/*
 * Trabalhador do Monitor de desmatamento (GF-DIST), tudo no navegador e com dados abertos:
 *  - óptico: Sentinel-2 L2A em COG (AWS Open Data), catálogo STAC Earth Search (Element 84);
 *  - radar: Sentinel-1 RTC (Microsoft Planetary Computer, CC BY 4.0), com chave de acesso gratuita (SAS);
 *  - comparação: perda de cobertura arbórea Hansen/UMD GFC v1.13 (2001-2025), lida através do nosso servidor.
 * Método em dist-core.js.
 */
import { fromUrl } from 'geotiff';
import { polygonMask, allowSparseTiles } from './lulc-core.js';
import { utmMapper, readToGrid } from './cog-read.js';
import * as C from './dist-core.js';

const { epsgOf } = C;

export const S2_STAC = 'https://earth-search.aws.element84.com/v1/search';
export const S1_STAC = 'https://planetarycomputer.microsoft.com/api/stac/v1/search';
export const S1_TOKEN = 'https://planetarycomputer.microsoft.com/api/sas/v1/token/sentinel-1-rtc';

let state = null;
const progress = (step, frac = 0, extra = {}) => postMessage({ progress: step, frac, ...extra });
const DAY = 864e5;
const iso = (t) => new Date(t).toISOString().slice(0, 10);
const monthOf = (d) => Number(d.slice(5, 7)) - 1;


// ---------- catálogos ----------
async function stacAll(url, body, maxPages = 12) {
    const out = [];
    let req = { method: 'POST', url, body };
    for (let page = 0; page < maxPages && req; page++) {
        const r = await fetch(req.url, req.method === 'POST'
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(req.body) }
            : {});
        if (!r.ok) throw new Error(`STAC ${r.status}`);
        const j = await r.json();
        out.push(...(j.features || []));
        const next = (j.links || []).find((l) => l.rel === 'next');
        // página seguinte: o Earth Search e o Planetary Computer devolvem o corpo do pedido seguinte (POST)
        req = !next ? null : next.body ? { method: 'POST', url: next.href || url, body: { ...req.body, ...next.body } } : { method: 'GET', url: next.href };
        if ((j.features || []).length === 0) break;
    }
    return out;
}

/**
 * Escolha das imagens: linha de base (3 anos antes do período, só nos meses da época) com as k melhores DATAS
 * por mês e ano (todas as quadrículas dessa data, para cobrir a área inteira sem ler duas vezes a mesma passagem);
 * monitorização: as melhores datas do período (teto), por ordem cronológica.
 */
function choose(items, { start, end, months, k, cap, cloud }) {
    const dates = new Map();
    for (const it of items) {
        const d = it.properties.datetime.slice(0, 10);
        if (d > end) continue;
        if (!dates.has(d)) dates.set(d, []);
        dates.get(d).push(it);
    }
    const score = (list) => (cloud ? list.reduce((a, it) => a + cloud(it), 0) / list.length : 0);
    const best = (groups, n) => [...groups.values()].flatMap((l) => l.sort((x, y) => score(x[1]) - score(y[1])).slice(0, n));
    const base = new Map(), year = new Map(), mon = [];
    const yearStart = iso(Date.parse(start) - 365 * DAY);
    for (const [d, list] of dates) {
        if (d >= start) { mon.push([d, list]); continue; }
        if (d >= yearStart) { const g = d.slice(0, 7); if (!year.has(g)) year.set(g, []); year.get(g).push([d, list]); }
        if (!months.includes(monthOf(d))) continue;
        const g = d.slice(0, 7);
        if (!base.has(g)) base.set(g, []);
        base.get(g).push([d, list]);
    }
    // ano anterior: a melhor data de cada mês (floresta de referência equilibrada entre épocas)
    const annual = best(year, 1);
    const annualSet = new Set(annual.map(([d]) => d));
    const b = new Map([...best(base, k), ...annual].map((x) => [x[0], x]));
    const byD = (a, c) => (a[0] < c[0] ? -1 : 1);
    return { base: [...b.values()].sort(byD), annual: annualSet, mon: mon.sort((x, y) => score(x[1]) - score(y[1])).slice(0, cap).sort(byD) };
}

/** Lê várias datas em paralelo (n de cada vez) e entrega os resultados pela ordem original. */
async function* inOrder(list, read, n = 3) {
    const results = new Array(list.length);
    let next = 0;
    const start = () => { if (next < list.length) { const i = next++; results[i] = read(list[i]); } };
    for (let i = 0; i < n; i++) start();
    for (let i = 0; i < list.length; i++) {
        const r = await results[i];
        results[i] = null;
        start();
        yield [list[i], r];
    }
}


// ---------- óptico (Sentinel-2) ----------
async function readOpticalDate(scenes, grid, inside) {
    const N = grid.width * grid.height;
    const nbr = new Float32Array(N).fill(NaN), s16 = new Float32Array(N).fill(NaN);
    const buf = Math.max(1, Math.round(C.PARAMS.optical.cloudBufferM / grid.res));
    let any = false;
    for (const it of scenes) {
        const a = it.assets, map = utmMapper(grid, epsgOf(it, a.nir));
        try {
            const scl = await readToGrid(a.scl.href, grid, map, 0);
            let cand = false;
            for (let p = 0; p < N; p++) if (inside[p] && nbr[p] !== nbr[p] && (scl[p] === 4 || scl[p] === 5)) { cand = true; break; }
            if (!cand) continue;
            // valores já harmonizados no Earth Search; se não, retira-se o desvio de +1000 (baseline >= 04.00)
            const off = it.properties['earthsearch:boa_offset_applied'] === false && Number(it.properties['s2:processing_baseline']) >= 4 ? 1000 : 0;
            const [blue, nir, sw16, sw22] = await Promise.all(['blue', 'nir', 'swir16', 'swir22'].map((k) => readToGrid(a[k].href, grid, map)));
            const ok = C.clearMask(scl, off ? blue.map((v) => v - off) : blue, grid.width, grid.height, buf);
            for (let p = 0; p < N; p++) {
                if (!ok[p] || !inside[p] || nbr[p] === nbr[p]) continue;
                const n8 = nir[p] - off, s2 = sw22[p] - off;
                if (!(n8 > 0 && s2 > 0)) continue;
                nbr[p] = (n8 - s2) / (n8 + s2); s16[p] = sw16[p] - off; any = true;
            }
        } catch { /* imagem ilegível (mesmo depois de repetir): segue-se */ }
    }
    return any ? { nbr, s16 } : null;
}

// ---------- radar (Sentinel-1 RTC) ----------
const token = { value: null, at: 0 };
async function s1Token() {
    if (token.value && Date.now() - token.at < 30 * 60e3) return token.value;
    const r = await fetch(S1_TOKEN);
    if (!r.ok) throw new Error(`token ${r.status}`);
    token.value = (await r.json()).token; token.at = Date.now();
    return token.value;
}

async function readRadarDate(scenes, grid, inside) {
    const N = grid.width * grid.height;
    let lin = null;
    const res = grid.res, opts = res <= 10 ? {} : { supersample: 2, finer: 2 };
    for (const it of scenes) {
        const asset = it.assets.vh || it.assets.hv;
        if (!asset) continue;
        try {
            const v = await readToGrid(`${asset.href}?${await s1Token()}`, grid, utmMapper(grid, epsgOf(it, asset)), NaN, opts);
            if (!lin) lin = v; else for (let p = 0; p < N; p++) if (!(lin[p] === lin[p]) && v[p] === v[p]) lin[p] = v[p];
        } catch { /* segue-se */ }
    }
    if (!lin) return null;
    const k = res <= 10 ? 5 : res <= 40 ? 3 : 1;
    const f = C.boxMean(lin, grid.width, grid.height, k);
    const db = new Float32Array(N).fill(NaN);
    let any = false;
    for (let p = 0; p < N; p++) if (inside[p] && f[p] > 0) { db[p] = 10 * Math.log10(f[p]); any = true; }
    return any ? db : null;
}

// ---------- execução ----------
async function run({ bbox, geometry, start, end, sources = { optical: true, radar: true } }) {
    const t0 = performance.now();
    const grid = C.planDistGrid(bbox);
    if (!grid) return { error: 'too_big' };
    const N = grid.width * grid.height;
    const inside = polygonMask(grid, geometry);
    const months = C.neededMonths(start, end);
    const bStart = iso(new Date(`${start}T00:00:00Z`).getTime() - (C.PARAMS.baselineYears * 365 + 45) * DAY);
    const yearMode = (new Date(`${end}T00:00:00Z`) - new Date(`${start}T00:00:00Z`)) / DAY > 200;
    const warnings = [];
    const counts = { optBase: 0, optMon: 0, radBase: 0, radMon: 0 };
    const scenesUsed = { optical: [], radar: [] };

    // --- catálogos ---
    progress('search');
    let s2 = [], s1 = [];
    const area = { type: 'Polygon', coordinates: [[[bbox[0], bbox[1]], [bbox[2], bbox[1]], [bbox[2], bbox[3]], [bbox[0], bbox[3]], [bbox[0], bbox[1]]]] };
    if (sources.optical) {
        try {
            s2 = await stacAll(S2_STAC, { collections: ['sentinel-2-l2a'], intersects: area, datetime: `${bStart}T00:00:00Z/${end}T23:59:59Z`, limit: 100, query: { 'eo:cloud_cover': { lt: 70 } } }, 40);
            s2 = s2.filter((it) => epsgOf(it, it.assets?.nir) && ['scl', 'blue', 'nir', 'swir16', 'swir22'].every((k) => it.assets?.[k]?.href));
        } catch { warnings.push('optical_unavailable'); }
    }
    if (sources.radar) {
        try {
            s1 = await stacAll(S1_STAC, { collections: ['sentinel-1-rtc'], intersects: area, datetime: `${bStart}T00:00:00Z/${end}T23:59:59Z`, limit: 250 }, 20);
            s1 = s1.filter((it) => epsgOf(it, it.assets?.vh || it.assets?.hv) && (it.assets?.vh || it.assets?.hv));
            if (s1.length) await s1Token();
        } catch { warnings.push('radar_unavailable'); s1 = []; }
    }
    const optSel = choose(s2, { start, end, months, k: yearMode ? 2 : 3, cap: yearMode ? 60 : 30, cloud: (it) => it.properties['eo:cloud_cover'] ?? 50 });
    const radSel = choose(s1, { start, end, months, k: yearMode ? 2 : 3, cap: yearMode ? 45 : 25 });

    // --- linhas de base ---
    const ob = sources.optical && optSel.base.length ? C.opticalBaseline(N, months) : null;
    const rb = sources.radar && radSel.base.length ? C.radarBaseline(N, months) : null;
    const optBaseDates = optSel.base, radBaseDates = radSel.base, optMonDates = optSel.mon, radMonDates = radSel.mon;
    const total = optBaseDates.length + radBaseDates.length + optMonDates.length + radMonDates.length || 1;
    let done = 0;
    const tick = (step, extra = {}) => progress(step, done++ / total, extra);

    if (ob) for await (const [[d, sc], o] of inOrder(optBaseDates, ([, sc]) => readOpticalDate(sc, grid, inside))) {
        tick('opt_base', { n: optBaseDates.length });
        if (o && C.opticalBaseAdd(ob, monthOf(d), o.nbr, o.s16, optSel.annual.has(d))) { counts.optBase++; scenesUsed.optical.push(...sc.map((it) => ({ id: it.id, date: d, role: 'baseline' }))); }
    }
    if (rb) for await (const [[d, sc], db] of inOrder(radBaseDates, ([, sc]) => readRadarDate(sc, grid, inside))) {
        tick('rad_base', { n: radBaseDates.length });
        if (db && C.radarBaseAdd(rb, monthOf(d), db)) { counts.radBase++; scenesUsed.radar.push(...sc.map((it) => ({ id: it.id, date: d, role: 'baseline' }))); }
    }
    const forest = C.forestMask(ob, rb, inside);
    let forestPx = 0;
    for (let p = 0; p < N; p++) forestPx += forest[p];

    // --- monitorização ---
    const om = ob ? C.monitorState(N) : null, rm = rb ? C.monitorState(N) : null;
    if (om) for await (const [[d, sc], o] of inOrder(optMonDates, ([, sc]) => readOpticalDate(sc, grid, inside))) {
        tick('opt_mon', { n: optMonDates.length });
        if (!o) continue;
        const { an, mag } = C.opticalAnomaly(ob, monthOf(d), o.nbr, o.s16);
        C.monitorStep(om, an, C.dateCode(d), mag);
        counts.optMon++; scenesUsed.optical.push(...sc.map((it) => ({ id: it.id, date: d, role: 'monitoring' })));
    }
    if (rm) for await (const [[d, sc], db] of inOrder(radMonDates, ([, sc]) => readRadarDate(sc, grid, inside))) {
        tick('rad_mon', { n: radMonDates.length });
        if (!db) continue;
        const { an, mag } = C.radarAnomaly(rb, monthOf(d), db);
        C.monitorStep(rm, an, C.dateCode(d), mag);
        counts.radMon++; scenesUsed.radar.push(...sc.map((it) => ({ id: it.id, date: d, role: 'monitoring' })));
    }

    // --- dados suficientes? ---
    const optOk = counts.optBase >= 3 && counts.optMon >= 1;
    const radOk = counts.radBase >= 6 && counts.radMon >= 2;
    if (!optOk && !radOk) return { error: 'no_data', counts, warnings };
    if (!forestPx) return { error: 'no_forest', counts, warnings };
    if (sources.optical && !optOk) warnings.push('optical_insufficient');
    if (sources.radar && !radOk) warnings.push('radar_insufficient');

    progress('fuse');
    const fused = C.fuse(optOk ? om : null, radOk ? rm : null, forest, grid.width, grid.height, grid.res);
    const ha = grid.res * grid.res / 1e4;
    let confPx = 0, provPx = 0, observed = 0;
    for (let p = 0; p < N; p++) {
        if (fused.status[p] === 2) confPx++; else if (fused.status[p] === 1) provPx++;
        if (forest[p] && ((optOk && om.obs[p]) || (radOk && rm.obs[p]))) observed++;
    }
    const { lab, list } = C.patches(fused, grid);
    state = {
        grid, fused, forest, inside, lab, patches: list, start, end, counts, scenesUsed, warnings,
        optObs: optOk ? om.obs : new Uint16Array(N), radObs: radOk ? rm.obs : new Uint16Array(N), hansen: null,
    };
    return {
        grid, start, end, version: C.VERSION, params: C.PARAMS, seconds: (performance.now() - t0) / 1000,
        confHa: confPx * ha, provHa: provPx * ha, forestHa: forestPx * ha, coverage: observed / forestPx,
        byMonth: C.byMonth(fused, grid.res), patches: list.slice(0, 200), nPatches: list.length,
        counts, warnings, used: { optical: optOk, radar: radOk },
        status: fused.status.slice(),
    };
}

// ---------- Hansen (perda anual 2001-2025) através do nosso servidor ----------
async function hansen({ base }) {
    if (!state) return { error: 'failed' };
    const { grid, inside } = state;
    const { width: W, height: H, west, north, dx, dy } = grid;
    const lossyear = new Uint8Array(W * H);
    const perYear = {};
    for (const t of C.hansenTiles([grid.west, grid.south, grid.east, grid.north])) {
        let tiff;
        try { tiff = await fromUrl(`${base}/${t.name}`, { allowFullFile: false, blockSize: 1 << 20, cacheSize: 40 }); } catch { continue; }
        const img = allowSparseTiles(await tiff.getImage());
        const [ox, oy] = img.getOrigin(), [rx, ry] = img.getResolution();
        const IW = img.getWidth(), IH = img.getHeight();
        const c0 = Math.max(0, Math.floor((grid.west - ox) / rx)), c1 = Math.min(IW, Math.ceil((grid.east - ox) / rx));
        const r0 = Math.max(0, Math.floor((grid.north - oy) / ry)), r1 = Math.min(IH, Math.ceil((grid.south - oy) / ry));
        if (c1 <= c0 || r1 <= r0) continue;
        const [data] = await img.readRasters({ window: [c0, r0, c1, r1] });
        const ww = c1 - c0;
        // 1) área por ano a partir dos próprios píxeis Hansen (área real de cada píxel de 0,00025°, varia com a latitude)
        for (let r = r0; r < r1; r++) {
            const lat = oy + (r + 0.5) * ry;
            const j = Math.floor((north - lat) / dy);
            if (j < 0 || j >= H) continue;
            const pxHa = (Math.abs(rx) * 111320 * Math.cos(lat * Math.PI / 180)) * (Math.abs(ry) * 110574) / 1e4;
            for (let c = c0; c < c1; c++) {
                const v = data[(r - r0) * ww + (c - c0)];
                if (!v) continue;
                const i = Math.floor((ox + (c + 0.5) * rx - west) / dx);
                if (i < 0 || i >= W || !inside[j * W + i]) continue;
                perYear[2000 + v] = (perYear[2000 + v] || 0) + pxHa;
            }
        }
        // 2) camada na grelha do detector (vizinho mais próximo), para o mapa e para o GeoTIFF
        for (let j = 0; j < H; j++) {
            const r = Math.floor((north - (j + 0.5) * dy - oy) / ry);
            if (r < r0 || r >= r1) continue;
            for (let i = 0; i < W; i++) {
                const c = Math.floor((west + (i + 0.5) * dx - ox) / rx);
                if (c < c0 || c >= c1 || !inside[j * W + i]) continue;
                const v = data[(r - r0) * ww + (c - c0)];
                if (v) lossyear[j * W + i] = v;
            }
        }
    }
    state.hansen = { lossyear, perYear };
    return { perYear, lossyear: lossyear.slice() };
}

// ---------- pacote para descarregar ----------
function csvRows(rows) {
    return rows.map((r) => r.map((v) => { const s = v == null ? '' : String(v); return /[",;\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; }).join(',')).join('\n') + '\n';
}

function pack({ readme, citation, place }) {
    const s = state, { grid, fused } = s, N = grid.width * grid.height;
    const forestBand = new Uint16Array(N);
    for (let p = 0; p < N; p++) forestBand[p] = s.inside[p] ? s.forest[p] : 255;
    const mag = Uint16Array.from(fused.mag, (v) => Math.min(65535, Math.round(v * 1000)));
    const bands = [fused.status, fused.date, fused.source, mag, forestBand, s.optObs, s.radObs];
    const names = ['alert_status (0 none, 1 provisional, 2 confirmed)', 'first_detection (YYDDD: (year-2000)*1000 + day of year)', 'source (1 optical, 2 radar, 3 both)',
        'nbr_drop_x1000 (optical intensity)', 'forest_baseline (1 forest, 0 non-forest, 255 outside area)', 'optical_clear_observations', 'radar_observations'];
    if (s.hansen) { bands.push(s.hansen.lossyear); names.push('hansen_gfc_v1.13_lossyear (1-25 = 2001-2025)'); }
    const tif = C.geotiffBands(grid, bands.map((b) => Uint16Array.from(b)), names, `${C.VERSION} deforestation alerts ${s.start} to ${s.end}. geofloresta.com`);

    const ha = grid.res * grid.res / 1e4;
    const byId = new Map(s.patches.map((p) => [p.id, p]));
    const geo = C.vectorize(s.lab, s.patches.length, grid, (l) => {
        const p = byId.get(l);
        return { patch_id: l, area_ha: +p.ha.toFixed(2), confirmed_ha: +p.confHa.toFixed(2), status: p.status === 2 ? 'confirmed' : 'provisional', first_detection: p.first, source: ['', 'optical', 'radar', 'optical+radar'][p.source], method: C.VERSION };
    });
    geo.name = `GEOFLORESTA ${C.VERSION} alerts ${s.start}/${s.end}`;

    const months = C.byMonth(fused, grid.res);
    const summary = [
        ['GEOFLORESTA', C.VERSION, 'geofloresta.com'], ['place', place || ''], ['period_start', s.start], ['period_end', s.end], ['pixel_m', grid.res],
        ['confirmed_ha', (fused.status.filter((v) => v === 2).length * ha).toFixed(2)], ['provisional_ha', (fused.status.filter((v) => v === 1).length * ha).toFixed(2)],
        ['confirmed_km2', (fused.status.filter((v) => v === 2).length * ha / 100).toFixed(4)],
        ['optical_baseline_dates', s.counts.optBase], ['optical_monitoring_dates', s.counts.optMon], ['radar_baseline_dates', s.counts.radBase], ['radar_monitoring_dates', s.counts.radMon],
        [], ['month', 'confirmed_ha', 'provisional_ha'], ...Object.keys(months).sort().map((k) => [k, months[k].conf.toFixed(2), months[k].prov.toFixed(2)]),
    ];
    if (s.hansen) summary.push([], ['hansen_year', 'tree_cover_loss_ha'], ...Object.keys(s.hansen.perYear).sort().map((y) => [y, s.hansen.perYear[y].toFixed(2)]));
    const patchesCsv = [['patch_id', 'area_ha', 'confirmed_ha', 'status', 'first_detection', 'source', 'lon', 'lat'],
        ...s.patches.map((p) => [p.id, p.ha.toFixed(2), p.confHa.toFixed(2), p.status === 2 ? 'confirmed' : 'provisional', p.first, ['', 'optical', 'radar', 'optical+radar'][p.source], p.lon.toFixed(6), p.lat.toFixed(6)])];
    const v = C.validationSample(fused, s.forest, grid);
    const valCsv = [['point_id', 'stratum', 'lon', 'lat', 'alert_status', 'first_detection', 'verified_as (fill in: loss / no_loss / unclear)', 'notes'],
        ...v.points.map((p, k) => [k + 1, p.stratum, p.lon, p.lat, p.status, p.date, '', ''])];
    valCsv.push([], ['stratum_area_ha', 'alert', v.strataHa.alert.toFixed(2)], ['stratum_area_ha', 'forest_no_alert', v.strataHa.forest_no_alert.toFixed(2)]);
    const scenes = [['sensor', 'role', 'date', 'item_id'], ...s.scenesUsed.optical.map((x) => ['Sentinel-2 L2A', x.role, x.date, x.id]), ...s.scenesUsed.radar.map((x) => ['Sentinel-1 RTC', x.role, x.date, x.id])];

    const stem = `geofloresta_gfdist_${s.start}_${s.end}`;
    const blob = C.zip([
        { name: `${stem}/README.txt`, data: readme },
        { name: `${stem}/CITATION.txt`, data: citation },
        { name: `${stem}/alerts.tif`, data: tif },
        { name: `${stem}/alert_patches.geojson`, data: JSON.stringify(geo) },
        { name: `${stem}/summary.csv`, data: csvRows(summary) },
        { name: `${stem}/patches.csv`, data: csvRows(patchesCsv) },
        { name: `${stem}/validation_points.csv`, data: csvRows(valCsv) },
        { name: `${stem}/scenes.csv`, data: csvRows(scenes) },
    ]);
    return { blob, name: `${stem}.zip` };
}

onmessage = async ({ data: msg }) => {
    try {
        if (msg.cmd === 'run') {
            const res = await run(msg);
            postMessage({ id: msg.id, ...res }, res.status ? [res.status.buffer] : []);
        } else if (msg.cmd === 'hansen') {
            const res = await hansen(msg);
            postMessage({ id: msg.id, ...res }, res.lossyear ? [res.lossyear.buffer] : []);
        } else if (msg.cmd === 'package') {
            postMessage({ id: msg.id, ...pack(msg) });
        } else if (msg.cmd === 'geojson') {
            const s = state;
            const byId = new Map(s.patches.map((p) => [p.id, p]));
            const geo = C.vectorize(s.lab, s.patches.length, s.grid, (l) => ({ patch_id: l, area_ha: +byId.get(l).ha.toFixed(2), status: byId.get(l).status === 2 ? 'confirmed' : 'provisional', first_detection: byId.get(l).first }));
            postMessage({ id: msg.id, text: JSON.stringify(geo) });
        }
    } catch (e) {
        postMessage({ id: msg.id, error: 'failed', detail: String(e?.message || e) });
    }
};
