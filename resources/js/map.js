/*
 * Monitor de incêndios — MapLibre GL JS (empacotado), OpenFreeMap (positron/dark), NASA GIBS, NASA FIRMS (cache local).
 * Área de interesse: província, distrito ou polígono desenhado; cálculos no navegador com Turf.js.
 */
import maplibregl from 'maplibre-gl';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import area from '@turf/area';
import { planGrid } from './lulc-core.js';

const cfg = JSON.parse(document.getElementById('map-config')?.textContent || '{}');
const el = document.querySelector('[data-map]');
const layersForm = document.querySelector('[data-map-layers]');
const aoiForm = document.querySelector('[data-aoi]');
const T = cfg.t || {};

const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const theme = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';

let fires = null;       // FeatureCollection dos últimos 7 dias
let provinces = null;
let districts = null;
let outline = null;
let aoi = null;         // Feature (Polygon/MultiPolygon) da área de interesse
let aoiFires = [];
let drawing = null;     // vértices em desenho

const state = () => Object.fromEntries(new FormData(layersForm).entries());

function supported() {
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl'));
    } catch { return false; }
}

if (el && supported()) init();
else if (el) el.querySelector('.map-loading')?.replaceChildren(document.createTextNode(el.dataset.noWebgl || ''));

function hoursAgo(iso) {
    return (Date.now() - Date.parse(iso)) / 36e5;
}

function addOverlays(map) {
    const s = state();
    const date = s.date || cfg.imageDate;
    const before = map.getStyle().layers.find((l) => l.type === 'symbol')?.id; // por baixo dos rótulos

    // NASA GIBS (rasters WMTS; identificadores e níveis vindos do GetCapabilities)
    for (const key of ['truecolor', 'ndvi']) {
        const g = cfg.gibs?.[key];
        if (!g || map.getSource(`gibs-${key}`)) continue;
        const t = key === 'ndvi' ? (g.default_time || date) : date;
        map.addSource(`gibs-${key}`, {
            type: 'raster', tileSize: 256, maxzoom: g.maxzoom,
            tiles: [g.tiles.replace('{time}', t)],
        });
        map.addLayer({
            id: `gibs-${key}`, type: 'raster', source: `gibs-${key}`,
            layout: { visibility: s[key] ? 'visible' : 'none' },
            paint: { 'raster-opacity': key === 'ndvi' ? 0.75 : 1 },
        }, before);
    }

    // Relevo (Terrain Tiles, formato Terrarium): altitude em cores e sombreamento. Só descarrega mosaicos com a camada ligada.
    if (cfg.dem && !map.getSource('dem')) {
        map.addSource('dem', { type: 'raster-dem', encoding: 'terrarium', tileSize: 256, maxzoom: 14, tiles: [cfg.dem.tiles] });
        map.addLayer({
            id: 'relief', type: 'color-relief', source: 'dem',
            layout: { visibility: s.relief ? 'visible' : 'none' },
            paint: {
                'color-relief-opacity': 0.55,
                'color-relief-color': ['interpolate', ['linear'], ['elevation'],
                    -200, '#9cc3d5', 0, '#2f6b3a', 200, '#7fa65a', 500, '#d8c97a', 1000, '#c08a4a', 2000, '#8c5a3c', 4000, '#f4f1e8'],
            },
        }, before);
        const dark = theme() === 'dark';
        map.addLayer({
            id: 'hillshade', type: 'hillshade', source: 'dem',
            layout: { visibility: s.hillshade ? 'visible' : 'none' },
            paint: {
                'hillshade-exaggeration': 0.45,
                'hillshade-shadow-color': dark ? '#000000' : '#3b4a3f',
                'hillshade-highlight-color': dark ? '#5d6b60' : '#ffffff',
                'hillshade-accent-color': dark ? '#000000' : '#2a332c',
            },
        }, before);
    }

    // Limites
    const line = css('--c-moss') || '#3E6B4A';
    if (provinces && !map.getSource('provinces')) {
        map.addSource('provinces', { type: 'geojson', data: provinces });
        map.addLayer({ id: 'provinces', type: 'line', source: 'provinces', layout: { visibility: s.provinces ? 'visible' : 'none' }, paint: { 'line-color': line, 'line-width': 1.2 } });
    }
    if (outline && !provinces && !map.getSource('outline')) {
        map.addSource('outline', { type: 'geojson', data: outline });
        map.addLayer({ id: 'outline', type: 'line', source: 'outline', layout: { visibility: s.outline ? 'visible' : 'none' }, paint: { 'line-color': line, 'line-width': 1.4 } });
    }
    if (districts && !map.getSource('districts')) {
        map.addSource('districts', { type: 'geojson', data: districts });
        map.addLayer({ id: 'districts', type: 'line', source: 'districts', layout: { visibility: s.districts ? 'visible' : 'none' }, paint: { 'line-color': line, 'line-width': 0.5, 'line-dasharray': [2, 2] } });
    }

    // Área de interesse
    if (!map.getSource('aoi')) {
        map.addSource('aoi', { type: 'geojson', data: aoi || empty() });
        map.addLayer({ id: 'aoi-fill', type: 'fill', source: 'aoi', paint: { 'fill-color': css('--c-accent'), 'fill-opacity': 0.08 } });
        map.addLayer({ id: 'aoi-line', type: 'line', source: 'aoi', paint: { 'line-color': css('--c-accent'), 'line-width': 2 } });
        map.addSource('draw', { type: 'geojson', data: empty() });
        map.addLayer({ id: 'draw-line', type: 'line', source: 'draw', paint: { 'line-color': css('--c-accent'), 'line-width': 2, 'line-dasharray': [1, 1] } });
        map.addLayer({ id: 'draw-pts', type: 'circle', source: 'draw', filter: ['==', ['geometry-type'], 'Point'], paint: { 'circle-radius': 4, 'circle-color': css('--c-accent') } });
    }

    // Focos (cor por idade)
    if (fires && !map.getSource('fires')) {
        map.addSource('fires', { type: 'geojson', data: fires });
        map.addLayer({
            id: 'fires', type: 'circle', source: 'fires',
            layout: { visibility: s.fires ? 'visible' : 'none' },
            paint: {
                'circle-radius': ['interpolate', ['linear'], ['zoom'], 4, 1.6, 8, 3.5, 12, 6],
                'circle-color': ['step', ['get', 'age'], '#F06A33', 24, '#E3A008', 48, '#9C8B6E'],
                'circle-opacity': ['step', ['get', 'age'], 0.95, 24, 0.8, 48, 0.55],
                'circle-stroke-width': 0,
            },
        });
    }
}

function empty() {
    return { type: 'FeatureCollection', features: [] };
}

async function loadJson(url) {
    if (!url) return null;
    try {
        const r = await fetch(url, { credentials: 'same-origin' });
        return r.ok ? await r.json() : null;
    } catch { return null; }
}

// Ficheiro compacto por país: rows = [lon, lat, "AAAA-MM-DDTHH:MM", satélite (índice), confiança, FRP]
function fromCompact(d) {
    const sats = d.sats || [];
    return {
        type: 'FeatureCollection',
        features: (d.rows || []).map(([x, y, t, s, c, frp]) => ({
            type: 'Feature', geometry: { type: 'Point', coordinates: [x, y] },
            properties: { acq: `${t}:00Z`, sat: sats[s] || '', conf: c, frp, prov: '', dist: '' },
        })),
    };
}

// Escolha do país: muda de página assim que se escolhe (o botão serve sem JavaScript)
document.querySelector('[data-country] select')?.addEventListener('change', (e) => e.target.form.submit());

async function init() {
    const map = new maplibregl.Map({
        container: el,
        style: cfg.styles[theme()],
        bounds: cfg.bounds,
        fitBoundsOptions: { padding: 20 },
        attributionControl: false,
        cooperativeGestures: matchMedia('(pointer: coarse)').matches,
        locale: {},
    });
    map.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
    // O estilo OpenFreeMap já traz a sua atribuição; aqui só se acrescentam as fontes NASA
    map.addControl(new maplibregl.AttributionControl({ compact: false, customAttribution: cfg.attribution }), 'bottom-right');
    el.querySelector('.map-loading')?.remove();

    // Os focos (alguns MB) só se descarregam no monitor de incêndios
    [fires, provinces, districts, outline] = await Promise.all([cfg.tool && cfg.tool !== 'fires' ? null : loadJson(cfg.fires), loadJson(cfg.provinces), loadJson(cfg.districts), loadJson(cfg.outline)]);
    if (fires && cfg.compact) fires = fromCompact(fires);
    if (fires) fires.features.forEach((f) => { f.properties.age = Math.max(0, hoursAgo(f.properties.acq)); });

    // O estilo pode ter carregado antes ou depois dos dados: tenta até estar pronto
    // (isStyleLoaded() fica falso enquanto houver mosaicos a carregar; tenta e, se o estilo ainda não estiver pronto, repete quando o mapa ficar inactivo)
    const ready = () => {
        try { addOverlays(map); } catch { map.once('idle', ready); }
    };
    ready();
    map.on('style.load', ready);

    // Tema claro/escuro: troca o estilo de base e volta a acrescentar as camadas
    document.addEventListener('gf:theme', () => map.setStyle(cfg.styles[theme()]));

    layersForm.addEventListener('submit', (e) => e.preventDefault());
    layersForm.addEventListener('change', (e) => {
        const { name, checked, value } = e.target;
        if (name === 'date') {
            for (const key of ['truecolor']) {
                if (map.getLayer(`gibs-${key}`)) { map.removeLayer(`gibs-${key}`); map.removeSource(`gibs-${key}`); }
            }
            addOverlays(map);
            return;
        }
        if (map.getLayer(name)) map.setLayoutProperty(name, 'visibility', checked ? 'visible' : 'none');
        if (name === 'truecolor' || name === 'ndvi') map.getLayer(`gibs-${name}`) && map.setLayoutProperty(`gibs-${name}`, 'visibility', checked ? 'visible' : 'none');
    });

    // Popup de cada foco
    map.on('click', 'fires', (e) => {
        if (drawing) return;
        const p = e.features[0].properties;
        const tt = T.popup || {};
        const row = (k, v) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`;
        new maplibregl.Popup({ maxWidth: '280px' }).setLngLat(e.lngLat).setHTML(
            `<dl class="popup-dl">${row(tt.when, p.acq.replace('T', ' ').replace(':00Z', ''))}${row(tt.sat, p.sat)}${row(tt.conf, (T.conf || {})[p.conf] || p.conf)}${p.frp ? row(tt.frp, p.frp) : ''}${row(tt.where, [p.dist, p.prov].filter(Boolean).join(', ') || cfg.countryName)}</dl>`,
        ).addTo(map);
    });
    map.on('mouseenter', 'fires', () => { if (!drawing) map.getCanvas().style.cursor = 'pointer'; });
    map.on('mouseleave', 'fires', () => { if (!drawing) map.getCanvas().style.cursor = ''; });

    setupAoi(map);
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* ---------------- Área de interesse ---------------- */
function setupAoi(map) {
    const q = (s) => aoiForm.querySelector(s);
    const provSel = q('select[name="province"]');
    const distSel = q('select[name="district"]');
    const daysSel = q('select[name="days"]');
    const result = q('[data-aoi-result]');
    const dl = q('[data-aoi-download]');
    const btnDraw = q('[data-draw]'), btnFinish = q('[data-draw-finish]'), btnCancel = q('[data-draw-cancel]'), btnClear = q('[data-aoi-clear]');
    const help = q('[data-draw-help]');
    aoiForm.addEventListener('submit', (e) => e.preventDefault());
    const dem = setupDem(q, () => aoi);
    const lulc = setupLulc(q, () => aoi, map);
    const clearSelects = () => { if (provSel) provSel.value = ''; if (distSel) { distSel.value = ''; distSel.disabled = true; } };

    const setAoi = (feature) => {
        aoi = feature;
        dem.reset();
        lulc.reset();
        map.getSource('aoi')?.setData(aoi ? { type: 'FeatureCollection', features: [aoi] } : empty());
        btnClear.hidden = !aoi;
        if (aoi) {
            const b = bboxOf(aoi);
            map.fitBounds([[b[0], b[1]], [b[2], b[3]]], { padding: 40, maxZoom: 10, duration: matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 600 });
        }
        compute();
    };

    const compute = () => {
        dl.hidden = true;
        if (!aoi) { result.innerHTML = `<p class="small muted">${esc(T.pick)}</p>`; return; }
        const maxAge = Number(daysSel.value) * 24;
        aoiFires = (fires?.features || []).filter((f) => f.properties.age <= maxAge && booleanPointInPolygon(f, aoi));
        const n = aoiFires.length;
        const msg = n === 0 ? T.result[0] : n === 1 ? T.result[1] : T.result.n.replace(':count', n.toLocaleString(cfg.locale));
        const km2 = Math.round(area(aoi) / 1e6).toLocaleString(cfg.locale);
        const byDay = {};
        aoiFires.forEach((f) => { const d = f.properties.acq.slice(0, 10); byDay[d] = (byDay[d] || 0) + 1; });
        const rows = Object.keys(byDay).sort().reverse().map((d) => `<tr><td>${d}</td><td class="num">${byDay[d].toLocaleString(cfg.locale)}</td></tr>`).join('');
        result.innerHTML = `<p><strong>${esc(msg)}</strong></p><p class="small muted">${esc(T.area.replace(':km2', km2))}</p>`
            + (rows ? `<table class="table table--compact"><caption class="small">${esc(T.byDay)}</caption><tbody>${rows}</tbody></table>` : '');
        dl.hidden = n === 0;
    };

    // País inteiro (fora de Moçambique, onde não há províncias/distritos)
    q('[data-aoi-country]')?.addEventListener('click', () => {
        const f = outline?.features?.[0];
        if (f) setAoi({ type: 'Feature', properties: { nome: cfg.countryName }, geometry: f.geometry });
    });

    if (provSel && distSel) provSel.addEventListener('change', () => {
        const name = provSel.value;
        distSel.innerHTML = distSel.options[0].outerHTML;
        distSel.disabled = !name;
        if (!name) { setAoi(null); return; }
        (districts?.features || []).filter((f) => f.properties.provincia === name)
            .sort((a, b) => a.properties.nome.localeCompare(b.properties.nome, cfg.locale))
            .forEach((f) => distSel.add(new Option(f.properties.nome, f.properties.nome)));
        setAoi((provinces?.features || []).find((f) => f.properties.nome === name) || null);
    });
    distSel?.addEventListener('change', () => {
        const f = (districts?.features || []).find((d) => d.properties.nome === distSel.value && d.properties.provincia === provSel.value);
        setAoi(f || (provinces?.features || []).find((p) => p.properties.nome === provSel.value) || null);
    });
    daysSel.addEventListener('change', compute);

    // Desenho de polígono (implementação própria, sem dependências)
    const drawData = () => {
        const pts = drawing || [];
        const features = pts.map((c) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: c } }));
        if (pts.length > 1) features.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: pts.length > 2 ? [...pts, pts[0]] : pts } });
        map.getSource('draw')?.setData({ type: 'FeatureCollection', features });
    };
    const startDraw = () => {
        drawing = [];
        map.doubleClickZoom.disable();
        map.getCanvas().style.cursor = 'crosshair';
        btnDraw.hidden = true; btnFinish.hidden = false; btnCancel.hidden = false; help.hidden = false;
        btnFinish.disabled = true;
        clearSelects();
    };
    const stopDraw = () => {
        drawing = null;
        map.doubleClickZoom.enable();
        map.getCanvas().style.cursor = '';
        btnDraw.hidden = false; btnFinish.hidden = true; btnCancel.hidden = true; help.hidden = true;
        drawData();
    };
    const finishDraw = () => {
        if (!drawing || drawing.length < 3) return;
        const ring = [...drawing, drawing[0]];
        stopDraw();
        setAoi({ type: 'Feature', properties: { nome: 'polígono' }, geometry: { type: 'Polygon', coordinates: [ring] } });
        btnDraw.focus();
    };
    btnDraw.addEventListener('click', startDraw);
    btnFinish.addEventListener('click', finishDraw);
    btnCancel.addEventListener('click', () => { stopDraw(); btnDraw.focus(); });
    btnClear.addEventListener('click', () => { clearSelects(); setAoi(null); });
    map.on('click', (e) => {
        if (!drawing) return;
        drawing.push([+e.lngLat.lng.toFixed(5), +e.lngLat.lat.toFixed(5)]);
        btnFinish.disabled = drawing.length < 3;
        drawData();
    });
    map.on('dblclick', (e) => { if (drawing) { e.preventDefault(); finishDraw(); } });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawing) { stopDraw(); btnDraw.focus(); } });

    // Downloads da selecção (gerados no navegador)
    const save = (name, type, text) => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([text], { type }));
        a.download = name;
        document.body.append(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    };
    const stamp = () => new Date().toISOString().slice(0, 10);
    q('[data-aoi-csv]').addEventListener('click', () => {
        const head = 'data_hora_utc,satelite,confianca,frp_mw,pais,provincia,distrito,longitude,latitude';
        const rows = aoiFires.map((f) => {
            const p = f.properties; const [x, y] = f.geometry.coordinates;
            return [p.acq, p.sat, p.conf, p.frp ?? '', cfg.iso || '', p.prov || '', p.dist || '', x, y].map((v) => /[",]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : v).join(',');
        });
        save(`focos_${(cfg.iso || 'area').toLowerCase()}_${stamp()}.csv`, 'text/csv;charset=utf-8', '﻿' + [head, ...rows].join('\n') + '\n');
    });
    q('[data-aoi-geojson]').addEventListener('click', () => {
        const fc = { type: 'FeatureCollection', source: 'NASA FIRMS via GEOFLORESTA', updated_at: cfg.updatedAt, area: aoi, features: aoiFires.map(({ type, geometry, properties: { age, ...p } }) => ({ type, geometry, properties: p })) };
        save(`focos_${(cfg.iso || 'area').toLowerCase()}_${stamp()}.geojson`, 'application/geo+json', JSON.stringify(fc));
    });
}

/* ---------------- Relevo (DEM) da área de interesse ---------------- */
function setupDem(q, getAoi) {
    const box = q('[data-dem]');
    if (!box) return { reset() {} };
    const btnBuild = q('[data-dem-build]'), status = q('[data-dem-status]'), ready = q('[data-dem-ready]');
    const interval = q('select[name="interval"]'), btnTiff = q('[data-dem-tiff]'), btnContours = q('[data-dem-contours]');
    let worker = null, seq = 0, meta = null;
    const pending = new Map();
    const fmt = (n) => Math.round(n).toLocaleString(cfg.locale);
    const say = (text) => { status.textContent = text; };

    const call = (msg, transfer) => new Promise((resolve) => {
        if (!worker) {
            worker = new Worker(new URL('./dem-worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = ({ data }) => {
                if (data.progress) { say(T.dem_progress.replace(':done', data.progress).replace(':total', data.total)); return; }
                pending.get(data.id)?.(data);
                pending.delete(data.id);
            };
            worker.onerror = () => { for (const r of pending.values()) r({ error: 'failed' }); pending.clear(); worker = null; };
        }
        const id = ++seq;
        pending.set(id, resolve);
        worker.postMessage({ ...msg, id }, transfer || []);
    });

    const busy = (on) => { [btnBuild, btnTiff, btnContours].forEach((b) => { b.disabled = on; }); box.setAttribute('aria-busy', on ? 'true' : 'false'); };
    const name = (kind, ext) => `${kind}_${(cfg.iso || 'area').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.${ext}`;
    const download = (filename, blob) => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.append(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    };

    btnBuild.addEventListener('click', async () => {
        const area = getAoi();
        if (!area) return;
        busy(true); ready.hidden = true; meta = null;
        say(T.dem_working);
        const r = await call({ cmd: 'build', bbox: bboxOf(area), geometry: area.geometry });
        busy(false);
        if (getAoi() !== area) return; // a área mudou entretanto
        if (r.error) { say(T[`dem_${r.error === 'too_big' ? 'too_big' : r.error === 'empty' ? 'empty' : 'failed'}`]); return; }
        meta = r.meta;
        say(T.dem_result.replace(':res', fmt(meta.resolution_m)).replace(':min', fmt(meta.min)).replace(':max', fmt(meta.max)).replace(':mean', fmt(meta.mean)));
        // Intervalo sugerido: o menor que dê até ~40 curvas
        const range = meta.max - meta.min;
        interval.value = String([10, 20, 50, 100, 200, 500].find((i) => range / i <= 40) || 500);
        ready.hidden = false;
    });

    btnTiff.addEventListener('click', async () => {
        busy(true);
        const r = await call({ cmd: 'tiff', description: `Elevation (m), WGS 84 (EPSG:4326), ~${meta?.resolution_m} m. Source: Terrain Tiles (Mapzen/Tilezen, AWS Open Data): SRTM, GMTED2010, ETOPO1 and others. Generated at geofloresta.com` });
        busy(false);
        if (r.error) { say(T.dem_failed); return; }
        download(name('relevo', 'tif'), new Blob([r.buffer], { type: 'image/tiff' }));
    });

    btnContours.addEventListener('click', async () => {
        busy(true); say(T.dem_working);
        const step = Number(interval.value);
        const r = await call({ cmd: 'contours', interval: step, props: {
            name: `Curvas de nível / contour lines (${step} m)`, crs: { type: 'name', properties: { name: 'urn:ogc:def:crs:OGC:1.3:CRS84' } },
            source: 'Terrain Tiles (Mapzen/Tilezen, AWS Open Data) via GEOFLORESTA (geofloresta.com)',
            attribution: cfg.dem?.attribution || '', resolution_m: meta?.resolution_m, interval_m: step, generated_at: new Date().toISOString(),
        } });
        busy(false);
        if (r.error) { say(T.dem_failed); return; }
        say(T.dem_contours_done.replace(':count', r.count.toLocaleString(cfg.locale)));
        download(name(`curvas_${step}m`, 'geojson'), new Blob([r.text], { type: 'application/geo+json' }));
    });

    return {
        reset() {
            box.hidden = !getAoi();
            ready.hidden = true;
            meta = null;
            say('');
        },
    };
}

/* ---------------- Uso e cobertura do solo (Random Forest no navegador) ---------------- */
const LULC_CODES = [1, 2, 4, 5, 7, 8, 9, 11];
const LULC_COLORS = { 1: '#419bdf', 2: '#397d49', 4: '#7a87c6', 5: '#e49635', 7: '#c4281b', 8: '#a59b8f', 9: '#a8ebff', 11: '#e3e2c3' };

function setupLulc(q, getAoi, map) {
    const box = q('[data-lulc]');
    if (!box) return { reset() {} };
    const L = T.lulc || {};
    const names = Object.fromEntries(LULC_CODES.map((c, k) => [c, (L.lulc_classes || [])[k] || String(c)]));
    const btnRun = q('[data-lulc-run]'), status = q('[data-lulc-status]'), bar = q('[data-lulc-progress]');
    const result = q('[data-lulc-result]'), dl = q('[data-lulc-download]'), show = q('[data-lulc-show]');
    const areaEl = q('[data-lulc-area]');
    const btnTiff = q('[data-lulc-tiff]'), btnCsv = q('[data-lulc-csv]');
    const start = q('input[name="lulc_start"]'), end = q('input[name="lulc_end"]'), trees = q('select[name="lulc_trees"]');
    end.max = start.max = new Date().toISOString().slice(0, 10);
    let worker = null, seq = 0, last = null, run = 0;
    const pending = new Map();
    const pct = (v) => (v == null ? '–' : `${(v * 100).toLocaleString(cfg.locale, { maximumFractionDigits: 1 })} %`);
    const num = (v, d = 0) => v.toLocaleString(cfg.locale, { maximumFractionDigits: d, minimumFractionDigits: d });
    const say = (t) => { status.textContent = t; };

    const call = (msg) => new Promise((resolve) => {
        if (!worker) {
            worker = new Worker(new URL('./lulc-worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = ({ data }) => {
                if (data.progress) {
                    say((L[`lulc_step_${data.progress}`] || L.lulc_running || '').replace(':scenes', data.scenes ?? '').replace(':trees', data.trees ?? ''));
                    bar.hidden = false;
                    const order = ['search', 'clouds', 'bands', 'terrain', 'reference', 'features', 'samples', 'train', 'classify'];
                    const w = { search: 0.02, clouds: 0.08, bands: 0.5, terrain: 0.03, reference: 0.04, features: 0.03, samples: 0.02, train: 0.08, classify: 0.2 };
                    let done = 0;
                    for (const k of order) { if (k === data.progress) { done += w[k] * (data.frac || 0); break; } done += w[k]; }
                    bar.value = Math.min(1, done);
                    return;
                }
                pending.get(data.id)?.(data);
                pending.delete(data.id);
            };
            worker.onerror = () => { for (const r of pending.values()) r({ error: 'failed' }); pending.clear(); worker = null; };
        }
        const id = ++seq;
        pending.set(id, resolve);
        worker.postMessage({ ...msg, id });
    });

    // Área escolhida: tamanho, resolução e se cabe no limite (o botão só fica activo com uma área válida)
    const checkArea = () => {
        const a = getAoi();
        areaEl.classList.remove('is-ok', 'is-bad');
        if (!a) { areaEl.textContent = L.area_none; btnRun.disabled = true; return false; }
        const sq = area(a) / 1e6;
        const km2 = (sq < 10 ? Math.round(sq * 10) / 10 : Math.round(sq)).toLocaleString(cfg.locale);
        if (sq < 1) { areaEl.textContent = L.area_small.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        const plan = sq <= 10000 ? planGrid(bboxOf(a)) : null;
        if (!plan) { areaEl.textContent = L.area_big.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        areaEl.textContent = L.area_ok.replace(':km2', km2).replace(':res', plan.res);
        areaEl.classList.add('is-ok');
        btnRun.disabled = false;
        return true;
    };

    const removeLayer = () => {
        if (map.getLayer('lulc')) map.removeLayer('lulc');
        if (map.getSource('lulc')) map.removeSource('lulc');
    };

    const draw = (r) => {
        const { width: W, height: H, west, north, east, south } = r.grid;
        const cv = document.createElement('canvas');
        cv.width = W; cv.height = H;
        const ctx = cv.getContext('2d'), img = ctx.createImageData(W, H);
        const rgb = Object.fromEntries(Object.entries(LULC_COLORS).map(([k, c]) => [k, [1, 3, 5].map((i) => parseInt(c.slice(i, i + 2), 16))]));
        for (let p = 0; p < W * H; p++) {
            const c = rgb[r.classMap[p]];
            if (!c) continue;
            img.data[p * 4] = c[0]; img.data[p * 4 + 1] = c[1]; img.data[p * 4 + 2] = c[2]; img.data[p * 4 + 3] = 255;
        }
        ctx.putImageData(img, 0, 0);
        removeLayer();
        // fonte «canvas»: desenha directamente a partir do canvas, sem pedidos de rede (respeita a CSP)
        map.addSource('lulc', { type: 'canvas', canvas: cv, animate: false, coordinates: [[west, north], [east, north], [east, south], [west, south]] });
        map.addLayer({ id: 'lulc', type: 'raster', source: 'lulc', paint: { 'raster-opacity': 0.85, 'raster-resampling': 'nearest' } }, map.getLayer('aoi-line') ? 'aoi-line' : undefined);
    };

    const report = (r) => {
        const a = r.accuracy, cls = r.classes;
        const th = (c) => `<th scope="col"><span class="lulc-swatch" style="background:${LULC_COLORS[c]}"></span>${esc(names[c])}</th>`;
        let h = `<div class="lulc-kpis"><div><span class="lulc-kpi">${pct(a.overall)}</span><span class="small">${esc(L.lulc_accuracy)}</span></div>`
            + `<div><span class="lulc-kpi">${num(a.kappa, 2)}</span><span class="small">${esc(L.lulc_kappa)}</span></div></div>`;
        h += `<p class="small muted">${esc(L.lulc_done.replace(':s', num(r.seconds)).replace(':res', r.grid.res).replace(':trees', r.trees))} ${esc(L.lulc_samples.replace(':train', r.samples.train).replace(':val', r.samples.validation))}</p>`;
        h += `<p class="small"><strong>${esc(L.lulc_matrix)}</strong></p><div class="table-scroll"><table class="lulc-matrix"><thead><tr><th scope="col" class="small">${esc(L.lulc_ref_map)}</th>${cls.map(th).join('')}<th scope="col">${esc(L.lulc_producer)}</th></tr></thead><tbody>`;
        a.matrix.forEach((row, i) => {
            h += `<tr><th scope="row"><span class="lulc-swatch" style="background:${LULC_COLORS[cls[i]]}"></span>${esc(names[cls[i]])}</th>`
                + row.map((v, j) => `<td${i === j ? ' class="lulc-diag"' : ''}>${v}</td>`).join('') + `<td>${pct(a.producers[i])}</td></tr>`;
        });
        h += `<tr><th scope="row">${esc(L.lulc_user)}</th>${a.users.map((u) => `<td>${pct(u)}</td>`).join('')}<td></td></tr></tbody></table></div>`;
        const total = r.areas.reduce((s, x) => s + x.ha, 0) || 1;
        h += `<p class="small"><strong>${esc(L.lulc_area)}</strong></p><table class="lulc-areas"><tbody>`
            + r.areas.map((x) => `<tr><th scope="row"><span class="lulc-swatch" style="background:${LULC_COLORS[x.code]}"></span>${esc(names[x.code])}</th><td>${num(x.ha)} ha</td><td>${pct(x.ha / total)}</td></tr>`).join('')
            + '</tbody></table>';
        h += `<p class="small muted">${esc(L.lulc_ref_year.replace(':year', r.year))} ${esc(L.lulc_scenes.replace(':list', r.scenes.map((s) => s.date).join(', ')))}</p>`;
        result.innerHTML = h;
        result.hidden = false;
    };

    const fileName = (ext) => `uso_solo_rf_${(cfg.iso || 'area').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.${ext}`;
    const download = (filename, blob) => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.append(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    };
    const csv = (r) => {
        const cls = r.classes, a = r.accuracy, rows = [];
        const q2 = (v) => `"${String(v).replace(/"/g, '""')}"`;
        rows.push(['GEOFLORESTA - Random Forest (geofloresta.com)'], [L.lulc_warning + ' ' + L.lulc_warning_text]);
        rows.push([L.lulc_accuracy, a.overall.toFixed(4)], [L.lulc_kappa, a.kappa.toFixed(4)], ['trees', r.trees], ['pixel_m', r.grid.res],
            ['images', r.scenes.map((s) => s.id).join(' ')], ['reference', `Sentinel-2 10 m Land Use/Land Cover ${r.year} (Impact Observatory, Microsoft, Esri; CC BY 4.0)`], []);
        rows.push([L.lulc_matrix], [L.lulc_ref_map, ...cls.map((c) => names[c]), L.lulc_producer]);
        a.matrix.forEach((row, i) => rows.push([names[cls[i]], ...row, a.producers[i] ?? '']));
        rows.push([L.lulc_user, ...a.users.map((u) => u ?? '')], []);
        rows.push([L.lulc_area, 'code', 'ha', '%']);
        const total = r.areas.reduce((s, x) => s + x.ha, 0) || 1;
        r.areas.forEach((x) => rows.push([names[x.code], x.code, x.ha.toFixed(2), (100 * x.ha / total).toFixed(2)]));
        rows.push([], ['variable', 'importance'], ...Object.entries(r.importance).map(([k, v]) => [k, v.toFixed(4)]));
        return '﻿' + rows.map((r2) => r2.map(q2).join(',')).join('\r\n');
    };

    btnRun.addEventListener('click', async () => {
        const area = getAoi();
        if (!area) return;
        const id = ++run;
        btnRun.disabled = true; box.setAttribute('aria-busy', 'true');
        result.hidden = true; dl.hidden = true; last = null; removeLayer();
        bar.value = 0; bar.hidden = false;
        say(L.lulc_running);
        const s0 = start.value || '2025-01-01', e0 = end.value || '2025-12-31';
        const r = await call({ cmd: 'run', bbox: bboxOf(area), geometry: area.geometry, start: s0 <= e0 ? s0 : e0, end: s0 <= e0 ? e0 : s0, trees: Number(trees.value) });
        checkArea(); box.setAttribute('aria-busy', 'false'); bar.hidden = true;
        if (id !== run || getAoi() !== area) return;
        if (r.error) { say(L[`lulc_${['too_big', 'no_images', 'no_valid', 'few_classes'].includes(r.error) ? r.error : 'failed'}`]); return; }
        last = r;
        say('');
        draw(r); report(r);
        show.checked = true;
        dl.hidden = false;
        dl.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        window.gtag?.('event', 'lulc_classify', { trees: r.trees, pixel_m: r.grid.res, overall: Math.round(r.accuracy.overall * 1000) / 1000 });
    });
    show.addEventListener('change', () => { if (map.getLayer('lulc')) map.setLayoutProperty('lulc', 'visibility', show.checked ? 'visible' : 'none'); });
    btnTiff.addEventListener('click', async () => {
        if (!last) return;
        const r = await call({ cmd: 'tiff', description: `Land use/land cover, Random Forest (${last.trees} trees), Sentinel-2 ${last.scenes.map((s) => s.date).join(' ')}; classes = Sentinel-2 10 m LULC codes (Impact Observatory/Esri). NOT validated with field data. geofloresta.com` });
        if (r.error) { say(L.lulc_failed); return; }
        download(fileName('tif'), new Blob([r.buffer], { type: 'image/tiff' }));
        window.gtag?.('event', 'file_download', { file_name: fileName('tif'), link_text: 'lulc_geotiff' });
    });
    btnCsv.addEventListener('click', () => {
        if (!last) return;
        download(fileName('csv'), new Blob([csv(last)], { type: 'text/csv;charset=utf-8' }));
        window.gtag?.('event', 'file_download', { file_name: fileName('csv'), link_text: 'lulc_csv' });
    });

    return {
        reset() {
            run++;
            box.hidden = false;
            result.hidden = true; dl.hidden = true; bar.hidden = true; last = null;
            checkArea();
            say('');
            removeLayer();
        },
    };
}

function bboxOf(feature) {
    let b = [Infinity, Infinity, -Infinity, -Infinity];
    const walk = (c) => {
        if (typeof c[0] === 'number') { b = [Math.min(b[0], c[0]), Math.min(b[1], c[1]), Math.max(b[2], c[0]), Math.max(b[3], c[1])]; return; }
        c.forEach(walk);
    };
    walk(feature.geometry.coordinates);
    return b;
}
