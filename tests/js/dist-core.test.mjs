import test from 'node:test';
import assert from 'node:assert/strict';
import { fromArrayBuffer } from 'geotiff';
import * as C from '../../resources/js/dist-core.js';

test('códigos de data: ida e volta, e meses da época', () => {
    assert.equal(C.dateCode('2024-10-31'), 24305);
    assert.equal(C.codeToIso(24305), '2024-10-31');
    assert.equal(C.codeToIso(C.dateCode('2026-01-01')), '2026-01-01');
    assert.deepEqual(C.neededMonths('2026-09-01', '2026-11-28'), [7, 8, 9, 10, 11]);
    assert.equal(C.neededMonths('2025-01-01', '2025-12-31').length, 12);
});

test('grelha: 10 m em áreas pequenas, maior em áreas grandes, recusa acima do limite; unidade mínima 0,5 ha', () => {
    assert.equal(C.planDistGrid([36.49, -16.56, 36.56, -16.49]).res, 10);
    const big = C.planDistGrid([35, -17, 36, -16]);
    assert.ok(big.res >= 100 && big.width * big.height <= C.MAX_PIXELS);
    assert.equal(C.planDistGrid([30, -20, 40, -10]), null);
    assert.equal(C.mmuPixels(10), 50);
    assert.equal(C.mmuPixels(100), 1);
});

test('máscara de nuvens: SCL, margem à volta das nuvens e névoa', () => {
    const W = 10, H = 1;
    const scl = Uint8Array.from([4, 4, 4, 9, 4, 4, 4, 5, 4, 5]);
    const blue = Float32Array.from([300, 300, 300, 3000, 300, 300, 300, 300, 900, 1500]);
    const ok = C.clearMask(scl, blue, W, H, 2);
    // névoa sobre vegetação (píxel 8) é apagada; solo nu claro de uma clareira (píxel 9) fica
    assert.deepEqual([...ok], [1, 0, 0, 0, 0, 0, 1, 1, 0, 1]);
});

test('máquina de estados: provisório com 1 anomalia, confirmado com 2 seguidas, anulado por observação normal', () => {
    const ms = C.monitorState(3);
    C.monitorStep(ms, Uint8Array.from([1, 1, 255]), 24100);
    C.monitorStep(ms, Uint8Array.from([0, 1, 1]), 24112);
    C.monitorStep(ms, Uint8Array.from([1, 255, 255]), 24124);
    assert.deepEqual([...ms.prov], [1, 1, 1]);
    assert.deepEqual([...ms.conf], [0, 1, 0]);
    assert.equal(ms.first[1], 24100);
    assert.equal(ms.first[0], 24124); // a sequência recomeçou depois da observação normal
    assert.deepEqual([...ms.obs], [3, 2, 1]);
});

test('anomalia óptica: abaixo do mínimo sazonal menos 0,20 e SWIR mais claro (não sombra)', () => {
    const b = C.opticalBaseline(3, [5, 6, 7]);
    for (const v of [0.6, 0.55, 0.62]) C.opticalBaseAdd(b, 6, Float32Array.from([v, v, v]), Float32Array.from([1000, 1000, 1000]));
    // píxel 0: clareira (NBR baixo, SWIR alto); 1: sombra (NBR baixo, SWIR baixo); 2: normal
    const { an, mag } = C.opticalAnomaly(b, 6, Float32Array.from([0.1, 0.1, 0.5]), Float32Array.from([2000, 600, 1000]));
    assert.deepEqual([...an], [1, 0, 0]);
    assert.ok(Math.abs(mag[0] - 0.45) < 1e-6);
});

test('anomalia de radar: queda >= 3 dB e z <= -2 face à mesma época', () => {
    const b = C.radarBaseline(2, [0, 1, 11]);
    for (const v of [-13, -13.5, -12.5, -13.2]) C.radarBaseAdd(b, 0, Float32Array.from([v, v]));
    const { an } = C.radarAnomaly(b, 0, Float32Array.from([-18, -14]));
    assert.deepEqual([...an], [1, 0]);
});

test('fusão: confirmado por um sensor, ou provisório nos dois; manchas < 0,5 ha retiradas', () => {
    const W = 20, H = 20, N = W * H;
    const mk = () => C.monitorState(N);
    const op = mk(), ra = mk();
    const forest = new Uint8Array(N).fill(1);
    // bloco A (8x8 = 64 px = 0,64 ha a 10 m): confirmado óptico
    for (let j = 0; j < 8; j++) for (let i = 0; i < 8; i++) { const p = j * W + i; op.prov[p] = op.conf[p] = 1; op.first[p] = 24200; }
    // bloco B (8x8): provisório nos dois sensores -> confirmado
    for (let j = 10; j < 18; j++) for (let i = 10; i < 18; i++) { const p = j * W + i; op.prov[p] = ra.prov[p] = 1; op.first[p] = 24250; ra.first[p] = 24240; }
    // bloco C (2x2 = 0,04 ha): confirmado mas demasiado pequeno
    for (let j = 0; j < 2; j++) for (let i = 15; i < 17; i++) { const p = j * W + i; ra.prov[p] = ra.conf[p] = 1; ra.first[p] = 24300; }
    const f = C.fuse(op, ra, forest, W, H, 10);
    assert.equal(f.status[0], 2);
    assert.equal(f.status[11 * W + 11], 2);
    assert.equal(f.source[11 * W + 11], 3);
    assert.equal(f.date[11 * W + 11], 24240);
    assert.equal(f.status[15], 0);
    const { list } = C.patches(f, { width: W, height: H, res: 10, west: 0, north: 0, dx: 1, dy: 1 });
    assert.equal(list.length, 2);
    assert.ok(Math.abs(list[0].ha - 0.64) < 1e-9);
    const bm = C.byMonth(f, 10);
    assert.ok(Math.abs(bm['2024-07'].conf - 0.64) < 1e-9 && Math.abs(bm['2024-08'].conf - 0.64) < 1e-9);
});

test('vectorização: um quadrado com buraco dá um polígono com anel exterior e interior', () => {
    const W = 5, H = 5, m = new Uint8Array(25);
    for (let j = 0; j < 5; j++) for (let i = 0; i < 5; i++) if (!(i === 2 && j === 2)) m[j * W + i] = 1;
    const { lab, sizes } = C.label(m, W, H);
    const fc = C.vectorize(lab, sizes.length - 1, { width: W, height: H, west: 30, north: -10, dx: 0.01, dy: 0.01 });
    assert.equal(fc.features.length, 1);
    const rings = fc.features[0].geometry.coordinates;
    assert.equal(rings.length, 2);
    assert.deepEqual(rings[0][0], rings[0][rings[0].length - 1]);
    assert.equal(rings[0].length, 5); // 4 cantos + fecho (vértices colineares retirados)
});

test('GeoTIFF de várias bandas: abre com a geotiff.js, valores e georreferência certos', async () => {
    const grid = { width: 4, height: 3, dx: 0.001, dy: 0.001, west: 36.5, north: -16.5 };
    const a = Uint16Array.from({ length: 12 }, (_, k) => k), b = Uint16Array.from({ length: 12 }, (_, k) => 24000 + k);
    const tif = await fromArrayBuffer(C.geotiffBands(grid, [a, b], ['status', 'date'], 'test'));
    const img = await tif.getImage();
    assert.equal(img.getSamplesPerPixel(), 2);
    const [ra, rb] = await img.readRasters();
    assert.deepEqual([...ra], [...a]);
    assert.deepEqual([...rb], [...b]);
    assert.deepEqual(img.getOrigin().slice(0, 2), [36.5, -16.5]);
    const one = await (await fromArrayBuffer(C.geotiffBands(grid, [a], ['status']))).getImage();
    assert.deepEqual([...(await one.readRasters())[0]], [...a]);
});

test('ZIP: CRC32 correcto e estrutura válida', async () => {
    assert.equal(C.crc32(new TextEncoder().encode('123456789')), 0xcbf43926);
    const z = new Uint8Array(await C.zip([{ name: 'a.txt', data: 'olá' }, { name: 'b.bin', data: new Uint8Array([1, 2, 3]) }]).arrayBuffer());
    const dv = new DataView(z.buffer);
    assert.equal(dv.getUint32(0, true), 0x04034b50);
    assert.equal(dv.getUint32(z.length - 22, true), 0x06054b50);
    assert.equal(dv.getUint16(z.length - 22 + 10, true), 2);
});

test('blocos Hansen: nomes pelo canto superior esquerdo', () => {
    assert.deepEqual(C.hansenTiles([36.49, -16.56, 36.56, -16.49]).map((t) => t.name), ['10S_030E']);
    assert.deepEqual(C.hansenTiles([-60.3, -20.8, -60.2, -20.6]).map((t) => t.name), ['20S_070W']);
    assert.deepEqual(C.hansenTiles([9.5, 0.5, 10.5, 1.5]).map((t) => t.name), ['10N_000E', '10N_010E']);
    assert.deepEqual(C.hansenTiles([113.5, -0.5, 113.9, 0.5]).map((t) => t.name), ['10N_110E', '00N_110E']);
});

test('média em janela ignora NaN (filtro do radar)', () => {
    const a = Float32Array.from([1, 1, 1, NaN, 5, 1, 1, 1, 1]);
    const m = C.boxMean(a, 3, 3, 3);
    assert.ok(Math.abs(m[4] - 12 / 8) < 1e-6);
    assert.ok(Number.isNaN(m[3]));
});

test('amostra de validação estratificada', () => {
    const N = 100, fused = { status: new Uint8Array(N), date: new Uint16Array(N) };
    for (let p = 0; p < 10; p++) { fused.status[p] = 2; fused.date[p] = 24100; }
    const forest = new Uint8Array(N).fill(1);
    const v = C.validationSample(fused, forest, { width: 10, west: 0, north: 0, dx: 1, dy: 1, res: 10 }, 5);
    assert.equal(v.points.filter((p) => p.stratum === 'alert').length, 5);
    assert.equal(v.points.filter((p) => p.stratum === 'forest_no_alert').length, 5);
    assert.ok(Math.abs(v.strataHa.alert - 0.1) < 1e-9);
});
