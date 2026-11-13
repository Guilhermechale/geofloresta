/*
 * Monitor de incêndios — MapLibre GL JS (empacotado), OpenFreeMap (positron/dark), NASA GIBS, NASA FIRMS (cache local).
 * Área de interesse: província, distrito ou polígono desenhado; cálculos no navegador com Turf.js.
 */
import maplibregl from 'maplibre-gl';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import area from '@turf/area';

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

    // Limites
    const line = css('--c-moss') || '#3E6B4A';
    if (provinces && !map.getSource('provinces')) {
        map.addSource('provinces', { type: 'geojson', data: provinces });
        map.addLayer({ id: 'provinces', type: 'line', source: 'provinces', layout: { visibility: s.provinces ? 'visible' : 'none' }, paint: { 'line-color': line, 'line-width': 1.2 } });
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
    const r = await fetch(url, { credentials: 'same-origin' });
    return r.ok ? r.json() : null;
}

async function init() {
    const map = new maplibregl.Map({
        container: el,
        style: cfg.styles[theme()],
        bounds: cfg.bounds,
        fitBoundsOptions: { padding: 20 },
        maxBounds: [[20, -35], [50, -2]],
        attributionControl: false,
        cooperativeGestures: matchMedia('(pointer: coarse)').matches,
        locale: {},
    });
    map.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
    // O estilo OpenFreeMap já traz a sua atribuição; aqui só se acrescentam as fontes NASA
    map.addControl(new maplibregl.AttributionControl({ compact: false, customAttribution: cfg.attribution }), 'bottom-right');
    el.querySelector('.map-loading')?.remove();

    [fires, provinces, districts] = await Promise.all([loadJson(cfg.fires), loadJson(cfg.provinces), loadJson(cfg.districts)]);
    if (fires) fires.features.forEach((f) => { f.properties.age = Math.max(0, hoursAgo(f.properties.acq)); });

    // O estilo pode ter carregado antes ou depois dos dados: tenta até estar pronto
    const ready = () => {
        if (!map.isStyleLoaded()) { map.once('styledata', ready); return; }
        addOverlays(map);
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
            `<dl class="popup-dl">${row(tt.when, p.acq.replace('T', ' ').replace(':00Z', ''))}${row(tt.sat, p.sat)}${row(tt.conf, (T.conf || {})[p.conf] || p.conf)}${p.frp ? row(tt.frp, p.frp) : ''}${row(tt.where, [p.dist, p.prov].filter(Boolean).join(', '))}</dl>`,
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

    const setAoi = (feature) => {
        aoi = feature;
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

    provSel.addEventListener('change', () => {
        const name = provSel.value;
        distSel.innerHTML = distSel.options[0].outerHTML;
        distSel.disabled = !name;
        if (!name) { setAoi(null); return; }
        (districts?.features || []).filter((f) => f.properties.provincia === name)
            .sort((a, b) => a.properties.nome.localeCompare(b.properties.nome, cfg.locale))
            .forEach((f) => distSel.add(new Option(f.properties.nome, f.properties.nome)));
        setAoi((provinces?.features || []).find((f) => f.properties.nome === name) || null);
    });
    distSel.addEventListener('change', () => {
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
        provSel.value = ''; distSel.value = ''; distSel.disabled = true;
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
    btnClear.addEventListener('click', () => { provSel.value = ''; distSel.value = ''; distSel.disabled = true; setAoi(null); });
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
        const head = 'data_hora_utc,satelite,confianca,frp_mw,provincia,distrito,longitude,latitude';
        const rows = aoiFires.map((f) => {
            const p = f.properties; const [x, y] = f.geometry.coordinates;
            return [p.acq, p.sat, p.conf, p.frp ?? '', p.prov, p.dist, x, y].map((v) => /[",]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : v).join(',');
        });
        save(`focos_area_${stamp()}.csv`, 'text/csv;charset=utf-8', '﻿' + [head, ...rows].join('\n') + '\n');
    });
    q('[data-aoi-geojson]').addEventListener('click', () => {
        const fc = { type: 'FeatureCollection', source: 'NASA FIRMS via GEOFLORESTA', updated_at: cfg.updatedAt, area: aoi, features: aoiFires.map(({ type, geometry, properties: { age, ...p } }) => ({ type, geometry, properties: p })) };
        save(`focos_area_${stamp()}.geojson`, 'application/geo+json', JSON.stringify(fc));
    });
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
