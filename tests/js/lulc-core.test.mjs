import test from 'node:test';
import assert from 'node:assert/strict';
import {
    toUtm, planGrid, areaKm2, medianStack, binEdges, binOf, sample, split, trainForest, predict, accuracy, areas, geotiffClasses, rng,
} from '../../resources/js/lulc-core.js';

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
