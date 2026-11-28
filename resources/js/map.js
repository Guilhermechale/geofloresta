/*
 * Monitor de incêndios — MapLibre GL JS (empacotado), OpenFreeMap (positron/dark), NASA GIBS, NASA FIRMS (cache local).
 * Área de interesse: província, distrito ou polígono desenhado; cálculos no navegador com Turf.js.
 */
import maplibregl from 'maplibre-gl';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import area from '@turf/area';
import { planGrid, MAX_AREA_KM2, REGIONAL_KM2 } from './lulc-core.js';
import { planDistGrid, MAX_AREA_KM2 as DIST_MAX_KM2, VERSION as DIST_VERSION, PARAMS as DIST_PARAMS } from './dist-core.js';
import { setupResizer } from './map-resizer.js';

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

setupResizer();
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
    // O mapa acompanha a largura escolhida na barra entre o mapa e o painel
    if ('ResizeObserver' in window) new ResizeObserver(() => map.resize()).observe(el);
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
    // O estilo OpenFreeMap já traz a sua atribuição; aqui só se acrescentam as fontes NASA
    map.addControl(new maplibregl.AttributionControl({ compact: true, customAttribution: cfg.attribution }), 'bottom-right');
    // créditos recolhidos no botão «i» desde o início (o MapLibre abre-os ao carregar)
    map.once('load', () => el.querySelector('.maplibregl-ctrl-attrib')?.classList.remove('maplibregl-compact-show'));
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
    const dist = setupDist(q, () => aoi, map);
    const clearSelects = () => { if (provSel) provSel.value = ''; if (distSel) { distSel.value = ''; distSel.disabled = true; } };

    const setAoi = (feature) => {
        aoi = feature;
        dem.reset();
        lulc.reset();
        dist.reset();
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
    // Área partilhada por ligação: ?bbox=oeste,sul,leste,norte (graus WGS 84)
    const shared = new URLSearchParams(location.search).get('bbox')?.split(',').map(Number);
    if (shared?.length === 4 && shared.every(Number.isFinite) && shared[0] < shared[2] && shared[1] < shared[3]
        && shared[0] >= -180 && shared[2] <= 180 && shared[1] >= -90 && shared[3] <= 90) {
        const [w, s0, e, n] = shared;
        setAoi({ type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [[[w, s0], [e, s0], [e, n], [w, n], [w, s0]]] } });
    }

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
    const result = q('[data-lulc-result]'), extra = q('[data-lulc-extra]'), dl = q('[data-lulc-download]'), show = q('[data-lulc-show]');
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
    let regional = false;
    const checkArea = () => {
        const a = getAoi();
        areaEl.classList.remove('is-ok', 'is-bad', 'is-warn');
        if (!a) { areaEl.textContent = L.area_none; btnRun.disabled = true; return false; }
        const sq = area(a) / 1e6;
        const km2 = (sq < 10 ? Math.round(sq * 10) / 10 : Math.round(sq)).toLocaleString(cfg.locale);
        if (sq < 1) { areaEl.textContent = L.area_small.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        const plan = sq <= MAX_AREA_KM2 ? planGrid(bboxOf(a)) : null;
        if (!plan) { areaEl.textContent = L.area_big.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        regional = sq > REGIONAL_KM2;
        if (regional) {
            // Modo regional: píxel maior, mais tempo; no telemóvel pode faltar memória
            const phone = matchMedia('(pointer: coarse)').matches || (navigator.deviceMemory && navigator.deviceMemory < 4);
            areaEl.textContent = L.area_regional.replace(':km2', km2).replace(':res', plan.res) + (phone ? ' ' + L.area_regional_phone : '');
            areaEl.classList.add('is-ok', 'is-warn');
        } else {
            areaEl.classList.remove('is-warn');
            areaEl.textContent = L.area_ok.replace(':km2', km2).replace(':res', plan.res);
            areaEl.classList.add('is-ok');
        }
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
        const a = r.accuracy, cls = r.classes, P = L.panel || {};
        const sw = (c) => `<span class="lulc-swatch" style="background:${LULC_COLORS[c]}"></span>`;
        // Indicadores em destaque e uma linha com o essencial
        result.innerHTML = `<div class="lulc-kpis"><div><span class="lulc-kpi">${pct(a.overall)}</span><span class="small">${esc(L.lulc_accuracy)}</span></div>`
            + `<div><span class="lulc-kpi">${num(a.kappa, 2)}</span><span class="small">${esc(L.lulc_kappa)}</span></div></div>`
            + `<p class="lulc-meta small muted">${esc((P.lulc_meta || '').replace(':s', num(r.seconds)).replace(':res', r.grid.res).replace(':trees', r.trees))}</p>`;
        result.hidden = false;
        // Área por classe em barras (do maior para o menor)
        const total = r.areas.reduce((s2, x) => s2 + x.ha, 0) || 1;
        let h = `<h4 class="lulc-sub">${esc(L.lulc_area)}</h4><ul class="lulc-bars">`
            + [...r.areas].sort((x, y) => y.ha - x.ha).map((x) => `<li><span class="lulc-bars__name">${sw(x.code)}${esc(names[x.code])}</span>`
                + `<span class="lulc-bars__val">${pct(x.ha / total)} · ${num(x.ha)} ha</span>`
                + `<span class="lulc-bars__bar"><span style="width:${(100 * x.ha / total).toFixed(1)}%;background:${LULC_COLORS[x.code]}"></span></span></li>`).join('')
            + '</ul>';
        // Matriz de confusão e detalhes técnicos: recolhidos, para não encher o painel
        // Matriz compacta: cada classe é o seu quadrado de cor (o nome aparece ao passar o rato; as cores estão nas barras acima)
        const p0 = (v) => (v == null || Number.isNaN(v) ? '' : Math.round(v * 100) + '%');
        const sq = (c) => `<span class="lulc-swatch" style="background:${LULC_COLORS[c]}" title="${esc(names[c])}"></span><span class="visually-hidden">${esc(names[c])}</span>`;
        h += `<details class="panel-fold"><summary>${esc(P.lulc_matrix_short || L.lulc_matrix)}</summary>`
            + `<div class="table-scroll"><table class="lulc-matrix lulc-matrix--compact"><caption class="small muted">${esc(L.lulc_ref_map)}</caption><thead><tr><th scope="col"></th>`
            + cls.map((c) => `<th scope="col">${sq(c)}</th>`).join('') + `<th scope="col" title="${esc(L.lulc_producer)}"><abbr title="${esc(L.lulc_producer)}">${esc(L.lulc_producer.slice(0, 4))}.</abbr></th></tr></thead><tbody>`;
        a.matrix.forEach((row, i) => {
            h += `<tr><th scope="row">${sq(cls[i])}</th>`
                + row.map((v, j) => `<td${i === j ? ' class="lulc-diag"' : ''}>${v}</td>`).join('') + `<td>${p0(a.producers[i])}</td></tr>`;
        });
        h += `<tr><th scope="row"><abbr title="${esc(L.lulc_user)}">${esc(L.lulc_user.slice(0, 4))}.</abbr></th>${a.users.map((u) => `<td>${p0(u)}</td>`).join('')}<td></td></tr></tbody></table></div></details>`;
        h += `<details class="panel-fold"><summary>${esc(P.lulc_details || '')}</summary><p class="small muted">`
            + `${esc(L.lulc_samples.replace(':train', r.samples.train).replace(':val', r.samples.validation))} ${esc(L.lulc_ref_year.replace(':year', r.year))} `
            + `${esc(L.lulc_scenes.replace(':list', (() => { const d = [...new Set(r.scenes.map((x) => x.date))].sort(); return `${r.scenes.length} (${d[0]}${d.length > 1 ? ' – ' + d[d.length - 1] : ''})`; })()))}</p></details>`;
        extra.innerHTML = h;
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
        say(regional ? L.lulc_running_regional : L.lulc_running);
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

/* ---------------- Monitor de desmatamento (GF-DIST) ---------------- */
function setupDist(q, getAoi, map) {
    const box = q('[data-dist]');
    if (!box) return { reset() {} };
    const D = T.dist || {};
    const btnRun = q('[data-dist-run]'), status = q('[data-dist-status]'), bar = q('[data-dist-progress]');
    const card = q('[data-dist-card]'), result = q('[data-dist-result]'), show = q('[data-dist-show]'), areaEl = q('[data-dist-area]');
    const period = q('select[name="dist_period"]'), yearBox = q('[data-dist-year]'), yearSel = q('select[name="dist_year"]');
    const optical = q('input[name="dist_optical"]'), radar = q('input[name="dist_radar"]');
    const hRun = q('[data-dist-hansen-run]'), hRes = q('[data-dist-hansen-result]'), hToggle = q('[data-dist-hansen-toggle]'), hShow = q('[data-dist-hansen-show]'), hLegend = q('[data-dist-hansen-legend]');
    let worker = null, seq = 0, last = null, run = 0, hansen = null;
    const pending = new Map();
    const num = (v, d = 0) => Number(v).toLocaleString(cfg.locale, { maximumFractionDigits: d, minimumFractionDigits: d });
    const haTxt = (v) => (v >= 100 ? num(v) : num(v, v >= 10 ? 1 : 2));
    const say = (t) => { status.textContent = t; };
    const level = () => q('input[name="dist_level"]:checked')?.value || 'conf';
    const call = (msg) => new Promise((resolve) => {
        if (!worker) {
            worker = new Worker(new URL('./dist-worker.js', import.meta.url), { type: 'module' });
            worker.onmessage = ({ data }) => {
                if (data.progress) {
                    say((D[`step_${data.progress}`] || D.running || '').replace(':n', data.n ?? ''));
                    bar.hidden = false;
                    if (data.progress !== 'search') bar.value = Math.min(1, data.frac || 0);
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

    const dates = () => {
        const today = new Date().toISOString().slice(0, 10);
        if (period.value === 'year') {
            const y = Number(yearSel.value);
            const end = `${y}-12-31`;
            return { start: `${y}-01-01`, end: end > today ? today : end };
        }
        return { start: new Date(Date.now() - Number(period.value) * 864e5).toISOString().slice(0, 10), end: today };
    };
    period.addEventListener('change', () => { yearBox.hidden = period.value !== 'year'; });

    const checkArea = () => {
        const a = getAoi();
        areaEl.classList.remove('is-ok', 'is-bad', 'is-warn');
        const okSrc = optical.checked || radar.checked;
        if (!a) { areaEl.textContent = (T.lulc || {}).area_none || ''; btnRun.disabled = true; return false; }
        const sq = area(a) / 1e6;
        const km2 = (sq < 10 ? Math.round(sq * 100) / 100 : Math.round(sq)).toLocaleString(cfg.locale);
        if (sq < 0.05) { areaEl.textContent = D.area_small.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        const plan = sq <= DIST_MAX_KM2 ? planDistGrid(bboxOf(a)) : null;
        if (!plan) { areaEl.textContent = D.area_big.replace(':km2', km2); areaEl.classList.add('is-bad'); btnRun.disabled = true; return false; }
        areaEl.textContent = D.area_ok.replace(':km2', km2).replace(':res', plan.res);
        areaEl.classList.add('is-ok');
        btnRun.disabled = !okSrc;
        return true;
    };
    optical.addEventListener('change', checkArea);
    radar.addEventListener('change', checkArea);

    const removeLayer = (id) => { if (map.getLayer(id)) map.removeLayer(id); if (map.getSource(id)) map.removeSource(id); };
    const canvasLayer = (id, grid, paint) => {
        const { width: W, height: H, west, north, east, south } = grid;
        const cv = document.createElement('canvas');
        cv.width = W; cv.height = H;
        const ctx = cv.getContext('2d'), img = ctx.createImageData(W, H);
        for (let p = 0; p < W * H; p++) {
            const c = paint(p);
            if (!c) continue;
            img.data[p * 4] = c[0]; img.data[p * 4 + 1] = c[1]; img.data[p * 4 + 2] = c[2]; img.data[p * 4 + 3] = c[3] ?? 235;
        }
        ctx.putImageData(img, 0, 0);
        removeLayer(id);
        map.addSource(id, { type: 'canvas', canvas: cv, animate: false, coordinates: [[west, north], [east, north], [east, south], [west, south]] });
        map.addLayer({ id, type: 'raster', source: id, paint: { 'raster-opacity': 0.9, 'raster-resampling': 'nearest' } }, map.getLayer('aoi-line') ? 'aoi-line' : undefined);
    };
    const CONF = [220, 38, 38], PROV = [245, 158, 11], HANSEN = [124, 58, 237, 170];
    const draw = () => {
        if (!last) return;
        const all = level() === 'all';
        canvasLayer('dist', last.grid, (p) => (last.status[p] === 2 ? CONF : all && last.status[p] === 1 ? PROV : null));
        map.setLayoutProperty('dist', 'visibility', show.checked ? 'visible' : 'none');
    };

    const report = () => {
        const r = last, all = level() === 'all';
        const src = (k) => D[`src_${k}`] || '';
        const kpi = (v, label) => `<div><span class="lulc-kpi">${haTxt(v)} ha</span><span class="small">${esc(label)} · ${num(v / 100, 2)} ${esc(D.km2)}</span></div>`;
        let h = `<div class="lulc-kpis">${kpi(r.confHa, D.kpi_conf)}${all ? kpi(r.provHa, D.kpi_prov) : ''}</div>`;
        const shown = r.patches.filter((p) => all || p.status === 2);
        h += `<p class="lulc-meta small muted">${esc(D.patches_n.replace(':n', num(all ? r.nPatches : shown.length)))} · ${esc(D.meta.replace(':res', r.grid.res).replace(':start', r.start).replace(':end', r.end).replace(':s', num(r.seconds)))}</p>`;
        for (const w of r.warnings || []) if (D[`w_${w}`]) h += `<p class="small is-warn-text">${esc(D[`w_${w}`])}</p>`;
        if (!r.confHa && !(all && r.provHa)) h += `<p class="small">${esc(D.none)}</p>`;
        const used = [r.used.optical ? `${src(1)} (${r.counts.optMon})` : '', r.used.radar ? `${src(2)} (${r.counts.radMon})` : ''].filter(Boolean).join(' + ');
        h += `<dl class="dist-facts small"><dt>${esc(D.forest)}</dt><dd>${haTxt(r.forestHa)} ha</dd><dt>${esc(D.coverage)}</dt><dd>${num(r.coverage * 100)} %</dd><dt>${esc(D.used)}</dt><dd>${esc(used)}</dd></dl>`;
        const months = Object.keys(r.byMonth).sort();
        const val = (m) => r.byMonth[m].conf + (all ? r.byMonth[m].prov : 0);
        const max = Math.max(...months.map(val), 1e-9);
        if (months.some((m) => val(m) > 0)) {
            h += `<h4 class="lulc-sub">${esc(D.by_month)}</h4><ul class="lulc-bars">` + months.filter((m) => val(m) > 0).map((m) => `<li><span class="lulc-bars__name">${esc(m)}</span><span class="lulc-bars__val">${haTxt(val(m))} ha</span>`
                + `<span class="lulc-bars__bar"><span style="width:${(100 * val(m) / max).toFixed(1)}%;background:#dc2626"></span></span></li>`).join('') + '</ul>';
        }
        if (shown.length) {
            h += `<h4 class="lulc-sub">${esc(D.top)}</h4><table class="dist-patches small"><tbody>` + shown.slice(0, 8).map((p, k) => `<tr><td class="num">${haTxt(p.ha)} ha</td><td class="nowrap">${esc(p.first)}</td>`
                + `<td><span class="sw ${p.status === 2 ? 'sw--conf' : 'sw--prov'}" title="${esc(p.status === 2 ? D.legend_conf : D.legend_prov)}"></span> <span class="muted">${esc(src(p.source))}</span></td>`
                + `<td><button type="button" class="dist-zoom" data-zoom="${k}" title="${esc(D.zoom)}" aria-label="${esc(D.zoom)}: ${haTxt(p.ha)} ha"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5M11 8v6M8 11h6"/></svg></button></td></tr>`).join('') + '</tbody></table>';
        }
        result.innerHTML = h;
        result.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => {
            const p = shown[Number(b.dataset.zoom)];
            map.fitBounds([[p.bbox[0], p.bbox[1]], [p.bbox[2], p.bbox[3]]], { padding: 80, maxZoom: 15 });
        }));
    };

    btnRun.addEventListener('click', async () => {
        const a = getAoi();
        if (!a || !checkArea()) return;
        const id = ++run;
        btnRun.disabled = true; box.setAttribute('aria-busy', 'true');
        card.hidden = true; last = null; hansen = null; removeLayer('dist'); removeLayer('dist-hansen');
        bar.value = 0; bar.hidden = false;
        say(D.running);
        const { start, end } = dates();
        const r = await call({ cmd: 'run', bbox: bboxOf(a), geometry: a.geometry, start, end, sources: { optical: optical.checked, radar: radar.checked } });
        checkArea(); box.setAttribute('aria-busy', 'false'); bar.hidden = true;
        if (id !== run || getAoi() !== a) return;
        if (r.error) {
            const c = r.counts || {};
            say((D[r.error] || D.failed).replace(':opt', c.optMon ?? 0).replace(':rad', c.radMon ?? 0));
            return;
        }
        last = r; say('');
        hRes.innerHTML = ''; hToggle.hidden = true; hLegend.hidden = true; hRun.hidden = false; hRun.disabled = false;
        draw(); report();
        show.checked = true; card.hidden = false;
        { const b = bboxOf(a); map.fitBounds([[b[0], b[1]], [b[2], b[3]]], { padding: 40, maxZoom: 14, duration: 0 }); }
        card.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        window.gtag?.('event', 'deforestation_detect', { pixel_m: r.grid.res, confirmed_ha: Math.round(r.confHa), optical: r.used.optical, radar: r.used.radar });
    });
    box.addEventListener('change', (e) => { if (e.target.name === 'dist_level' && last) { draw(); report(); } });
    show.addEventListener('change', () => { if (map.getLayer('dist')) map.setLayoutProperty('dist', 'visibility', show.checked ? 'visible' : 'none'); });
    hShow.addEventListener('change', () => { if (map.getLayer('dist-hansen')) map.setLayoutProperty('dist-hansen', 'visibility', hShow.checked ? 'visible' : 'none'); });

    hRun.addEventListener('click', async () => {
        if (!last) return;
        hRun.disabled = true; say(D.step_hansen);
        const r = await call({ cmd: 'hansen', base: D.hansenBase });
        say('');
        if (r.error || !r.perYear) { hRes.innerHTML = `<p class="small">${esc(D.hansen_failed)}</p>`; hRun.disabled = false; return; }
        hansen = r;
        hRun.hidden = true;
        const years = Object.keys(r.perYear).map(Number).sort();
        if (!years.length) { hRes.innerHTML = `<p class="small">${esc(D.hansen_none)}</p>`; return; }
        const since = years.filter((y) => y >= 2021).reduce((s, y) => s + r.perYear[y], 0);
        const max = Math.max(...years.map((y) => r.perYear[y]));
        hRes.innerHTML = `<h4 class="lulc-sub">${esc(D.hansen_title)}</h4><p class="small"><strong>${esc(D.hansen_since)}: ${haTxt(since)} ha (${num(since / 100, 2)} ${esc(D.km2)})</strong></p>`
            + `<ul class="lulc-bars">${years.filter((y) => y >= 2016).map((y) => `<li><span class="lulc-bars__name">${y}</span><span class="lulc-bars__val">${haTxt(r.perYear[y])} ha</span><span class="lulc-bars__bar"><span style="width:${(100 * r.perYear[y] / max).toFixed(1)}%;background:${y >= 2021 ? '#7c3aed' : '#a8a29e'}"></span></span></li>`).join('')}</ul>`;
        canvasLayer('dist-hansen', last.grid, (p) => (r.lossyear[p] >= 21 ? HANSEN : null));
        if (map.getLayer('dist')) map.moveLayer('dist');
        hToggle.hidden = false; hLegend.hidden = false; hShow.checked = true;
    });

    const readme = () => {
        const r = last, P = DIST_PARAMS, b = bboxOf(getAoi());
        return [
            `GEOFLORESTA ${DIST_VERSION} - deforestation alerts (optical Sentinel-2 + radar Sentinel-1)`,
            `Generated: ${new Date().toISOString()} at https://geofloresta.com`,
            `Period monitored: ${r.start} to ${r.end}. Area bbox (WGS 84): ${b.map((v) => v.toFixed(5)).join(', ')}. Pixel: ${r.grid.res} m (EPSG:4326 grid).`,
            '',
            'RESULTS',
            `Confirmed: ${r.confHa.toFixed(2)} ha (${(r.confHa / 100).toFixed(4)} km2). Provisional: ${r.provHa.toFixed(2)} ha. Patches: ${r.nPatches}.`,
            `Baseline forest: ${r.forestHa.toFixed(2)} ha. Forest observed at least once in the period: ${(r.coverage * 100).toFixed(1)} %.`,
            `Dates used - optical baseline: ${r.counts.optBase}, optical monitoring: ${r.counts.optMon}, radar baseline: ${r.counts.radBase}, radar monitoring: ${r.counts.radMon}.`,
            r.warnings?.length ? `Warnings: ${r.warnings.join(', ')}` : '',
            '',
            'FILES',
            'alerts.tif - GeoTIFF, UInt16, EPSG:4326, bands: 1 alert_status (0 none, 1 provisional, 2 confirmed); 2 first_detection (YYDDD = (year-2000)*1000 + day of year);',
            '  3 source (1 optical, 2 radar, 3 both); 4 nbr_drop_x1000; 5 forest_baseline (1 forest, 0 non-forest, 255 outside area); 6 optical_clear_observations; 7 radar_observations;',
            '  8 hansen_gfc_v1.13_lossyear (only if the Hansen comparison was run; 1-25 = 2001-2025).',
            'alert_patches.geojson - one polygon per patch (>= 0.5 ha) with area, status, first detection and source.',
            'summary.csv - totals, area by month and (if run) Hansen annual loss in the area.',
            'patches.csv - patch list with centroid coordinates.',
            'validation_points.csv - stratified random sample (alert / forest without alert) to assess accuracy in your area; fill in "verified_as" with high-resolution imagery or field visits and use the stratum areas to compute area-adjusted accuracy (Olofsson et al., 2014, Remote Sensing of Environment 148: 42-57).',
            'scenes.csv - every satellite item used (baseline and monitoring).',
            '',
            'METHOD (GF-DIST, adapted from DIST-ALERT - Hansen, Pickens, Song, UMD-GLAD / NASA OPERA - and RADD - Reiche et al., Wageningen University)',
            `Optical: NBR = (B8 - B12)/(B8 + B12) from Sentinel-2 L2A (SCL classes 4/5, ${P.optical.cloudBufferM} m buffer around clouds and shadows, haze if B2 >= ${P.optical.hazeBlue / 10000}). Baseline = minimum NBR in the same season (month +/- 1) over the ${P.baselineYears} previous years. Anomaly: NBR below that minimum by more than ${P.optical.drop} and SWIR B11 above ${P.optical.swirRatio} x its baseline mean (excludes cloud shadows).`,
            `Radar: Sentinel-1 RTC VH (gamma0, dB) with a spatial mean filter. Baseline = mean and standard deviation in the same season over the ${P.baselineYears} previous years. Anomaly: drop >= ${P.radar.dropDb} dB and z <= -${P.radar.z}.`,
            'Per sensor: 1 anomaly = provisional; 2 consecutive anomalies without a normal observation in between = confirmed.',
            'Fusion: confirmed = optical confirmed OR radar confirmed OR (optical provisional AND radar provisional).',
            `Forest only (baseline mean NBR >= ${P.optical.forestNbr}; without optical data, mean VH >= ${P.radar.forestVhDb} dB). Minimum mapping unit ${P.mmuHa} ha (FAO/EUDR forest definition).`,
            '',
            'KNOWN ACCURACY (browser version, year 2024, confirmed alerts vs Hansen GFC v1.13 loss 2024-2025, 20 m tolerance; precision P / recall R):',
            'Mozambique (Zambezia, miombo) P 0.97 R 0.84; Brazil (Para, Amazon) P 0.99 R 0.90; Paraguay (Chaco, optical only) P 1.00 R 0.48; Cote d\'Ivoire P 0.98 R 0.82; Indonesia (Papua) P 0.98 R 0.97; Indonesia (Kalimantan) P 0.92 R 0.57.',
            'Including provisional alerts, recall rises to 0.86-0.99 (precision 0.70-1.00, lower in very cloudy humid forest).',
            'LIMITATIONS: alerts are signals to verify, not legal proof. Persistent clouds reduce optical detections (radar compensates). In dry forests, dry-season clearings may only be detected at green-up. Tree cover loss is not always deforestation (e.g., fire with regrowth, plantation harvest).',
            '',
            'DATA AND LICENCES: Copernicus Sentinel-2 L2A (ESA) via Earth Search (Element 84, AWS Open Data); Copernicus Sentinel-1 RTC (ESA) via Microsoft Planetary Computer (CC BY 4.0); Hansen/UMD/Google/USGS/NASA Global Forest Change v1.13 (CC BY 4.0). Results: CC BY 4.0, GEOFLORESTA.',
        ].filter((x) => x !== null).join('\n');
    };
    const citation = () => `GEOFLORESTA (${new Date().getFullYear()}). ${DIST_VERSION} deforestation alerts, ${last.start} to ${last.end} [dataset]. Sentinel-2 and Sentinel-1 (Copernicus/ESA). https://geofloresta.com. Licence CC BY 4.0.\n`;
    q('[data-dist-zip]').addEventListener('click', async () => {
        if (!last) return;
        say(D.step_package);
        const r = await call({ cmd: 'package', readme: readme(), citation: citation(), place: '' });
        say('');
        if (r.error) { say(D.failed); return; }
        download(r.name, r.blob);
        window.gtag?.('event', 'file_download', { file_name: r.name, link_text: 'deforestation_zip' });
    });
    q('[data-dist-geojson]').addEventListener('click', async () => {
        if (!last) return;
        const r = await call({ cmd: 'geojson' });
        if (r.error) { say(D.failed); return; }
        const name = `geofloresta_gfdist_${last.start}_${last.end}.geojson`;
        download(name, new Blob([r.text], { type: 'application/geo+json' }));
        window.gtag?.('event', 'file_download', { file_name: name, link_text: 'deforestation_geojson' });
    });

    return {
        reset() {
            run++;
            card.hidden = true; bar.hidden = true; last = null; hansen = null;
            checkArea(); say('');
            removeLayer('dist'); removeLayer('dist-hansen');
        },
    };
}

function download(filename, blob) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.append(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
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
