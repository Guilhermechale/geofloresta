/*
 * GF-DIST - detector de desmatamento da GEOFLORESTA (funções puras, testadas em tests/js/dist-core.test.mjs).
 *
 * Método (adaptado do DIST-ALERT, Hansen/Pickens/Song, UMD-GLAD e NASA OPERA; radar inspirado no RADD, Reiche et al.):
 *  Óptico (Sentinel-2 L2A): NBR por observação limpa; linha de base = MÍNIMO do NBR da mesma época (mês ± 1)
 *   nos 3 anos anteriores; anomalia = NBR abaixo desse mínimo menos 0,20 e SWIR 1,6 µm acima de 1,1 x a média
 *   (separa clareiras de sombras de nuvens). Máscara de nuvens SCL + margem de 50 m + névoa (azul >= 0,08).
 *  Radar (Sentinel-1 RTC): VH em dB com filtro espacial; linha de base = média e desvio-padrão da mesma época
 *   nos 3 anos anteriores; anomalia = queda >= 3 dB e z <= -2.
 *  Cada sensor: 1 anomalia = provisório; 2 seguidas (sem observação normal entre elas) = confirmado.
 *  Fusão: confirmado = óptico confirmado OU radar confirmado OU (óptico provisório E radar provisório).
 *  Só em floresta (NBR médio da linha de base >= 0,35; sem dados ópticos, VH médio >= -16 dB) e manchas >= 0,5 ha.
 */
export const VERSION = 'GF-DIST 1.0';
export const PARAMS = {
    optical: { drop: 0.2, forestNbr: 0.35, swirRatio: 1.1, hazeBlue: 800, cloudBufferM: 50, minBaseObs: 2 },
    radar: { dropDb: 3, z: 2, minSd: 0.3, forestVhDb: -16, minBaseObs: 3 },
    mmuHa: 0.5,
    baselineYears: 3,
};

// ---------- grelha (WGS 84, píxel com o mesmo tamanho em metros) ----------
export const MAX_PIXELS = 1_000_000;
export const MAX_AREA_KM2 = 10_000;
export const RESOLUTIONS = [10, 20, 30, 40, 60, 80, 100, 120];

export function planDistGrid(bbox, maxPixels = MAX_PIXELS) {
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

/** Número mínimo de píxeis de uma mancha (0,5 ha). */
export const mmuPixels = (res) => Math.max(1, Math.ceil(PARAMS.mmuHa * 1e4 / (res * res)));

// ---------- datas ----------
/** Código de data compacto e legível: (ano - 2000) * 1000 + dia do ano (ex.: 24305 = 31/10/2024). Cabe em Uint16. */
export function dateCode(iso) {
    const d = new Date(`${iso.slice(0, 10)}T00:00:00Z`);
    const doy = Math.floor((d - Date.UTC(d.getUTCFullYear(), 0, 1)) / 864e5) + 1;
    return (d.getUTCFullYear() - 2000) * 1000 + doy;
}
export function codeToIso(code) {
    if (!code) return '';
    const y = 2000 + Math.floor(code / 1000), doy = code % 1000;
    return new Date(Date.UTC(y, 0, doy)).toISOString().slice(0, 10);
}
/** Meses (0-11) que a linha de base precisa: os do período e os vizinhos (janela sazonal de mês ± 1). */
export function neededMonths(start, end) {
    const out = new Set();
    let d = new Date(`${start}T00:00:00Z`);
    const e = new Date(`${end}T00:00:00Z`);
    for (let k = 0; d <= e && k < 40; k++) {
        const m = d.getUTCMonth();
        out.add(m); out.add((m + 11) % 12); out.add((m + 1) % 12);
        d = new Date(Date.UTC(d.getUTCFullYear(), m + 1, 1));
    }
    return [...out].sort((a, b) => a - b);
}

// ---------- máscara de nuvens ----------
const BAD = new Set([0, 1, 3, 8, 9, 10, 11]);
/** Píxeis ópticos válidos: SCL vegetação (4) ou solo (5), longe (>= buf píxeis) de nuvens/sombras, sem névoa. */
export function clearMask(scl, blue, W, H, buf) {
    const N = W * H, ok = new Uint8Array(N), bad = new Uint8Array(N);
    for (let p = 0; p < N; p++) {
        const s = scl[p];
        if (s === 4 || s === 5) ok[p] = 1;
        if (s === 3 || s === 8 || s === 9 || s === 10) bad[p] = 1;
    }
    if (buf > 0) {
        const near = dilate(bad, W, H, buf);
        for (let p = 0; p < N; p++) if (near[p]) ok[p] = 0;
    }
    if (blue) for (let p = 0; p < N; p++) if (ok[p] && !(blue[p] < PARAMS.optical.hazeBlue)) ok[p] = 0;
    return ok;
}
export const isBadScl = (s) => BAD.has(s);

/** Dilatação quadrada (r píxeis) de uma máscara binária, em duas passagens separáveis. */
export function dilate(m, W, H, r) {
    const tmp = new Uint8Array(W * H), out = new Uint8Array(W * H);
    for (let j = 0; j < H; j++) {
        let last = -1e9;
        for (let i = 0; i < W; i++) { if (m[j * W + i]) last = i; if (i - last <= r) tmp[j * W + i] = 1; }
        last = 1e9;
        for (let i = W - 1; i >= 0; i--) { if (m[j * W + i]) last = i; if (last - i <= r) tmp[j * W + i] = 1; }
    }
    for (let i = 0; i < W; i++) {
        let last = -1e9;
        for (let j = 0; j < H; j++) { if (tmp[j * W + i]) last = j; if (j - last <= r) out[j * W + i] = 1; }
        last = 1e9;
        for (let j = H - 1; j >= 0; j--) { if (tmp[j * W + i]) last = j; if (last - j <= r) out[j * W + i] = 1; }
    }
    return out;
}

/** Média em janela quadrada (k ímpar), ignorando NaN; usada para reduzir o ruído (speckle) do radar. */
export function boxMean(a, W, H, k) {
    if (k <= 1) return a;
    const r = (k - 1) >> 1, N = W * H;
    const sum = new Float64Array((W + 1) * (H + 1)), cnt = new Float64Array((W + 1) * (H + 1));
    for (let j = 0; j < H; j++) {
        let rs = 0, rc = 0;
        for (let i = 0; i < W; i++) {
            const v = a[j * W + i];
            if (v === v) { rs += v; rc++; }
            const q = (j + 1) * (W + 1) + i + 1;
            sum[q] = sum[q - W - 1] + rs; cnt[q] = cnt[q - W - 1] + rc;
        }
    }
    const out = new Float32Array(N).fill(NaN);
    for (let j = 0; j < H; j++) {
        const j0 = Math.max(0, j - r), j1 = Math.min(H, j + r + 1);
        for (let i = 0; i < W; i++) {
            if (!(a[j * W + i] === a[j * W + i])) continue;
            const i0 = Math.max(0, i - r), i1 = Math.min(W, i + r + 1);
            const A = j1 * (W + 1) + i1, B = j0 * (W + 1) + i1, C = j1 * (W + 1) + i0, D = j0 * (W + 1) + i0;
            const c = cnt[A] - cnt[B] - cnt[C] + cnt[D];
            if (c >= Math.max(1, (k * k) >> 1)) out[j * W + i] = (sum[A] - sum[B] - sum[C] + sum[D]) / c;
        }
    }
    return out;
}

// ---------- linha de base óptica ----------
/** Mínimo sazonal do NBR (por mês, com os meses vizinhos), média do NBR (floresta) e média do SWIR 1,6 µm. */
export function opticalBaseline(N, months) {
    const bins = {};
    for (const m of months) bins[m] = { min: new Float32Array(N).fill(Infinity), n: new Uint8Array(N) };
    return { N, bins, nbrSum: new Float32Array(N), nbrN: new Uint16Array(N), s16Sum: new Float32Array(N), s16N: new Uint16Array(N), scenes: 0 };
}

/** Junta uma data de observação (camadas já combinadas por data) à linha de base. */
export function opticalBaseAdd(b, month, nbr, s16) {
    const targets = [month, (month + 11) % 12, (month + 1) % 12].map((m) => b.bins[m]).filter(Boolean);
    let any = false;
    for (let p = 0; p < b.N; p++) {
        const v = nbr[p];
        if (!(v === v)) continue;
        any = true;
        b.nbrSum[p] += v; b.nbrN[p]++;
        const s = s16[p];
        if (s === s) { b.s16Sum[p] += s; b.s16N[p]++; }
        for (const t of targets) { if (v < t.min[p]) t.min[p] = v; if (t.n[p] < 255) t.n[p]++; }
    }
    if (any) b.scenes++;
    return any;
}

// ---------- linha de base do radar ----------
export function radarBaseline(N, months) {
    const bins = {};
    for (const m of months) bins[m] = { s: new Float32Array(N), q: new Float32Array(N), n: new Uint8Array(N) };
    return { N, bins, sum: new Float32Array(N), n: new Uint16Array(N), scenes: 0 };
}
export function radarBaseAdd(b, month, db) {
    const targets = [month, (month + 11) % 12, (month + 1) % 12].map((m) => b.bins[m]).filter(Boolean);
    let any = false;
    for (let p = 0; p < b.N; p++) {
        const v = db[p];
        if (!(v === v)) continue;
        any = true;
        b.sum[p] += v; b.n[p]++;
        for (const t of targets) { t.s[p] += v; t.q[p] += v * v; if (t.n[p] < 255) t.n[p]++; }
    }
    if (any) b.scenes++;
    return any;
}

// ---------- monitorização: máquina de estados por píxel (provisório / confirmado) ----------
export function monitorState(N) {
    return { N, st: new Uint8Array(N), prov: new Uint8Array(N), conf: new Uint8Array(N), first: new Uint16Array(N), confDate: new Uint16Array(N), mag: new Float32Array(N), obs: new Uint16Array(N), dates: 0 };
}

/**
 * Uma data de observação: an[p] = 1 anomalia, 0 normal, 255 sem observação válida.
 * Uma anomalia marca provisório (e a data); outra seguida confirma; uma observação normal antes da confirmação
 * anula a sequência (o provisório fica registado como tal).
 */
export function monitorStep(ms, an, code, mag = null) {
    ms.dates++;
    for (let p = 0; p < ms.N; p++) {
        const a = an[p];
        if (a === 255) continue;
        ms.obs[p]++;
        if (ms.conf[p]) continue;
        if (a === 1) {
            if (ms.st[p] === 0) { ms.first[p] = code; ms.mag[p] = 0; }
            if (mag && mag[p] > ms.mag[p]) ms.mag[p] = mag[p];
            ms.prov[p] = 1;
            if (ms.st[p] >= 1) { ms.conf[p] = 1; ms.confDate[p] = code; }
            ms.st[p] = Math.min(2, ms.st[p] + 1);
        } else {
            ms.st[p] = 0;
        }
    }
}

/** Anomalias ópticas de uma data (1/0/255) e a intensidade (queda do NBR abaixo do mínimo sazonal). */
export function opticalAnomaly(b, month, nbr, s16) {
    const P = PARAMS.optical, bin = b.bins[month], N = b.N;
    const an = new Uint8Array(N).fill(255), mag = new Float32Array(N);
    if (!bin) return { an, mag };
    for (let p = 0; p < N; p++) {
        const v = nbr[p];
        if (!(v === v) || bin.n[p] < P.minBaseObs) continue;
        const s16m = b.s16N[p] ? b.s16Sum[p] / b.s16N[p] : NaN;
        const drop = bin.min[p] - v;
        const isAn = drop > P.drop && s16[p] > s16m * P.swirRatio;
        an[p] = isAn ? 1 : 0;
        if (isAn) mag[p] = drop;
    }
    return { an, mag };
}

export function radarAnomaly(b, month, db) {
    const P = PARAMS.radar, bin = b.bins[month], N = b.N;
    const an = new Uint8Array(N).fill(255), mag = new Float32Array(N);
    if (!bin) return { an, mag };
    for (let p = 0; p < N; p++) {
        const v = db[p], n = bin.n[p];
        if (!(v === v) || n < P.minBaseObs) continue;
        const mu = bin.s[p] / n, sd = Math.sqrt(Math.max(0, bin.q[p] / n - mu * mu));
        const drop = mu - v;
        const isAn = drop >= P.dropDb && -drop / Math.max(sd, P.minSd) <= -P.z;
        an[p] = isAn ? 1 : 0;
        if (isAn) mag[p] = drop;
    }
    return { an, mag };
}

/** Floresta de referência: NBR médio da linha de base >= 0,35; sem dados ópticos suficientes, VH médio >= -16 dB. */
export function forestMask(ob, rb, inside) {
    const N = inside.length, f = new Uint8Array(N);
    for (let p = 0; p < N; p++) {
        if (!inside[p]) continue;
        if (ob && ob.nbrN[p] >= 3) f[p] = ob.nbrSum[p] / ob.nbrN[p] >= PARAMS.optical.forestNbr ? 1 : 0;
        else if (rb && rb.n[p] >= 3) f[p] = rb.sum[p] / rb.n[p] >= PARAMS.radar.forestVhDb ? 1 : 0;
    }
    return f;
}

// ---------- fusão, unidade mínima e manchas ----------
/**
 * Estado final por píxel: 2 confirmado, 1 provisório, 0 sem alerta. Fonte: 1 óptico, 2 radar, 3 ambos.
 * Data: primeira detecção (o mais cedo dos dois sensores). Manchas com menos de 0,5 ha são retiradas.
 */
export function fuse(op, ra, forest, W, H, res) {
    const N = W * H, minPx = mmuPixels(res);
    const conf = new Uint8Array(N), any = new Uint8Array(N);
    for (let p = 0; p < N; p++) {
        if (!forest[p]) continue;
        const oc = op?.conf[p], rc = ra?.conf[p], opv = op?.prov[p], rpv = ra?.prov[p];
        if (oc || rc || (opv && rpv)) conf[p] = 1;
        if (conf[p] || opv || rpv) any[p] = 1;
    }
    const keepConf = sieve(conf, W, H, minPx), keepAny = sieve(any, W, H, minPx);
    const status = new Uint8Array(N), source = new Uint8Array(N), date = new Uint16Array(N), mag = new Float32Array(N);
    for (let p = 0; p < N; p++) {
        if (!keepAny[p]) continue;
        status[p] = keepConf[p] ? 2 : 1;
        const oHit = op && op.prov[p], rHit = ra && ra.prov[p];
        source[p] = (oHit ? 1 : 0) | (rHit ? 2 : 0);
        const ds = [oHit ? op.first[p] : 0, rHit ? ra.first[p] : 0].filter(Boolean);
        date[p] = ds.length ? Math.min(...ds) : 0;
        mag[p] = oHit ? op.mag[p] : 0;
    }
    return { status, source, date, mag };
}

/** Rótulos de componentes ligados (8 vizinhos). Devolve { lab (Int32), sizes }. */
export function label(mask, W, H) {
    const N = W * H, lab = new Int32Array(N), sizes = [0];
    const stack = new Int32Array(N);
    let next = 0;
    for (let p0 = 0; p0 < N; p0++) {
        if (!mask[p0] || lab[p0]) continue;
        next++;
        let sp = 0, size = 0;
        stack[sp++] = p0; lab[p0] = next;
        while (sp) {
            const p = stack[--sp]; size++;
            const i = p % W, j = (p / W) | 0;
            for (let dj = -1; dj <= 1; dj++) {
                const jj = j + dj;
                if (jj < 0 || jj >= H) continue;
                for (let di = -1; di <= 1; di++) {
                    const ii = i + di;
                    if (ii < 0 || ii >= W || (!di && !dj)) continue;
                    const q = jj * W + ii;
                    if (mask[q] && !lab[q]) { lab[q] = next; stack[sp++] = q; }
                }
            }
        }
        sizes.push(size);
    }
    return { lab, sizes };
}

export function sieve(mask, W, H, minPx) {
    if (minPx <= 1) return mask;
    const { lab, sizes } = label(mask, W, H);
    const out = new Uint8Array(W * H);
    for (let p = 0; p < W * H; p++) if (lab[p] && sizes[lab[p]] >= minPx) out[p] = 1;
    return out;
}

/** Manchas (componentes de alerta): área total e confirmada, primeira data, centro e caixa. Ordenadas pela área. */
export function patches(fused, grid) {
    const { width: W, height: H, res, west, north, dx, dy } = grid;
    const { lab, sizes } = label(fused.status, W, H);
    const n = sizes.length - 1, ha = res * res / 1e4;
    const P = Array.from({ length: n }, (_, k) => ({ id: k + 1, px: 0, conf: 0, first: 0, sx: 0, sy: 0, i0: W, i1: 0, j0: H, j1: 0, src: 0 }));
    for (let p = 0; p < W * H; p++) {
        const l = lab[p];
        if (!l) continue;
        const o = P[l - 1], i = p % W, j = (p / W) | 0;
        o.px++; if (fused.status[p] === 2) o.conf++;
        const d = fused.date[p];
        if (d && (!o.first || d < o.first)) o.first = d;
        o.sx += i; o.sy += j; o.src |= fused.source[p];
        if (i < o.i0) o.i0 = i; if (i > o.i1) o.i1 = i; if (j < o.j0) o.j0 = j; if (j > o.j1) o.j1 = j;
    }
    return {
        lab,
        list: P.map((o) => ({
            id: o.id, ha: o.px * ha, confHa: o.conf * ha, status: o.conf ? 2 : 1, first: codeToIso(o.first), source: o.src,
            lon: west + (o.sx / o.px + 0.5) * dx, lat: north - (o.sy / o.px + 0.5) * dy,
            bbox: [west + o.i0 * dx, north - (o.j1 + 1) * dy, west + (o.i1 + 1) * dx, north - o.j0 * dy],
        })).sort((a, b) => b.ha - a.ha),
    };
}

/** Área por mês (da primeira detecção): { 'AAAA-MM': { conf, prov } } em hectares. */
export function byMonth(fused, res) {
    const out = {}, ha = res * res / 1e4;
    for (let p = 0; p < fused.status.length; p++) {
        const s = fused.status[p];
        if (!s || !fused.date[p]) continue;
        const k = codeToIso(fused.date[p]).slice(0, 7);
        out[k] ??= { conf: 0, prov: 0 };
        out[k][s === 2 ? 'conf' : 'prov'] += ha;
    }
    return out;
}

// ---------- vectorização das manchas (contornos ao longo dos limites dos píxeis) ----------
/**
 * Polígonos GeoJSON das manchas: liga as arestas entre píxeis dentro/fora de cada mancha em anéis fechados.
 * O anel maior de cada mancha é o exterior; os outros são buracos. Coordenadas WGS 84.
 */
export function vectorize(lab, nPatches, grid, props = () => ({})) {
    const { width: W, height: H, west, north, dx, dy } = grid;
    const edges = new Map(); // mancha -> Map(início "x,y" -> lista de fins)
    const add = (l, x0, y0, x1, y1) => {
        if (!edges.has(l)) edges.set(l, new Map());
        const m = edges.get(l), k = `${x0},${y0}`;
        if (!m.has(k)) m.set(k, []);
        m.get(k).push([x1, y1]);
    };
    const at = (i, j) => (i < 0 || j < 0 || i >= W || j >= H ? 0 : lab[j * W + i]);
    for (let j = 0; j < H; j++) {
        for (let i = 0; i < W; i++) {
            const l = lab[j * W + i];
            if (!l) continue;
            // sentido horário em coordenadas da imagem (y para baixo) = anti-horário no mapa
            if (at(i, j - 1) !== l) add(l, i, j, i + 1, j);
            if (at(i + 1, j) !== l) add(l, i + 1, j, i + 1, j + 1);
            if (at(i, j + 1) !== l) add(l, i + 1, j + 1, i, j + 1);
            if (at(i - 1, j) !== l) add(l, i, j + 1, i, j);
        }
    }
    const toLL = ([x, y]) => [+(west + x * dx).toFixed(7), +(north - y * dy).toFixed(7)];
    const ringArea = (r) => { let a = 0; for (let k = 0; k < r.length - 1; k++) a += r[k][0] * r[k + 1][1] - r[k + 1][0] * r[k][1]; return Math.abs(a / 2); };
    const features = [];
    for (const [l, m] of edges) {
        const rings = [];
        for (const [start, ends] of m) {
            while (ends.length) {
                const ring = [start.split(',').map(Number)];
                let cur = ends.pop();
                let guard = 0;
                while (guard++ < 1e7) {
                    ring.push(cur);
                    const k = `${cur[0]},${cur[1]}`;
                    if (k === start) break;
                    const list = m.get(k);
                    if (!list?.length) break;
                    cur = list.pop();
                }
                // retira vértices colineares
                const s = ring.filter((p, k) => k === 0 || k === ring.length - 1
                    || !((ring[k - 1][0] === p[0] && p[0] === ring[k + 1][0]) || (ring[k - 1][1] === p[1] && p[1] === ring[k + 1][1])));
                if (s.length >= 4) rings.push(s);
            }
        }
        if (!rings.length) continue;
        rings.sort((a, b) => ringArea(b) - ringArea(a));
        features.push({ type: 'Feature', properties: props(l), geometry: { type: 'Polygon', coordinates: rings.map((r) => r.map(toLL)) } });
    }
    return { type: 'FeatureCollection', features };
}

// ---------- amostra para validação (boas práticas: Olofsson et al., 2014) ----------
/** Amostra aleatória estratificada: até n píxeis com alerta e n de floresta sem alerta. */
export function validationSample(fused, forest, grid, n = 50, seed = 7) {
    let t = seed >>> 0;
    const r = () => { t += 0x6d2b79f5; let x = Math.imul(t ^ (t >>> 15), 1 | t); x ^= x + Math.imul(x ^ (x >>> 7), 61 | x); return ((x ^ (x >>> 14)) >>> 0) / 4294967296; };
    const A = [], B = [];
    for (let p = 0; p < fused.status.length; p++) { if (fused.status[p]) A.push(p); else if (forest[p]) B.push(p); }
    const pick = (arr) => { const out = []; for (let k = 0; k < Math.min(n, arr.length); k++) { const q = k + Math.floor(r() * (arr.length - k)); [arr[k], arr[q]] = [arr[q], arr[k]]; out.push(arr[k]); } return out; };
    const { width: W, west, north, dx, dy, res } = grid;
    const row = (p, stratum) => ({
        stratum, lon: +(west + (p % W + 0.5) * dx).toFixed(6), lat: +(north - (((p / W) | 0) + 0.5) * dy).toFixed(6),
        status: fused.status[p], date: codeToIso(fused.date[p]),
    });
    return { points: [...pick(A).map((p) => row(p, 'alert')), ...pick(B).map((p) => row(p, 'forest_no_alert'))], strataHa: { alert: A.length * res * res / 1e4, forest_no_alert: B.length * res * res / 1e4 } };
}

// ---------- GeoTIFF de várias bandas (Uint16, EPSG:4326), uma faixa por banda ----------
export function geotiffBands(grid, bands, names, description = '', nodata = null) {
    const { width, height, dx, dy, west, north } = grid;
    const nb = bands.length, plane = width * height * 2;
    const ascii = (s) => new TextEncoder().encode(s.replace(/[^\x20-\x7e]/g, '?') + '\0');
    const desc = ascii(description);
    const meta = ascii(`<GDALMetadata>${names.map((nm, k) => `<Item name="DESCRIPTION" sample="${k}" role="description">${nm}</Item>`).join('')}</GDALMetadata>`);
    const nd = nodata === null ? null : ascii(String(nodata));
    const u32 = (arr) => new Uint8Array(new Uint32Array(arr).buffer);
    const u16 = (arr) => new Uint8Array(new Uint16Array(arr).buffer);
    const scale = new Float64Array([dx, dy, 0]), tie = new Float64Array([0, 0, 0, west, north, 0]);
    const geokeys = new Uint16Array([1, 1, 0, 3, 1024, 0, 1, 2, 1025, 0, 1, 1, 2048, 0, 1, 4326]);
    const tags = [
        [256, 4, 1, width], [257, 4, 1, height], [258, 3, nb, nb > 2 ? u16(Array(nb).fill(16)) : (nb === 2 ? u16([16, 16]) : 16)], [259, 3, 1, 1], [262, 3, 1, 1],
        [270, 2, desc.length, desc], [273, 4, nb, null], [277, 3, 1, nb], [278, 4, 1, height], [279, 4, nb, nb > 1 ? u32(Array(nb).fill(plane)) : plane],
        [284, 3, 1, 2], [339, 3, nb, nb > 2 ? u16(Array(nb).fill(1)) : (nb === 2 ? u16([1, 1]) : 1)],
        [33550, 12, 3, new Uint8Array(scale.buffer)], [33922, 12, 6, new Uint8Array(tie.buffer)],
        [34735, 3, geokeys.length, new Uint8Array(geokeys.buffer)], [42112, 2, meta.length, meta],
        ...(nd ? [[42113, 2, nd.length, nd]] : []),
    ];
    const ifdSize = 2 + tags.length * 12 + 4;
    let off = 8 + ifdSize;
    const extras = [];
    const offTag = tags.find((t) => t[0] === 273);
    const offsetsBytes = nb > 1 ? 4 * nb : 0;
    let offsetsPos = 0;
    if (offsetsBytes) { offsetsPos = off; off += offsetsBytes; }
    for (const t of tags) {
        if (t[3] instanceof Uint8Array && t[3].length > 4) { t.push(off); extras.push([off, t[3]]); off += t[3].length + (t[3].length % 2); }
    }
    const dataStart = off;
    const offsets = Array.from({ length: nb }, (_, k) => dataStart + k * plane);
    if (nb > 1) { offTag[3] = null; offTag.push(offsetsPos); extras.push([offsetsPos, u32(offsets)]); } else offTag[3] = offsets[0];
    const buf = new ArrayBuffer(dataStart + nb * plane);
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
    bands.forEach((band, k) => {
        const view = new DataView(buf, dataStart + k * plane, plane);
        for (let p = 0; p < width * height; p++) view.setUint16(p * 2, band[p], true);
    });
    return buf;
}

// ---------- ZIP sem compressão (os ficheiros já são pequenos ou comprimidos pelo utilizador) ----------
const CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
export function crc32(u8) { let c = 0xffffffff; for (let k = 0; k < u8.length; k++) c = CRC[(c ^ u8[k]) & 255] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; }

export function zip(files) {
    const enc = new TextEncoder();
    const parts = [], central = [];
    let off = 0;
    for (const { name, data } of files) {
        const body = typeof data === 'string' ? enc.encode(data) : new Uint8Array(data);
        const nm = enc.encode(name), crc = crc32(body);
        const h = new DataView(new ArrayBuffer(30));
        h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true); h.setUint16(8, 0, true);
        h.setUint32(14, crc, true); h.setUint32(18, body.length, true); h.setUint32(22, body.length, true); h.setUint16(26, nm.length, true);
        parts.push(new Uint8Array(h.buffer), nm, body);
        const c = new DataView(new ArrayBuffer(46));
        c.setUint32(0, 0x02014b50, true); c.setUint16(4, 20, true); c.setUint16(6, 20, true); c.setUint16(8, 0x0800, true);
        c.setUint32(16, crc, true); c.setUint32(20, body.length, true); c.setUint32(24, body.length, true); c.setUint16(28, nm.length, true); c.setUint32(42, off, true);
        central.push(new Uint8Array(c.buffer), nm);
        off += 30 + nm.length + body.length;
    }
    const csize = central.reduce((a, b) => a + b.length, 0);
    const e = new DataView(new ArrayBuffer(22));
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true); e.setUint32(12, csize, true); e.setUint32(16, off, true);
    return new Blob([...parts, ...central, new Uint8Array(e.buffer)], { type: 'application/zip' });
}

// ---------- Hansen (Global Forest Change v1.13): blocos de 10° ----------
/** Nomes dos blocos 10°x10° que cobrem o rectângulo (canto superior esquerdo: 10S_030E...). */
export function hansenTiles(bbox) {
    const [w, s, e, n] = bbox;
    const out = [];
    for (let top = Math.ceil(n / 10) * 10; top > s; top -= 10) {
        for (let left = Math.floor(w / 10) * 10; left < e; left += 10) {
            const ns = top >= 0 ? `${String(top).padStart(2, '0')}N` : `${String(-top).padStart(2, '0')}S`;
            const ew = left >= 0 ? `${String(left).padStart(3, '0')}E` : `${String(-left).padStart(3, '0')}W`;
            out.push({ name: `${ns}_${ew}`, top, left });
        }
    }
    return out;
}
