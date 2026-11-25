import test from 'node:test';
import assert from 'node:assert/strict';
import {
    toUtm, planGrid, areaKm2, medianStack, binEdges, binOf, sample, split, trainForest, predict, accuracy, areas, geotiffClasses, rng, allowSparseTiles, slotStack, slotAdd, slotMedians, MAX_AREA_KM2,
} from '../../resources/js/lulc-core.js';
import { fromArrayBuffer } from 'geotiff';

test('UTM igual ao GDAL (milímetros)', () => {
    const ref = [[32645, 84.25, 27.75, 228932.244, 3072539.499], [32736, 34.85, -19.84, 693740.534, 7805162.315],
        [32733, 13.2, -8.8, 302016.1, 9026783.255], [32631, 2.35, 48.85, 452314.891, 5410984.888]];
    for (const [epsg, lon, lat, x, y] of ref) {
        const [ux, uy] = toUtm(lon, lat, epsg);
        assert.ok(Math.abs(ux - x) < 0.01 && Math.abs(uy - y) < 0.01, `${epsg}: ${ux}, ${uy}`);
    }
});

test('grelha: 20 m em áreas pequenas, resolução maior em áreas grandes, recusa áreas enormes', () => {
    const small = planGrid([84.25, 27.45, 84.45, 27.6]);
    assert.equal(small.res, 20);
    assert.ok(small.width * small.height <= 2_500_000);
    const big = planGrid([84, 27, 85, 28]);
    assert.ok(big.res > 20);
    assert.equal(planGrid([0, 0, 10, 10]), null);
    assert.ok(Math.abs(areaKm2([0, 0, 1, 1]) - 12309) < 50);
});

test('mediana ignora NaN', () => {
    const m = medianStack([Float32Array.of(1, NaN, 5), Float32Array.of(3, NaN, NaN), Float32Array.of(2, NaN, 7)], 3);
    assert.deepEqual(Array.from(m).map((v) => (Number.isNaN(v) ? 'nan' : v)), [2, 'nan', 6]);
});

test('quantização por quantis', () => {
    const e = binEdges(Array.from({ length: 1000 }, (_, k) => k), 4);
    assert.deepEqual(Array.from(e), [250, 500, 750]);
    assert.equal(binOf(-1, e), 0); assert.equal(binOf(250, e), 0); assert.equal(binOf(251, e), 1); assert.equal(binOf(9999, e), 3);
});

test('amostragem só em píxeis puros e estratificada', () => {
    const W = 20, H = 20, ref = new Uint8Array(W * H).fill(2);
    for (let j = 0; j < H; j++) for (let i = 10; i < W; i++) ref[j * W + i] = 5;  // metade árvores, metade culturas
    ref[5 * W + 5] = 1;                                                              // água isolada: impura e rara
    const valid = new Uint8Array(W * H).fill(1);
    const s = sample(ref, valid, { width: W, height: H }, { perClass: 30, minPerClass: 5 });
    assert.deepEqual(s.classes, [2, 5]);
    assert.equal(s.idx.length, 60);
    for (const p of s.idx) { const i = p % W; assert.ok(i !== 9 && i !== 10, 'nunca na fronteira'); }
});

test('Random Forest aprende um problema simples e a precisão/Kappa batem certo', () => {
    const r = rng(1), n = 600, nf = 3, X = new Uint8Array(n * nf), y = new Uint8Array(n);
    for (let s = 0; s < n; s++) {
        const c = s % 3; y[s] = c;
        X[s * nf] = Math.min(63, Math.max(0, c * 20 + Math.floor(r() * 18)));   // variável útil
        X[s * nf + 1] = Math.floor(r() * 64); X[s * nf + 2] = Math.floor(r() * 64); // ruído
    }
    const tr = split(n, 0.7, 3);
    const idxT = [], idxV = [];
    for (let s = 0; s < n; s++) (tr[s] ? idxT : idxV).push(s);
    const pick = (ids) => { const out = new Uint8Array(ids.length * nf); ids.forEach((s, k) => out.set(X.subarray(s * nf, s * nf + nf), k * nf)); return out; };
    const model = trainForest(pick(idxT), Uint8Array.from(idxT, (s) => y[s]), nf, 3, { trees: 30 });
    const pred = predict(model, pick(idxV), idxV.length);
    const acc = accuracy(Uint8Array.from(idxV, (s) => y[s]), pred, 3);
    assert.ok(acc.overall > 0.95, `OA ${acc.overall}`);
    assert.ok(acc.kappa > 0.9);
    assert.ok(model.importance[0] > model.importance[1] + model.importance[2], 'a variável útil é a mais importante');
});

test('Kappa de um exemplo conhecido', () => {
    // 2 classes: 45 + 15 acertos, 10 + 30 erros (Congalton & Green): OA 0,60; pe = (55*75 + 45*25)/100² = 0,525; Kappa ≈ 0,158
    const truth = [], pred = [];
    const add = (t, p, k) => { for (let i = 0; i < k; i++) { truth.push(t); pred.push(p); } };
    add(0, 0, 45); add(0, 1, 10); add(1, 0, 30); add(1, 1, 15);
    const a = accuracy(truth, pred, 2);
    assert.equal(a.overall, 0.6);
    assert.ok(Math.abs(a.kappa - (0.6 - 0.525) / (1 - 0.525)) < 1e-9);
    assert.deepEqual(a.matrix, [[45, 10], [30, 15]]);
});

test('áreas em hectares e GeoTIFF com paleta', () => {
    const data = Uint8Array.of(2, 2, 5, 0);
    const ar = areas(data, [2, 5], 20);
    assert.deepEqual(ar, [{ code: 2, ha: 0.08 }, { code: 5, ha: 0.04 }]);
    const buf = geotiffClasses({ width: 2, height: 2, dx: 0.0002, dy: 0.0002, west: 30, north: -19 }, data, 'teste');
    const dv = new DataView(buf);
    assert.equal(dv.getUint16(2, true), 42);
    assert.deepEqual(Array.from(new Uint8Array(buf, buf.byteLength - 4)), [2, 2, 5, 0]);
});

test('recorte por polígono (com buraco)', async () => {
    const { polygonMask } = await import('../../resources/js/lulc-core.js');
    const grid = { width: 10, height: 10, dx: 1, dy: 1, west: 0, north: 10 };
    const geom = { type: 'Polygon', coordinates: [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]]] };
    const m = polygonMask(grid, geom);
    assert.equal(m.reduce((a, b) => a + b, 0), 100 - 16);
    assert.equal(m[5 * 10 + 5], 0);
    assert.equal(polygonMask(grid, null).reduce((a, b) => a + b, 0), 100);
});

// TIFF em blocos 16x16 (2 blocos), com o segundo vazio (posição 0, tamanho 0), como a Esri devolve sobre o mar
function sparseTiff() {
    const tile = 256, entries = [[256, 3, 32], [257, 3, 16], [258, 3, 8], [259, 3, 1], [262, 3, 1], [277, 3, 1], [322, 3, 16], [323, 3, 16], [324, 4, 2], [325, 4, 2], [339, 3, 1]];
    const ifd = 8, n = entries.length, arrays = ifd + 2 + n * 12 + 4, data = arrays + 16;
    const buf = new ArrayBuffer(data + tile), v = new DataView(buf);
    v.setUint16(0, 0x4949); v.setUint16(2, 42, true); v.setUint32(4, ifd, true); v.setUint16(ifd, n, true);
    entries.forEach(([tag, type, val], k) => {
        const o = ifd + 2 + k * 12;
        v.setUint16(o, tag, true); v.setUint16(o + 2, type, true); v.setUint32(o + 4, tag === 324 || tag === 325 ? 2 : 1, true);
        if (tag === 324) v.setUint32(o + 8, arrays, true); else if (tag === 325) v.setUint32(o + 8, arrays + 8, true);
        else if (type === 3) v.setUint16(o + 8, val, true); else v.setUint32(o + 8, val, true);
    });
    v.setUint32(arrays, data, true); v.setUint32(arrays + 4, 0, true);      // posições dos blocos
    v.setUint32(arrays + 8, tile, true); v.setUint32(arrays + 12, 0, true); // tamanhos: o segundo é vazio
    new Uint8Array(buf, data, tile).fill(7);
    return buf;
}

test('blocos vazios do TIFF (Esri sobre o mar) lidos como «sem dados»', async () => {
    const img = allowSparseTiles(await (await fromArrayBuffer(sparseTiff())).getImage());
    const [d] = await img.readRasters();
    assert.equal(d.length, 32 * 16);
    assert.equal(d[0], 7);        // primeiro bloco: dados
    assert.equal(d[20], 0);       // segundo bloco: vazio
    assert.equal(d[32 * 15 + 31], 0);
});

test('mediana em fluxo igual à mediana normal e limitada a k valores', () => {
    const n = 4, st = slotStack(n, 2, 4);
    const ok = Uint8Array.from([1, 1, 1, 0]);
    // 5 imagens: só as 4 primeiras válidas entram; o píxel 3 é nuvem em todas; o píxel 2 tem uma falha (NaN)
    const imgs = [[100, 200, 300, 1], [300, 400, NaN, 1], [200, 600, 500, 1], [400, 800, 700, 1], [999, 999, 999, 1]];
    for (const v of imgs) slotAdd(st, [Float32Array.from(v), Float32Array.from(v.map((x) => x * 2))], ok);
    const [b1, b2] = slotMedians(st, 1);
    assert.equal(b1[0], 250);          // 100 200 300 400 -> (200+300)/2 (a 5.ª imagem não entra: k = 4)
    assert.equal(b1[1], 500);          // 200 400 600 800
    assert.equal(b1[2], 600);          // 300 500 700 999: o NaN não conta e a 5.ª imagem ocupa o lugar
    assert.ok(Number.isNaN(b1[3]));    // nuvem em todas
    assert.equal(b2[0], 500);          // a segunda banda acompanha a primeira
});

test('modo regional: uma província de ~130 000 km² cabe na grelha com píxel de 250 a 400 m', () => {
    const niassa = planGrid([34.5, -15.4, 38.5, -11.4]);   // rectângulo de ~190 000 km²
    assert.ok(niassa, 'deve caber');
    assert.ok(niassa.res >= 250 && niassa.res <= 400, String(niassa.res));
    assert.ok(niassa.width * niassa.height <= 2_500_000);
    assert.equal(planGrid([84.25, 27.45, 84.45, 27.6]).res, 20);   // áreas pequenas não mudam
    assert.equal(MAX_AREA_KM2, 130000);
});
