/*
 * Trabalhador do relevo: descarrega os mosaicos Terrarium da área, monta a grelha (WGS 84),
 * recorta pela área de interesse e gera o GeoTIFF e as curvas de nível, sem bloquear a página.
 */
import { TILE_URL, planTiles, decodeTerrarium, buildGrid, maskGrid, stats, contours, geotiff, pixelMeters } from './dem-core.js';

let grid = null;
let meta = null;

async function tile(z, x, y) {
    const r = await fetch(TILE_URL.replace('{z}', z).replace('{x}', x).replace('{y}', y));
    if (!r.ok) throw new Error(`tile ${z}/${x}/${y}: ${r.status}`);
    const bmp = await createImageBitmap(await r.blob(), { premultiplyAlpha: 'none', colorSpaceConversion: 'none' });
    const c = new OffscreenCanvas(256, 256);
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(bmp, 0, 0);
    return ctx.getImageData(0, 0, 256, 256).data;
}

async function build({ bbox, geometry, maxTiles }) {
    const plan = planTiles(bbox, maxTiles);
    if (!plan) return { error: 'too_big' };
    const cols = plan.x1 - plan.x0 + 1, rows = plan.y1 - plan.y0 + 1;
    const mw = cols * 256, mh = rows * 256;
    const mosaic = new Float32Array(mw * mh);
    const jobs = [];
    for (let ty = plan.y0; ty <= plan.y1; ty++) for (let tx = plan.x0; tx <= plan.x1; tx++) jobs.push([tx, ty]);
    let done = 0;
    // Até 6 pedidos em simultâneo
    const run = async () => {
        while (jobs.length) {
            const [tx, ty] = jobs.shift();
            const px = await tile(plan.z, tx, ty);
            const ox = (tx - plan.x0) * 256, oy = (ty - plan.y0) * 256;
            for (let y = 0; y < 256; y++) {
                for (let x = 0; x < 256; x++) {
                    const k = (y * 256 + x) * 4;
                    mosaic[(oy + y) * mw + ox + x] = decodeTerrarium(px[k], px[k + 1], px[k + 2]);
                }
            }
            postMessage({ progress: ++done, total: plan.count });
        }
    };
    await Promise.all(Array.from({ length: Math.min(6, plan.count) }, run));

    grid = maskGrid(buildGrid(bbox, plan, mosaic, mw, mh), geometry);
    const st = stats(grid);
    if (!st) return { error: 'empty' };
    meta = { z: plan.z, tiles: plan.count, width: grid.width, height: grid.height, resolution_m: Math.round(pixelMeters(plan.z, (bbox[1] + bbox[3]) / 2)), ...st };
    return { meta };
}

onmessage = async ({ data: msg }) => {
    try {
        if (msg.cmd === 'build') {
            postMessage({ id: msg.id, ...(await build(msg)) });
        } else if (msg.cmd === 'tiff') {
            const buf = geotiff(grid, msg.description);
            postMessage({ id: msg.id, buffer: buf }, [buf]);
        } else if (msg.cmd === 'contours') {
            const features = contours(grid, msg.interval);
            postMessage({ id: msg.id, text: JSON.stringify({ type: 'FeatureCollection', ...msg.props, features }), count: features.length });
        }
    } catch (e) {
        postMessage({ id: msg.id, error: 'failed', detail: String(e?.message || e) });
    }
};
