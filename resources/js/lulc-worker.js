/*
 * Trabalhador da classificação de uso e cobertura do solo (Random Forest), tudo no navegador e com dados abertos:
 *  - imagens: Sentinel-2 L2A em COG (AWS Open Data), procuradas no catálogo STAC Earth Search (Element 84);
 *  - referência para as amostras: mapa anual Sentinel-2 10 m Land Use/Land Cover (Impact Observatory, Microsoft, Esri; CC BY 4.0);
 *  - relevo: Terrain Tiles (AWS Open Data), as mesmas da ferramenta de relevo do site.
 */
import { fromArrayBuffer } from 'geotiff';
import { utmMapper, readToGrid } from './cog-read.js';
import {
    allowSparseTiles,
    planGrid, medianStack, slotStack, slotAdd, slotMedians, indices, slope, binEdges, binOf, sample, split, trainForest, predict, accuracy, areas,
    geotiffClasses, polygonMask, FEATURES, rng,
} from './lulc-core.js';
import { TILE_URL, planTiles, decodeTerrarium, buildGrid } from './dem-core.js';

export const STAC = 'https://earth-search.aws.element84.com/v1/search';
export const LULC = 'https://ic.imagery1.arcgis.com/arcgis/rest/services/Sentinel2_10m_LandCover/ImageServer/exportImage';
const BANDS = { B2: 'blue', B3: 'green', B4: 'red', B8: 'nir', B11: 'swir16', B12: 'swir22' };
const BAD_SCL = new Set([0, 1, 3, 8, 9, 10, 11]);   // sem dados, saturado, sombra de nuvem, nuvens, cirros, neve
// Imagens por quadrícula MGRS (2 quando a área tem muitas quadrículas, para não demorar demasiado) e teto total
const PER_TILE = 3, PER_TILE_MANY = 2, MANY_TILES = 12, MAX_ITEMS = 72, MAX_PAGES = 8;

let state = null;
const progress = (step, frac = 0, extra = {}) => postMessage({ progress: step, frac, ...extra });

// ---------- catálogo ----------
async function findScenes(area, start, end, maxCloud = 30, exclude = null) {
    let body = {
        collections: ['sentinel-2-l2a'], ...(Array.isArray(area) ? { bbox: area } : { intersects: area }), datetime: `${start}T00:00:00Z/${end}T23:59:59Z`, limit: 100,
        query: { 'eo:cloud_cover': { lt: maxCloud } }, sortby: [{ field: 'properties.eo:cloud_cover', direction: 'asc' }],
    };
    const need = ['scl', ...Object.values(BANDS)];
    const byTile = new Map();
    // Várias páginas do catálogo: numa província, as 100 imagens com menos nuvens podem não cobrir todas as quadrículas
    for (let page = 0; page < MAX_PAGES && body; page++) {
        const r = await fetch(STAC, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (!r.ok) throw new Error(`STAC ${r.status}`);
        const json = await r.json();
        for (const it of json.features || []) {
            if (!it.properties?.['proj:epsg'] || !need.every((k) => it.assets?.[k]?.href) || exclude?.has(it.id)) continue;
            const tile = it.properties['s2:mgrs_tile'] || it.properties['grid:code'] || it.id.split('_')[1];
            if (!byTile.has(tile)) byTile.set(tile, []);
            byTile.get(tile).push(it);
        }
        const next = (json.links || []).find((l) => l.rel === 'next');
        const enough = [...byTile.values()].every((l) => l.length >= PER_TILE * 3);
        body = next?.body && !enough && (json.features || []).length === 100 ? { ...body, ...next.body } : null;
    }
    const perTile = byTile.size > MANY_TILES ? PER_TILE_MANY : PER_TILE;
    // Por quadrícula: primeiro as imagens que cobrem a quadrícula inteira (as da borda da passagem do satélite
    // só cobrem parte), depois as com menos nuvens.
    const nodata = (it) => Number(it.properties['s2:nodata_pixel_percentage'] ?? 0);
    const quality = (a, b) => (nodata(a) > 40) - (nodata(b) > 40) || a.properties['eo:cloud_cover'] - b.properties['eo:cloud_cover'] || nodata(a) - nodata(b);
    // Quadrículas na borda da faixa do satélite: cada passagem (órbita relativa) só cobre parte da quadrícula.
    // Alterna entre passagens (a melhor imagem de cada, depois a segunda...) para a quadrícula ficar coberta inteira.
    const orbit = (it) => it.properties['sat:relative_orbit'] ?? it.properties['s2:datatake_id']?.split('_')?.[3] ?? 0;
    const picked = [...byTile.values()].map((list) => {
        list.sort(quality);
        if (nodata(list[0]) <= 5) return list.slice(0, perTile); // há imagens completas: as melhores bastam
        const groups = new Map();
        for (const it of list) {
            const o = orbit(it);
            if (!groups.has(o)) groups.set(o, []);
            groups.get(o).push(it);
        }
        const ordered = [...groups.values()];
        const out = [];
        for (let k = 0; out.length < list.length; k++) for (const g of ordered) if (g[k]) out.push(g[k]);
        return out.slice(0, perTile);
    });
    // Intercala as quadrículas (1.ª imagem de cada, depois a 2.ª...) para o teto nunca deixar uma quadrícula sem imagens.
    // Na 2.ª ronda em diante vêm primeiro as quadrículas cuja 1.ª imagem está incompleta (borda da faixa do satélite).
    const partialFirst = [...picked].sort((a, b) => (nodata(b[0]) > 5) - (nodata(a[0]) > 5));
    const out = [];
    for (let k = 0; k < perTile; k++) for (const list of (k === 0 ? picked : partialFirst)) if (list[k]) out.push(list[k]);
    return out.slice(0, MAX_ITEMS);
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
    const img = allowSparseTiles(await tiff.getImage());
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

    // Imagem a imagem: máscara de nuvens (SCL) e as 6 bandas; os valores válidos entram na mediana em fluxo
    // e a imagem é descartada logo (memória limitada mesmo numa província).
    const names = Object.keys(BANDS);
    const stack = slotStack(N, names.length, 4);
    let used = 0;
    const seen = new Set();
    const addScene = async (it) => {
        seen.add(it.id);
        const map = utmMapper(grid, it.properties['proj:epsg']);
        try {
            const scl = await readToGrid(it.assets.scl.href, grid, map, 0);
            const ok = new Uint8Array(N);
            let any = false;
            for (let p = 0; p < N; p++) if (inside[p] && stack.count[p] < stack.k && scl[p] && !BAD_SCL.has(scl[p])) { ok[p] = 1; any = true; }
            if (!any) return; // nada de novo (nuvens, fora do polígono ou píxeis já completos): não lê as bandas
            const layers = [];
            for (const name of names) layers.push(await readToGrid(it.assets[BANDS[name]].href, grid, map));
            slotAdd(stack, layers, ok);
            used++;
        } catch {
            // imagem que não foi possível ler (mesmo depois de repetir): segue-se com as outras
        }
    };
    for (const [k, it] of scenes.entries()) {
        progress('bands', k / scenes.length, { scenes: scenes.length });
        await addScene(it);
    }
    // Zonas que ficaram sem dados (quadrículas que o catálogo não devolveu, nuvens persistentes, bordas da faixa
    // do satélite): a área é dividida em 10 x 10 células e procura-se de novo só nas células com muitas falhas.
    const CELLS = 10;
    for (let round = 0; round < 3; round++) {
        const cw = Math.ceil(grid.width / CELLS), chh = Math.ceil(grid.height / CELLS);
        const inC = new Int32Array(CELLS * CELLS), missC = new Int32Array(CELLS * CELLS), sx = new Float64Array(CELLS * CELLS), sy = new Float64Array(CELLS * CELLS);
        for (let p = 0; p < N; p++) {
            if (!inside[p]) continue;
            const i = p % grid.width, j = (p / grid.width) | 0;
            const c = Math.min(CELLS - 1, (j / chh) | 0) * CELLS + Math.min(CELLS - 1, (i / cw) | 0);
            inC[c]++;
            if (stack.count[p]) continue;
            missC[c]++; sx[c] += i; sy[c] += j;
        }
        const cells = [...inC.keys()].filter((c) => missC[c] >= 100 && missC[c] / inC[c] > 0.01).sort((a, b) => missC[b] - missC[a]).slice(0, 8);
        if (!cells.length) break;
        const extra = [];
        for (const c of cells) {
            // ponto central das falhas desta célula: o catálogo só devolve imagens cujo contorno de dados o cobre
            const pt = { type: 'Point', coordinates: [grid.west + (sx[c] / missC[c] + 0.5) * grid.dx, grid.north - (sy[c] / missC[c] + 0.5) * grid.dy] };
            try {
                const found = (await findScenes(pt, start, end, round ? 60 : 30, seen)).filter((it) => !extra.some((x) => x.id === it.id));
                extra.push(...found.slice(0, 3));
            } catch { /* catálogo indisponível: segue-se sem esta célula */ }
        }
        if (!extra.length) break;
        for (const [k, it] of extra.entries()) {
            progress('gaps', k / extra.length, { scenes: extra.length });
            await addScene(it);
        }
        scenes.push(...extra);
    }
    if (!used) throw new Error('no scene could be read');
    const medians = slotMedians(stack);
    const bands = Object.fromEntries(names.map((name, b) => [name, medians[b]]));
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
