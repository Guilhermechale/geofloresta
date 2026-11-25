/*
 * Núcleo da classificação de uso e cobertura do solo no navegador (sem servidor, sem custos):
 * projecção UTM, composição mediana, amostragem estratificada, Random Forest, matriz de confusão,
 * índice Kappa, áreas por classe e GeoTIFF de 8 bits com paleta. Funções puras, testadas em tests/js.
 */

// Classes do mapa anual Sentinel-2 10 m Land Use/Land Cover (Impact Observatory, Microsoft e Esri; CC BY 4.0)
export const CLASSES = [
    { code: 1, key: 'water', color: '#419bdf' },
    { code: 2, key: 'trees', color: '#397d49' },
    { code: 4, key: 'flooded', color: '#7a87c6' },
    { code: 5, key: 'crops', color: '#e49635' },
    { code: 7, key: 'built', color: '#c4281b' },
    { code: 8, key: 'bare', color: '#a59b8f' },
    { code: 9, key: 'snow', color: '#a8ebff' },
    { code: 11, key: 'rangeland', color: '#e3e2c3' },
];
export const CLASS_BY_CODE = Object.fromEntries(CLASSES.map((c) => [c.code, c]));
export const FEATURES = ['B2', 'B3', 'B4', 'B8', 'B11', 'B12', 'NDVI', 'NDWI', 'elevation', 'slope'];
export const BINS = 64;

// ---------- números aleatórios reprodutíveis ----------
export function rng(seed = 42) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

// ---------- UTM (WGS 84), para ler as imagens Sentinel-2 ----------
const A = 6378137, F = 1 / 298.257223563, K0 = 0.9996;
const E2 = F * (2 - F), EP2 = E2 / (1 - E2);
const N1 = F / (2 - F);
const AA = A / (1 + N1) * (1 + N1 * N1 / 4 + N1 ** 4 / 64);
const ALPHA = [N1 / 2 - 2 * N1 ** 2 / 3 + 5 * N1 ** 3 / 16, 13 * N1 ** 2 / 48 - 3 * N1 ** 3 / 5, 61 * N1 ** 3 / 240];

/** lon/lat (graus) -> [x, y] em metros no EPSG 326zz (norte) ou 327zz (sul). Série de Krüger (precisão sub-milimétrica). */
export function toUtm(lon, lat, epsg) {
    const zone = epsg % 100, south = Math.floor(epsg / 100) === 327;
    const lon0 = (zone * 6 - 183) * Math.PI / 180;
    const phi = lat * Math.PI / 180, lam = lon * Math.PI / 180 - lon0;
    const e = Math.sqrt(E2);
    const t = Math.sinh(Math.atanh(Math.sin(phi)) - e * Math.atanh(e * Math.sin(phi)));
    const xi = Math.atan2(t, Math.cos(lam)), eta = Math.atanh(Math.sin(lam) / Math.sqrt(1 + t * t));
    let x = eta, y = xi;
    for (let j = 1; j <= 3; j++) {
        x += ALPHA[j - 1] * Math.cos(2 * j * xi) * Math.sinh(2 * j * eta);
        y += ALPHA[j - 1] * Math.sin(2 * j * xi) * Math.cosh(2 * j * eta);
    }
    return [500000 + K0 * AA * x, K0 * AA * y + (south ? 10000000 : 0)];
}

// ---------- grelha de saída (WGS 84, píxeis com o mesmo tamanho em metros) ----------
export const MAX_PIXELS = 2_500_000;

/** Área máxima do polígono (km²): até 10 000 km² com píxel de 20 a 80 m; acima disso, modo regional. */
export const MAX_AREA_KM2 = 130_000;
export const REGIONAL_KM2 = 10_000;
export const RESOLUTIONS = [20, 40, 60, 80, 100, 120, 150, 200, 250, 300, 400];

/** Escolhe a resolução (20 m a 400 m) para o rectângulo da área caber no limite de píxeis. */
export function planGrid(bbox, maxPixels = MAX_PIXELS) {
    const [w, s, e, n] = bbox;
    const lat = (s + n) / 2;
    const mLat = 111320, mLon = 111320 * Math.cos(lat * Math.PI / 180);
    for (const res of RESOLUTIONS) {
        const dy = res / mLat, dx = res / mLon;
        const width = Math.max(1, Math.round((e - w) / dx)), height = Math.max(1, Math.round((n - s) / dy));
        if (width * height <= maxPixels) return { res, dx, dy, width, height, west: w, north: n, south: n - height * dy, east: w + width * dx };
    }
    return null;
}

export function areaKm2(bbox) {
    const [w, s, e, n] = bbox;
    return (e - w) * 111.32 * Math.cos((s + n) / 2 * Math.PI / 180) * (n - s) * 110.57;
}

// ---------- composição ----------
/** Mediana por píxel de várias camadas Float32 (NaN = sem dados). */
export function medianStack(layers, n) {
    const out = new Float32Array(n).fill(NaN);
    const v = new Float32Array(layers.length);
    for (let p = 0; p < n; p++) {
        let k = 0;
        for (const L of layers) { const x = L[p]; if (x === x) v[k++] = x; }
        if (!k) continue;
        const s = v.subarray(0, k).sort();
        out[p] = k % 2 ? s[(k - 1) / 2] : (s[k / 2 - 1] + s[k / 2]) / 2;
    }
    return out;
}

/**
 * Mediana «em fluxo» de várias bandas: guarda até k valores por píxel (Uint16, número digital da imagem),
 * pela ordem em que as imagens chegam, e descarta cada imagem logo a seguir. Para uma província usa cerca de
 * 120 MB em vez de ~600 MB (todas as imagens em memória). Os contadores são comuns às bandas.
 */
export function slotStack(n, nBands, k = 4) {
    return { n, k, count: new Uint8Array(n), values: Array.from({ length: nBands }, () => new Uint16Array(n * k)) };
}

/** Junta uma imagem: layers[b][p] = valor da banda b no píxel p (NaN/0 = sem dados); ok[p] = máscara de nuvens. */
export function slotAdd(st, layers, ok) {
    const { n, k, count, values } = st;
    const nb = layers.length;
    for (let p = 0; p < n; p++) {
        const c = count[p];
        if (c >= k || !ok[p]) continue;
        let valid = true;
        for (let b = 0; b < nb; b++) { const x = layers[b][p]; if (!(x > 0)) { valid = false; break; } }
        if (!valid) continue;
        for (let b = 0; b < nb; b++) values[b][p * k + c] = Math.min(65535, Math.round(layers[b][p]));
        count[p] = c + 1;
    }
}

/** Medianas por banda (Float32, NaN onde não houve dados), multiplicadas por scale (reflectância = DN/10000). */
export function slotMedians(st, scale = 1 / 10000) {
    const { n, k, count, values } = st;
    const tmp = new Uint16Array(k);
    return values.map((V) => {
        const out = new Float32Array(n).fill(NaN);
        for (let p = 0; p < n; p++) {
            const c = count[p];
            if (!c) continue;
            for (let i = 0; i < c; i++) {
                const x = V[p * k + i];
                let j = i - 1;
                while (j >= 0 && tmp[j] > x) { tmp[j + 1] = tmp[j]; j--; }
                tmp[j + 1] = x;
            }
            out[p] = (c % 2 ? tmp[(c - 1) / 2] : (tmp[c / 2 - 1] + tmp[c / 2]) / 2) * scale;
        }
        return out;
    });
}

/** Variáveis derivadas: NDVI, NDWI e declive (graus) a partir da altitude. */
export function indices(b) {
    const n = b.B4.length;
    const ndvi = new Float32Array(n), ndwi = new Float32Array(n);
    for (let p = 0; p < n; p++) {
        ndvi[p] = (b.B8[p] - b.B4[p]) / (b.B8[p] + b.B4[p]);
        ndwi[p] = (b.B3[p] - b.B8[p]) / (b.B3[p] + b.B8[p]);
    }
    return { NDVI: ndvi, NDWI: ndwi };
}

export function slope(elev, grid) {
    const { width: W, height: H, res } = grid;
    const out = new Float32Array(W * H);
    for (let j = 0; j < H; j++) {
        for (let i = 0; i < W; i++) {
            const g = (x, y) => elev[Math.min(H - 1, Math.max(0, y)) * W + Math.min(W - 1, Math.max(0, x))];
            const dzx = (g(i + 1, j) - g(i - 1, j)) / (2 * res), dzy = (g(i, j + 1) - g(i, j - 1)) / (2 * res);
            out[j * W + i] = Math.atan(Math.hypot(dzx, dzy)) * 180 / Math.PI;
        }
    }
    return out;
}

// ---------- quantização (o Random Forest trabalha com 64 classes por variável) ----------
/** Limiares por variável a partir dos quantis de uma amostra de valores. */
export function binEdges(values, bins = BINS) {
    const v = Float32Array.from(values.filter((x) => x === x)).sort();
    const edges = new Float32Array(bins - 1);
    for (let k = 1; k < bins; k++) edges[k - 1] = v.length ? v[Math.min(v.length - 1, Math.floor(k * v.length / bins))] : 0;
    return edges;
}

export function binOf(x, edges) {
    let lo = 0, hi = edges.length;
    while (lo < hi) { const m = (lo + hi) >> 1; if (x > edges[m]) lo = m + 1; else hi = m; }
    return lo;
}

// ---------- amostragem ----------
/**
 * Amostras estratificadas a partir do mapa de referência: só píxeis no centro de janelas 3x3 da mesma classe
 * (amostras «puras») e com todas as variáveis válidas. Devolve { idx, label (índice de classe 0..C-1), classes }.
 */
export function sample(ref, valid, grid, { perClass = 300, minPerClass = 20, seed = 42 } = {}) {
    const { width: W, height: H } = grid;
    const pools = new Map();
    for (let j = 1; j < H - 1; j++) {
        for (let i = 1; i < W - 1; i++) {
            const p = j * W + i, c = ref[p];
            if (!CLASS_BY_CODE[c] || !valid[p]) continue;
            let pure = true;
            for (let dj = -1; dj <= 1 && pure; dj++) for (let di = -1; di <= 1; di++) if (ref[p + dj * W + di] !== c) { pure = false; break; }
            if (!pure) continue;
            if (!pools.has(c)) pools.set(c, []);
            pools.get(c).push(p);
        }
    }
    const r = rng(seed);
    const classes = [...pools.keys()].filter((c) => pools.get(c).length >= minPerClass).sort((a, b) => a - b);
    const idx = [], label = [];
    classes.forEach((c, k) => {
        const pool = pools.get(c);
        for (let m = pool.length - 1; m > 0; m--) { const q = Math.floor(r() * (m + 1)); [pool[m], pool[q]] = [pool[q], pool[m]]; }
        for (const p of pool.slice(0, perClass)) { idx.push(p); label.push(k); }
    });
    return { idx: Int32Array.from(idx), label: Uint8Array.from(label), classes, skipped: [...pools.keys()].filter((c) => !classes.includes(c)) };
}

/** Separação aleatória treino/validação (70/30 por defeito). */
export function split(n, frac = 0.7, seed = 7) {
    const r = rng(seed), train = new Uint8Array(n);
    for (let k = 0; k < n; k++) train[k] = r() < frac ? 1 : 0;
    return train;
}

// ---------- Random Forest (árvores CART com índice de Gini) ----------
/**
 * X: Uint8Array n x nf (variáveis já quantizadas, linha a linha), y: Uint8Array (0..C-1).
 * Devolve { trees, nf, C, importance }.
 */
export function trainForest(X, y, nf, C, { trees = 100, maxDepth = 24, minLeaf = 1, mtry = Math.max(1, Math.floor(Math.sqrt(nf))), seed = 42 } = {}) {
    const n = y.length, r = rng(seed);
    const importance = new Float64Array(nf);
    const forest = [];
    const hist = new Float64Array(BINS * C), tot = new Float64Array(C), left = new Float64Array(C);
    for (let t = 0; t < trees; t++) {
        const boot = new Int32Array(n);
        for (let k = 0; k < n; k++) boot[k] = Math.floor(r() * n);
        const nodes = { f: [], th: [], l: [], r: [], leaf: [] };
        const grow = (ids, depth) => {
            const id = nodes.f.length;
            nodes.f.push(-1); nodes.th.push(0); nodes.l.push(-1); nodes.r.push(-1); nodes.leaf.push(0);
            tot.fill(0);
            for (const s of ids) tot[y[s]]++;
            let major = 0;
            for (let c = 1; c < C; c++) if (tot[c] > tot[major]) major = c;
            nodes.leaf[id] = major;
            const N = ids.length;
            if (tot[major] === N || N < 2 * minLeaf || depth >= maxDepth) return id;
            let parentG = 1;
            for (let c = 0; c < C; c++) parentG -= (tot[c] / N) ** 2;
            const totSnap = Float64Array.from(tot);
            // sorteio das variáveis candidatas
            const feats = [];
            while (feats.length < mtry) { const f = Math.floor(r() * nf); if (!feats.includes(f)) feats.push(f); }
            let best = { gain: 1e-12, f: -1, th: 0 };
            for (const f of feats) {
                hist.fill(0);
                for (const s of ids) hist[X[s * nf + f] * C + y[s]]++;
                left.fill(0);
                let nl = 0;
                for (let b = 0; b < BINS - 1; b++) {
                    let any = 0;
                    for (let c = 0; c < C; c++) { const h = hist[b * C + c]; left[c] += h; nl += h; any += h; }
                    if (!any) continue;
                    const nr = N - nl;
                    if (nl < minLeaf || nr < minLeaf) continue;
                    let gl = 1, gr = 1;
                    for (let c = 0; c < C; c++) { gl -= (left[c] / nl) ** 2; gr -= ((totSnap[c] - left[c]) / nr) ** 2; }
                    const gain = parentG - (nl * gl + nr * gr) / N;
                    if (gain > best.gain) best = { gain, f, th: b };
                }
            }
            if (best.f < 0) return id;
            importance[best.f] += best.gain * N;
            const L = [], R = [];
            for (const s of ids) (X[s * nf + best.f] <= best.th ? L : R).push(s);
            nodes.f[id] = best.f; nodes.th[id] = best.th;
            const li = grow(L, depth + 1), ri = grow(R, depth + 1);
            nodes.l[id] = li; nodes.r[id] = ri;
            return id;
        };
        grow(Array.from(boot), 0);
        forest.push({ f: Int8Array.from(nodes.f), th: Uint8Array.from(nodes.th), l: Int32Array.from(nodes.l), r: Int32Array.from(nodes.r), leaf: Uint8Array.from(nodes.leaf) });
    }
    const sum = importance.reduce((a, b) => a + b, 0) || 1;
    return { trees: forest, nf, C, importance: Array.from(importance, (v) => v / sum) };
}

/** Classe prevista (votação) para cada linha de X (Uint8Array n x nf). */
export function predict(model, X, n) {
    const { trees, nf, C } = model;
    const out = new Uint8Array(n), votes = new Uint16Array(C);
    for (let s = 0; s < n; s++) {
        votes.fill(0);
        const o = s * nf;
        for (const t of trees) {
            let k = 0;
            while (t.f[k] >= 0) k = X[o + t.f[k]] <= t.th[k] ? t.l[k] : t.r[k];
            votes[t.leaf[k]]++;
        }
        let m = 0;
        for (let c = 1; c < C; c++) if (votes[c] > votes[m]) m = c;
        out[s] = m;
    }
    return out;
}

// ---------- precisão ----------
/** Matriz de confusão (linhas = referência, colunas = mapa), precisão global, Kappa, produtor e utilizador. */
export function accuracy(truth, pred, C) {
    const cm = Array.from({ length: C }, () => new Array(C).fill(0));
    for (let k = 0; k < truth.length; k++) cm[truth[k]][pred[k]]++;
    const n = truth.length;
    let diag = 0, pe = 0;
    const rows = cm.map((r) => r.reduce((a, b) => a + b, 0));
    const cols = cm[0].map((_, j) => cm.reduce((a, r) => a + r[j], 0));
    for (let c = 0; c < C; c++) { diag += cm[c][c]; pe += rows[c] * cols[c]; }
    const oa = n ? diag / n : 0;
    pe = n ? pe / (n * n) : 0;
    const kappa = pe < 1 ? (oa - pe) / (1 - pe) : 1;
    return {
        matrix: cm, n, overall: oa, kappa,
        producers: cm.map((r, c) => (rows[c] ? r[c] / rows[c] : null)),
        users: cm.map((r, c) => (cols[c] ? cm[c][c] / cols[c] : null)),
    };
}

/** Área (ha) de cada classe; o tamanho do píxel em metros é constante na grelha planeada. */
export function areas(classMap, codes, res) {
    const counts = new Map();
    for (const v of classMap) if (v) counts.set(v, (counts.get(v) || 0) + 1);
    const ha = res * res / 1e4;
    return codes.map((c) => ({ code: c, ha: (counts.get(c) || 0) * ha }));
}

// ---------- GeoTIFF de 8 bits (EPSG:4326) com paleta, para abrir directamente no QGIS ----------
/**
 * Blocos vazios («sparse tiles»: posição 0 e tamanho 0) são permitidos pelo GDAL e usados pela Esri sobre o mar,
 * mas a geotiff.js 2.x falha a lê-los («Offset is outside the bounds of the DataView»).
 * Esta função faz a imagem devolver esses blocos como zeros (= sem dados) e deixa os outros como estão.
 */
export function allowSparseTiles(img) {
    const fd = img.fileDirectory;
    const counts = img.isTiled ? fd.TileByteCounts : fd.StripByteCounts;
    if (!counts || !Array.prototype.some.call(counts, (c) => c === 0)) return img;
    const orig = img.getTileOrStrip.bind(img);
    const perRow = Math.ceil(img.getWidth() / img.getTileWidth()), perCol = Math.ceil(img.getHeight() / img.getTileHeight());
    img.getTileOrStrip = async (x, y, sample, poolOrDecoder, signal) => {
        const index = img.planarConfiguration === 2 ? sample * perRow * perCol + y * perRow + x : y * perRow + x;
        if (counts[index] !== 0) return orig(x, y, sample, poolOrDecoder, signal);
        const bpp = img.planarConfiguration === 2 ? img.getSampleByteSize(sample) : img.getBytesPerPixel();
        return { x, y, sample, data: new ArrayBuffer(img.getTileWidth() * img.getBlockHeight(y) * bpp) };
    };
    return img;
}

export function geotiffClasses(grid, data, description = '') {
    const { width, height, dx, dy, west, north } = grid;
    const ascii = (s) => new TextEncoder().encode(s.replace(/[^\x20-\x7e]/g, '?') + '\0');
    const desc = ascii(description || 'Land cover');
    const nodata = ascii('0');
    const scale = new Float64Array([dx, dy, 0]);
    const tie = new Float64Array([0, 0, 0, west, north, 0]);
    const geokeys = new Uint16Array([1, 1, 0, 3, 1024, 0, 1, 2, 1025, 0, 1, 1, 2048, 0, 1, 4326]);
    const cmap = new Uint16Array(3 * 256);
    for (const c of CLASSES) {
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(c.color.slice(i, i + 2), 16) * 257);
        cmap[c.code] = r; cmap[256 + c.code] = g; cmap[512 + c.code] = b;
    }
    const tags = [
        [256, 4, 1, width], [257, 4, 1, height], [258, 3, 1, 8], [259, 3, 1, 1], [262, 3, 1, 3],
        [270, 2, desc.length, desc], [273, 4, 1, 0], [277, 3, 1, 1], [278, 4, 1, height], [279, 4, 1, data.length],
        [284, 3, 1, 1], [320, 3, cmap.length, new Uint8Array(cmap.buffer)], [339, 3, 1, 1],
        [33550, 12, 3, new Uint8Array(scale.buffer)], [33922, 12, 6, new Uint8Array(tie.buffer)],
        [34735, 3, geokeys.length, new Uint8Array(geokeys.buffer)], [42113, 2, nodata.length, nodata],
    ];
    const ifdSize = 2 + tags.length * 12 + 4;
    let off = 8 + ifdSize;
    const extras = [];
    for (const t of tags) {
        if (t[3] instanceof Uint8Array && t[3].length > 4) { t.push(off); extras.push([off, t[3]]); off += t[3].length + (t[3].length % 2); }
    }
    tags.find((t) => t[0] === 273)[3] = off;
    const buf = new ArrayBuffer(off + data.length);
    const dv = new DataView(buf), u8 = new Uint8Array(buf);
    dv.setUint16(0, 0x4949); dv.setUint16(2, 42, true); dv.setUint32(4, 8, true);
    dv.setUint16(8, tags.length, true);
    tags.forEach(([tag, type, count, value, offset], k) => {
        const p = 10 + k * 12;
        dv.setUint16(p, tag, true); dv.setUint16(p + 2, type, true); dv.setUint32(p + 4, count, true);
        if (offset !== undefined) dv.setUint32(p + 8, offset, true);
        else if (value instanceof Uint8Array) u8.set(value, p + 8);
        else if (type === 3) dv.setUint16(p + 8, value, true);
        else dv.setUint32(p + 8, value, true);
    });
    dv.setUint32(10 + tags.length * 12, 0, true);
    for (const [o, b] of extras) u8.set(b, o);
    u8.set(data, off);
    return buf;
}

// ---------- recorte pela área de interesse (regra par-ímpar, com buracos e multipolígonos) ----------
export function polygonMask(grid, geometry) {
    const { width: W, height: H, dx, dy, west, north } = grid;
    const inside = new Uint8Array(W * H);
    const rings = geometry?.type === 'Polygon' ? geometry.coordinates : geometry?.type === 'MultiPolygon' ? geometry.coordinates.flat() : null;
    if (!rings) return inside.fill(1);
    const xs = [];
    for (let j = 0; j < H; j++) {
        const lat = north - (j + 0.5) * dy;
        xs.length = 0;
        for (const ring of rings) {
            for (let k = 0, m = ring.length - 1; k < ring.length; m = k++) {
                const [x1, y1] = ring[k], [x2, y2] = ring[m];
                if ((y1 > lat) !== (y2 > lat)) xs.push(x1 + (lat - y1) / (y2 - y1) * (x2 - x1));
            }
        }
        xs.sort((a, b) => a - b);
        for (let k = 0; k + 1 < xs.length; k += 2) {
            const i0 = Math.max(0, Math.ceil((xs[k] - west) / dx - 0.5)), i1 = Math.min(W - 1, Math.floor((xs[k + 1] - west) / dx - 0.5));
            for (let i = i0; i <= i1; i++) inside[j * W + i] = 1;
        }
    }
    return inside;
}
