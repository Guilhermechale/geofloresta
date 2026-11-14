// Testes do cálculo do relevo no navegador (resources/js/dem-core.js). Correr com: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { planTiles, buildGrid, maskGrid, stats, contours, geotiff, decodeTerrarium, lon2x, lat2y, NODATA } from '../../resources/js/dem-core.js';

// Cone sintético de 1000 m centrado num bbox de 0,5° × 0,5°
function cone() {
    const bbox = [34.5, -20.0, 35.0, -19.5];
    const plan = planTiles(bbox);
    const mw = (plan.x1 - plan.x0 + 1) * 256, mh = (plan.y1 - plan.y0 + 1) * 256, mosaic = new Float32Array(mw * mh);
    const cx = lon2x(34.75, plan.z) * 256 - plan.x0 * 256, cy = lat2y(-19.75, plan.z) * 256 - plan.y0 * 256;
    for (let y = 0; y < mh; y++) for (let x = 0; x < mw; x++) mosaic[y * mw + x] = Math.max(0, 1000 - Math.hypot(x - cx, y - cy) * 2);
    return buildGrid(bbox, plan, mosaic, mw, mh);
}

test('descodifica o formato Terrarium', () => {
    assert.equal(decodeTerrarium(128, 0, 0), 0);
    assert.equal(decodeTerrarium(128, 100, 128), 100.5);
});

test('escolhe o zoom pelo tamanho da área', () => {
    assert.equal(planTiles([34.5, -20, 35, -19.5]).z, 12);
    const country = planTiles([30.2, -26.9, 40.8, -10.5]);
    assert.ok(country.z < 9 && country.count <= 64);
});

test('recorta pela área (fora = NaN) e calcula estatísticas', () => {
    const g = maskGrid(cone(), { type: 'Polygon', coordinates: [[[34.55, -19.95], [34.95, -19.95], [34.95, -19.55], [34.55, -19.55], [34.55, -19.95]]] });
    assert.ok(Number.isNaN(g.data[0]));
    const st = stats(g);
    assert.ok(st.max > 990 && st.min >= 0);
});

test('curvas de nível contínuas e fechadas', () => {
    const f = contours(cone(), 100);
    assert.ok(f.every((x) => x.properties.altitude_m % 100 === 0));
    const c900 = f.filter((x) => x.properties.altitude_m === 900);
    assert.equal(c900.length, 1);
    const c = c900[0].geometry.coordinates;
    assert.ok(Math.hypot(c[0][0] - c.at(-1)[0], c[0][1] - c.at(-1)[1]) < 1e-3);
});

test('GeoTIFF com cabeçalho, georreferência EPSG:4326 e NoData', () => {
    const g = cone();
    g.data[0] = NaN;
    const buf = geotiff(g, 'teste');
    const dv = new DataView(buf);
    assert.equal(dv.getUint16(0), 0x4949);
    assert.equal(dv.getUint16(2, true), 42);
    const n = dv.getUint16(8, true);
    const tags = new Map();
    for (let k = 0; k < n; k++) tags.set(dv.getUint16(10 + k * 12, true), 10 + k * 12);
    assert.equal(dv.getUint32(tags.get(256) + 8, true), g.width);
    assert.equal(dv.getUint32(tags.get(257) + 8, true), g.height);
    const geo = dv.getUint32(tags.get(34735) + 8, true);
    assert.equal(dv.getUint16(geo + 15 * 2, true), 4326);
    const pix = dv.getUint32(tags.get(273) + 8, true);
    assert.equal(dv.getInt16(pix, true), NODATA);
    assert.equal(buf.byteLength, pix + g.width * g.height * 2);
});
