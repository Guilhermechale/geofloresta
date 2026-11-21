/*
 * Trabalhador da classificação de uso e cobertura do solo (Random Forest), tudo no navegador e com dados abertos:
 *  - imagens: Sentinel-2 L2A em COG (AWS Open Data), procuradas no catálogo STAC Earth Search (Element 84);
 *  - referência para as amostras: mapa anual Sentinel-2 10 m Land Use/Land Cover (Impact Observatory, Microsoft, Esri; CC BY 4.0);
 *  - relevo: Terrain Tiles (AWS Open Data), as mesmas da ferramenta de relevo do site.
 */
import { fromUrl, fromArrayBuffer } from 'geotiff';
import {
    planGrid, toUtm, medianStack, indices, slope, binEdges, binOf, sample, split, trainForest, predict, accuracy, areas,
    geotiffClasses, polygonMask, FEATURES, rng,
} from './lulc-core.js';
import { TILE_URL, planTiles, decodeTerrarium, buildGrid } from './dem-core.js';

export const STAC = 'https://earth-search.aws.element84.com/v1/search';
export const LULC = 'https://ic.imagery1.arcgis.com/arcgis/rest/services/Sentinel2_10m_LandCover/ImageServer/exportImage';
const BANDS = { B2: 'blue', B3: 'green', B4: 'red', B8: 'nir', B11: 'swir16', B12: 'swir22' };
const BAD_SCL = new Set([0, 1, 3, 8, 9, 10, 11]);   // sem dados, saturado, sombra de nuvem, nuvens, cirros, neve
const PER_TILE = 3, MAX_ITEMS = 8;

let state = null;
const progress = (step, frac = 0, extra = {}) => postMessage({ progress: step, frac, ...extra });

// ---------- catálogo ----------
async function findScenes(bbox, start, end, maxCloud = 30) {
    const body = {
        collections: ['sentinel-2-l2a'], bbox, datetime: `${start}T00:00:00Z/${end}T23:59:59Z`, limit: 100,
        query: { 'eo:cloud_cover': { lt: maxCloud } }, sortby: [{ field: 'properties.eo:cloud_cover', direction: 'asc' }],
    };
    const r = await fetch(STAC, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    if (!r.ok) throw new Error(`STAC ${r.status}`);
    const need = ['scl', ...Object.values(BANDS)];
    const items = ((await r.json()).features || []).filter((it) => it.properties?.['proj:epsg'] && need.every((k) => it.assets?.[k]?.href));
    const byTile = new Map();
    for (const it of items) {
        const tile = it.properties['s2:mgrs_tile'] || it.properties['grid:code'] || it.id.split('_')[1];
        if (!byTile.has(tile)) byTile.set(tile, []);
        if (byTile.get(tile).length < PER_TILE) byTile.get(tile).push(it);
    }
    return [...byTile.values()].flat().slice(0, MAX_ITEMS);
}

// ---------- mapeamento píxel -> UTM (grelha grossa interpolada: rápido e preciso) ----------
function utmMapper(grid, epsg, step = 16) {
    const { width: W, height: H, dx, dy, west, north } = grid;
    const cw = Math.ceil(W / step) + 1, ch = Math.ceil(H / step) + 1;
    const gx = new Float64Array(cw * ch), gy = new Float64Array(cw * ch);
    for (let b = 0; b < ch; b++) {
        for (let a = 0; a < cw; a++) {
            const [x, y] = toUtm(west + (a * step + 0.5) * dx, north - (b * step + 0.5) * dy, epsg);
            gx[b * cw + a] = x; gy[b * cw + a] = y;
        }
    }
    return (i, j, out) => {
        const a = Math.min(cw - 2, Math.floor(i / step)), b = Math.min(ch - 2, Math.floor(j / step));
        const fa = i / step - a, fb = j / step - b;
        const k = b * cw + a;
        out[0] = (gx[k] * (1 - fa) + gx[k + 1] * fa) * (1 - fb) + (gx[k + cw] * (1 - fa) + gx[k + cw + 1] * fa) * fb;
        out[1] = (gy[k] * (1 - fa) + gy[k + 1] * fa) * (1 - fb) + (gy[k + cw] * (1 - fa) + gy[k + cw + 1] * fa) * fb;
    };
}

/** Lê a banda de um COG para a grelha (vizinho mais próximo), usando a visão geral com resolução mais adequada. */
async function readToGrid(href, grid, map, nodataTo = NaN) {
    const tiff = await fromUrl(href, { allowFullFile: false });
    const n = await tiff.getImageCount();
    let img = await tiff.getImage(0);
    const base = img;
    for (let k = 1; k < n; k++) {
        const cand = await tiff.getImage(k);
        const res = base.getWidth() / cand.getWidth() * base.getResolution()[0];
        if (res <= grid.res + 0.1) img = cand; else break;
    }
    const [ox, oy] = base.getOrigin();
    const [bx] = base.getResolution();
    const f = base.getWidth() / img.getWidth();
    const rx = bx * f, ry = -base.getResolution()[1] * f;
    const IW = img.getWidth(), IH = img.getHeight();
    // janela de leitura: os cantos e os bordos da grelha
    const { width: W, height: H } = grid;
    const p = [0, 0];
    let c0 = Infinity, c1 = -Infinity, r0 = Infinity, r1 = -Infinity;
    for (const [i, j] of [[0, 0], [W - 1, 0], [0, H - 1], [W - 1, H - 1], [W >> 1, 0], [W >> 1, H - 1], [0, H >> 1], [W - 1, H >> 1]]) {
        map(i, j, p);
        const c = (p[0] - ox) / rx, r = (oy - p[1]) / ry;
        c0 = Math.min(c0, c); c1 = Math.max(c1, c); r0 = Math.min(r0, r); r1 = Math.max(r1, r);
    }
    c0 = Math.max(0, Math.floor(c0) - 1); r0 = Math.max(0, Math.floor(r0) - 1);
    c1 = Math.min(IW, Math.ceil(c1) + 2); r1 = Math.min(IH, Math.ceil(r1) + 2);
    const out = new Float32Array(W * H).fill(nodataTo);
    if (c1 <= c0 || r1 <= r0) return out;
    const [data] = await img.readRasters({ window: [c0, r0, c1, r1] });
    const ww = c1 - c0;
    for (let j = 0; j < H; j++) {
        for (let i = 0; i < W; i++) {
            map(i, j, p);
            const c = Math.floor((p[0] - ox) / rx) - c0, r = Math.floor((oy - p[1]) / ry) - r0;
            if (c < 0 || r < 0 || c >= ww || r >= r1 - r0) continue;
            const v = data[r * ww + c];
            if (v !== 0) out[j * W + i] = v;
        }
    }
    return out;
}

// ---------- relevo (Terrain Tiles) ----------
async function elevation(grid) {
    const bbox = [grid.west, grid.south, grid.east, grid.north];
    const plan = planTiles(bbox, 64);
    const cols = plan.x1 - plan.x0 + 1, rows = plan.y1 - plan.y0 + 1, mw = cols * 256, mh = rows * 256;
    const mosaic = new Float32Array(mw * mh);
    const jobs = [];
    for (let ty = plan.y0; ty <= plan.y1; ty++) for (let tx = plan.x0; tx <= plan.x1; tx++) jobs.push([tx, ty]);
    const run = async () => {
        while (jobs.length) {
            const [tx, ty] = jobs.shift();
            const r = await fetch(TILE_URL.replace('{z}', plan.z).replace('{x}', tx).replace('{y}', ty));
            const bmp = await createImageBitmap(await r.blob(), { premultiplyAlpha: 'none', colorSpaceConversion: 'none' });
            const c = new OffscreenCanvas(256, 256), ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(bmp, 0, 0);
            const px = ctx.getImageData(0, 0, 256, 256).data;
            const ox = (tx - plan.x0) * 256, oy = (ty - plan.y0) * 256;
            for (let y = 0; y < 256; y++) for (let x = 0; x < 256; x++) { const k = (y * 256 + x) * 4; mosaic[(oy + y) * mw + ox + x] = decodeTerrarium(px[k], px[k + 1], px[k + 2]); }
        }
    };
    await Promise.all(Array.from({ length: Math.min(6, plan.count) }, run));
    const dem = buildGrid(bbox, plan, mosaic, mw, mh);
    const { width: W, height: H, dx, dy, west, north } = grid;
    const out = new Float32Array(W * H);
    for (let j = 0; j < H; j++) {
        const jj = Math.min(dem.height - 1, Math.max(0, Math.floor((north - (j + 0.5) * dy - dem.north) / -dem.step)));
        for (let i = 0; i < W; i++) {
            const ii = Math.min(dem.width - 1, Math.max(0, Math.floor((west + (i + 0.5) * dx - dem.west) / dem.step)));
            out[j * W + i] = dem.data[jj * dem.width + ii];
        }
    }
    return out;
}

// ---------- referência (mapa LULC Sentinel-2) ----------
async function reference(grid, year) {
    const { width: W, height: H, west, south, east, north } = grid;
    const px = Math.min(grid.dx, grid.dy);
    let sw = Math.round((east - west) / px), sh = Math.round((north - south) / px);
    const k = Math.max(1, sw / 4000, sh / 4000);
    sw = Math.round(sw / k); sh = Math.round(sh / k);
    const t0 = Date.UTC(year, 0, 1), t1 = Date.UTC(year, 11, 31, 23, 59, 59);
    const q = new URLSearchParams({
        bbox: `${west},${south},${east},${north}`, bboxSR: '4326', imageSR: '4326', size: `${sw},${sh}`, format: 'tiff', pixelType: 'U8',
        interpolation: 'RSP_NearestNeighbor', renderingRule: JSON.stringify({ rasterFunction: 'None' }), time: `${t0},${t1}`, f: 'image',
    });
    const r = await fetch(`${LULC}?${q}`);
    if (!r.ok) throw new Error(`LULC ${r.status}`);
    const tiff = await fromArrayBuffer(await r.arrayBuffer());
    const img = await tiff.getImage();
    const [data] = await img.readRasters();
    // o serviço ajusta a extensão para ter píxeis quadrados: usar a georreferência devolvida, não a pedida
    const [ox, oy] = img.getOrigin(), [rx, ry] = img.getResolution();
    const IW = img.getWidth(), IH = img.getHeight();
    const out = new Uint8Array(W * H);
    for (let j = 0; j < H; j++) {
        const r0 = Math.floor((north - (j + 0.5) * grid.dy - oy) / ry);
        if (r0 < 0 || r0 >= IH) continue;
        for (let i = 0; i < W; i++) {
            const c0 = Math.floor((west + (i + 0.5) * grid.dx - ox) / rx);
            if (c0 >= 0 && c0 < IW) out[j * W + i] = data[r0 * IW + c0];
        }
    }
    return out;
}

// ---------- classificação ----------
async function run({ bbox, geometry, start, end, trees = 100, seed = 42 }) {
    const t0 = performance.now();
    const grid = planGrid(bbox);
    if (!grid) return { error: 'too_big' };
    const N = grid.width * grid.height;
    const inside = polygonMask(grid, geometry);
    const year = Math.min(2025, Math.max(2017, Number(end.slice(0, 4))));

    progress('search');
    const scenes = await findScenes(bbox, start, end);
    if (!scenes.length) return { error: 'no_images' };

    // máscara de nuvens por cena (SCL) e bandas, cena a cena
    const masks = [], maps = [];
    for (const [k, it] of scenes.entries()) {
        progress('clouds', k / scenes.length, { scenes: scenes.length });
        const map = utmMapper(grid, it.properties['proj:epsg']);
        maps.push(map);
        const scl = await readToGrid(it.assets.scl.href, grid, map, 0);
        const m = new Uint8Array(N);
        for (let p = 0; p < N; p++) m[p] = scl[p] && !BAD_SCL.has(scl[p]) ? 1 : 0;
        masks.push(m);
    }
    const bands = {};
    const names = Object.keys(BANDS);
    for (const [b, name] of names.entries()) {
        const layers = [];
        for (const [k, it] of scenes.entries()) {
            progress('bands', (b * scenes.length + k) / (names.length * scenes.length), { scenes: scenes.length });
            const L = await readToGrid(it.assets[BANDS[name]].href, grid, maps[k]);
            const m = masks[k];
            for (let p = 0; p < N; p++) L[p] = m[p] ? L[p] / 10000 : NaN;
            layers.push(L);
        }
        bands[name] = medianStack(layers, N);
    }
    progress('terrain');
    const elev = await elevation(grid);
    progress('reference');
    const ref = await reference(grid, year);

    progress('features');
    const idx = indices(bands);
    const feats = { ...bands, ...idx, elevation: elev, slope: slope(elev, grid) };
    const nf = FEATURES.length;
    const valid = new Uint8Array(N);
    for (let p = 0; p < N; p++) {
        let ok = 1;
        for (const f of FEATURES) { const v = feats[f][p]; if (!(v === v) || !Number.isFinite(v)) { ok = 0; break; } }
        valid[p] = ok && inside[p];
    }
    const nValid = valid.reduce((a, b) => a + b, 0);
    if (nValid < 1000) return { error: 'no_valid' };

    // limiares de quantização a partir de 50 000 píxeis válidos
    const r = rng(seed);
    const probe = [];
    for (let k = 0; k < 50000; k++) { const p = Math.floor(r() * N); if (valid[p]) probe.push(p); }
    const edges = FEATURES.map((f) => binEdges(probe.map((p) => feats[f][p])));
    const quant = (p, out, o) => { for (let f = 0; f < nf; f++) out[o + f] = binOf(feats[FEATURES[f]][p], edges[f]); };

    progress('samples');
    const s = sample(ref, valid, grid, { seed });
    if (s.classes.length < 2) return { error: 'few_classes' };
    const tr = split(s.idx.length, 0.7, seed + 1);
    const nT = tr.reduce((a, b) => a + b, 0), nV = s.idx.length - nT;
    const XT = new Uint8Array(nT * nf), yT = new Uint8Array(nT), XV = new Uint8Array(nV * nf), yV = new Uint8Array(nV);
    let a = 0, v = 0;
    for (let k = 0; k < s.idx.length; k++) {
        if (tr[k]) { quant(s.idx[k], XT, a * nf); yT[a++] = s.label[k]; } else { quant(s.idx[k], XV, v * nf); yV[v++] = s.label[k]; }
    }

    progress('train', 0, { trees });
    const C = s.classes.length;
    const model = trainForest(XT, yT, nf, C, { trees, seed });
    const acc = accuracy(yV, predict(model, XV, nV), C);

    progress('classify');
    const classMap = new Uint8Array(N);
    const CH = 65536, buf = new Uint8Array(CH * nf), ids = new Int32Array(CH);
    let m = 0;
    const flush = () => { const pr = predict(model, buf, m); for (let k = 0; k < m; k++) classMap[ids[k]] = s.classes[pr[k]]; m = 0; };
    for (let p = 0; p < N; p++) {
        if (!valid[p]) continue;
        quant(p, buf, m * nf); ids[m++] = p;
        if (m === CH) { flush(); progress('classify', p / N); }
    }
    if (m) flush();

    state = { grid, classMap };
    const seconds = (performance.now() - t0) / 1000;
    return {
        grid, classes: s.classes, skipped: s.skipped, accuracy: acc, importance: Object.fromEntries(FEATURES.map((f, k) => [f, model.importance[k]])),
        areas: areas(classMap, s.classes, grid.res), samples: { train: nT, validation: nV }, scenes: scenes.map((it) => ({ id: it.id, date: it.properties.datetime.slice(0, 10), cloud: it.properties['eo:cloud_cover'] })),
        year, trees, seconds, classMap: classMap.slice(),
    };
}

onmessage = async ({ data: msg }) => {
    try {
        if (msg.cmd === 'run') {
            const res = await run(msg);
            const transfer = res.classMap ? [res.classMap.buffer] : [];
            postMessage({ id: msg.id, ...res }, transfer);
        } else if (msg.cmd === 'tiff') {
            const buf = geotiffClasses(state.grid, state.classMap, msg.description);
            postMessage({ id: msg.id, buffer: buf }, [buf]);
        }
    } catch (e) {
        postMessage({ id: msg.id, error: 'failed', detail: String(e?.message || e) });
    }
};
