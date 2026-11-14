/*
 * Relevo (DEM) no navegador: grelha de altitudes a partir dos Terrain Tiles (formato Terrarium),
 * recorte pela área de interesse, curvas de nível e GeoTIFF (WGS 84, Int16).
 * Sem dependências: usado pelo dem-worker.js e testado em Node.
 */

export const TILE_URL = 'https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png';
export const NODATA = -32768;
export const MAX_TILES = 64;   // ~2 048 × 2 048 píxeis no máximo
export const MAX_ZOOM = 12;    // ~38 m no equador
export const MIN_ZOOM = 5;

const R = 6378137;

export function lon2x(lon, z) { return ((lon + 180) / 360) * 2 ** z; }
export function lat2y(lat, z) {
    const s = Math.sin((Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI) / 180);
    return (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * 2 ** z;
}

/** Terrarium: altitude = (R × 256 + G + B / 256) − 32768 */
export function decodeTerrarium(r, g, b) { return r * 256 + g + b / 256 - 32768; }

/** Mosaicos necessários para um bbox, no zoom mais alto que não passa de maxTiles. */
export function planTiles(bbox, maxTiles = MAX_TILES) {
    const [w, s, e, n] = bbox;
    for (let z = MAX_ZOOM; z >= MIN_ZOOM; z--) {
        const x0 = Math.floor(lon2x(w, z)), x1 = Math.floor(lon2x(e, z) - 1e-9);
        const y0 = Math.floor(lat2y(n, z)), y1 = Math.floor(lat2y(s, z) - 1e-9);
        const count = (x1 - x0 + 1) * (y1 - y0 + 1);
        if (count <= maxTiles) return { z, x0, x1, y0, y1, count };
    }
    return null; // área demasiado grande
}

/** Tamanho aproximado do píxel em metros (no centro do bbox). */
export function pixelMeters(z, lat) {
    return (2 * Math.PI * R * Math.cos((lat * Math.PI) / 180)) / (256 * 2 ** z);
}

/**
 * Grelha regular em graus (WGS 84) com o passo do píxel do mosaico no equador,
 * amostrada (bilinear) do mosaico Web Mercator. mosaic: Float32Array (largura mw, altura mh) dos mosaicos [x0..x1]×[y0..y1].
 */
export function buildGrid(bbox, plan, mosaic, mw, mh) {
    const [w, s, e, n] = bbox;
    const step = 360 / (256 * 2 ** plan.z);
    const width = Math.max(1, Math.ceil((e - w) / step));
    const height = Math.max(1, Math.ceil((n - s) / step));
    const data = new Float32Array(width * height);
    const ox = plan.x0 * 256, oy = plan.y0 * 256;
    const px = new Float64Array(width);
    for (let i = 0; i < width; i++) px[i] = lon2x(w + (i + 0.5) * step, plan.z) * 256 - ox - 0.5;
    for (let j = 0; j < height; j++) {
        const py = lat2y(n - (j + 0.5) * step, plan.z) * 256 - oy - 0.5;
        const y0 = Math.max(0, Math.min(mh - 1, Math.floor(py))), y1 = Math.min(mh - 1, y0 + 1), fy = Math.max(0, Math.min(1, py - y0));
        for (let i = 0; i < width; i++) {
            const x0 = Math.max(0, Math.min(mw - 1, Math.floor(px[i]))), x1 = Math.min(mw - 1, x0 + 1), fx = Math.max(0, Math.min(1, px[i] - x0));
            const a = mosaic[y0 * mw + x0], b = mosaic[y0 * mw + x1], c = mosaic[y1 * mw + x0], d = mosaic[y1 * mw + x1];
            data[j * width + i] = (a * (1 - fx) + b * fx) * (1 - fy) + (c * (1 - fx) + d * fx) * fy;
        }
    }
    return { width, height, step, west: w, north: n, data };
}

/** Píxeis fora da área (regra par-ímpar, com buracos e multipolígonos) passam a NaN. */
export function maskGrid(grid, geometry) {
    const rings = geometry.type === 'Polygon' ? geometry.coordinates : geometry.type === 'MultiPolygon' ? geometry.coordinates.flat() : [];
    const { width, height, step, west, north, data } = grid;
    const xs = [];
    for (let j = 0; j < height; j++) {
        const lat = north - (j + 0.5) * step;
        xs.length = 0;
        for (const ring of rings) {
            for (let k = 0, m = ring.length - 1; k < ring.length; m = k++) {
                const [ax, ay] = ring[m], [bx, by] = ring[k];
                if ((ay > lat) !== (by > lat)) xs.push(ax + ((lat - ay) / (by - ay)) * (bx - ax));
            }
        }
        xs.sort((a, b) => a - b);
        const row = j * width;
        let p = 0;
        for (let i = 0; i < width; i++) {
            const lon = west + (i + 0.5) * step;
            while (p < xs.length && xs[p] <= lon) p++;
            if (p % 2 === 0) data[row + i] = NaN;
        }
    }
    return grid;
}

export function stats(grid) {
    let min = Infinity, max = -Infinity, sum = 0, n = 0;
    for (const v of grid.data) {
        if (Number.isNaN(v)) continue;
        if (v < min) min = v;
        if (v > max) max = v;
        sum += v; n++;
    }
    return n ? { min, max, mean: sum / n, cells: n } : null;
}

/**
 * Curvas de nível (quadrados em marcha) em LineStrings WGS 84.
 * Só se percorrem os níveis entre o mínimo e o máximo de cada célula; células com NaN são ignoradas.
 */
export function contours(grid, interval) {
    const { width, height, step, west, north, data } = grid;
    const segs = new Map(); // nível -> lista de segmentos [ax, ay, bx, by] em coordenadas de grelha
    // nível exactamente igual a um vértice cria ambiguidade: desloca-se uma fracção mínima
    const lvl = (v, l) => (v === l ? v + 1e-6 : v);
    for (let j = 0; j < height - 1; j++) {
        for (let i = 0; i < width - 1; i++) {
            const tl = data[j * width + i], tr = data[j * width + i + 1], br = data[(j + 1) * width + i + 1], bl = data[(j + 1) * width + i];
            if (Number.isNaN(tl) || Number.isNaN(tr) || Number.isNaN(br) || Number.isNaN(bl)) continue;
            const lo = Math.min(tl, tr, br, bl), hi = Math.max(tl, tr, br, bl);
            for (let l = Math.ceil(lo / interval) * interval; l <= hi; l += interval) {
                const a = lvl(tl, l), b = lvl(tr, l), c = lvl(br, l), d = lvl(bl, l);
                const idx = (a > l ? 8 : 0) | (b > l ? 4 : 0) | (c > l ? 2 : 0) | (d > l ? 1 : 0);
                if (idx === 0 || idx === 15) continue;
                const top = [i + (l - a) / (b - a), j], right = [i + 1, j + (l - b) / (c - b)];
                const bottom = [i + (l - d) / (c - d), j + 1], left = [i, j + (l - a) / (d - a)];
                const out = segs.get(l) || segs.set(l, []).get(l);
                const add = (p, q) => out.push([p[0], p[1], q[0], q[1]]);
                switch (idx) {
                    case 1: case 14: add(left, bottom); break;
                    case 2: case 13: add(bottom, right); break;
                    case 3: case 12: add(left, right); break;
                    case 4: case 11: add(top, right); break;
                    case 6: case 9: add(top, bottom); break;
                    case 7: case 8: add(left, top); break;
                    case 5: add(left, top); add(bottom, right); break;   // sela
                    case 10: add(top, right); add(left, bottom); break;  // sela
                }
            }
        }
    }
    const toLonLat = (x, y) => [+(west + (x + 0.5) * step).toFixed(6), +(north - (y + 0.5) * step).toFixed(6)];
    const features = [];
    for (const [l, list] of [...segs.entries()].sort((a, b) => a[0] - b[0])) {
        for (const line of joinSegments(list)) {
            if (line.length < 2) continue;
            features.push({ type: 'Feature', properties: { altitude_m: l }, geometry: { type: 'LineString', coordinates: line.map(([x, y]) => toLonLat(x, y)) } });
        }
    }
    return features;
}

/** Junta segmentos que partilham extremidades em linhas contínuas. */
export function joinSegments(segs) {
    const key = (x, y) => `${Math.round(x * 1e6)},${Math.round(y * 1e6)}`;
    const ends = new Map();
    const used = new Uint8Array(segs.length);
    segs.forEach((s, k) => {
        for (const [x, y] of [[s[0], s[1]], [s[2], s[3]]]) {
            const kk = key(x, y);
            (ends.get(kk) || ends.set(kk, []).get(kk)).push(k);
        }
    });
    const next = (x, y) => (ends.get(key(x, y)) || []).find((k) => !used[k]);
    const lines = [];
    for (let k = 0; k < segs.length; k++) {
        if (used[k]) continue;
        used[k] = 1;
        // Cresce para a frente e para trás (sem unshift, que seria lento em linhas longas)
        const grow = (start) => {
            const out = [];
            let p = start;
            for (;;) {
                const m = next(p[0], p[1]);
                if (m === undefined) break;
                used[m] = 1;
                const s = segs[m];
                p = key(s[0], s[1]) === key(p[0], p[1]) ? [s[2], s[3]] : [s[0], s[1]];
                out.push(p);
            }
            return out;
        };
        const fwd = grow([segs[k][2], segs[k][3]]);
        const back = grow([segs[k][0], segs[k][1]]);
        lines.push([...back.reverse(), [segs[k][0], segs[k][1]], [segs[k][2], segs[k][3]], ...fwd]);
    }
    return lines;
}

/** GeoTIFF mínimo: uma banda Int16 sem compressão, EPSG:4326, NoData −32768. */
export function geotiff(grid, description = '') {
    const { width, height, step, west, north, data } = grid;
    const pixels = new Int16Array(width * height);
    for (let k = 0; k < data.length; k++) pixels[k] = Number.isNaN(data[k]) ? NODATA : Math.max(-32767, Math.min(32767, Math.round(data[k])));

    const ascii = (s) => new TextEncoder().encode(s.replace(/[^\x20-\x7e]/g, '?') + '\0');
    const desc = ascii(description || 'DEM');
    const nodata = ascii(String(NODATA));
    const scale = new Float64Array([step, step, 0]);
    const tie = new Float64Array([0, 0, 0, west, north, 0]);
    const geokeys = new Uint16Array([1, 1, 0, 3, 1024, 0, 1, 2, 1025, 0, 1, 1, 2048, 0, 1, 4326]);

    // tag, tipo, contagem, valor (número) ou bytes (dados fora do IFD)
    const tags = [
        [256, 4, 1, width], [257, 4, 1, height], [258, 3, 1, 16], [259, 3, 1, 1], [262, 3, 1, 1],
        [270, 2, desc.length, desc], [273, 4, 1, 0], [277, 3, 1, 1], [278, 4, 1, height], [279, 4, 1, pixels.byteLength],
        [284, 3, 1, 1], [339, 3, 1, 2],
        [33550, 12, 3, new Uint8Array(scale.buffer)], [33922, 12, 6, new Uint8Array(tie.buffer)],
        [34735, 3, geokeys.length, new Uint8Array(geokeys.buffer)], [42113, 2, nodata.length, nodata],
    ];
    const ifdSize = 2 + tags.length * 12 + 4;
    let extraOffset = 8 + ifdSize;
    const extras = [];
    for (const t of tags) {
        if (t[3] instanceof Uint8Array && t[3].length > 4) {
            t.push(extraOffset);
            extras.push([extraOffset, t[3]]);
            extraOffset += t[3].length + (t[3].length % 2);
        }
    }
    const pixelOffset = extraOffset;
    tags.find((t) => t[0] === 273)[3] = pixelOffset;

    const buf = new ArrayBuffer(pixelOffset + pixels.byteLength);
    const dv = new DataView(buf);
    const u8 = new Uint8Array(buf);
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
    for (const [off, bytes] of extras) u8.set(bytes, off);
    new Int16Array(buf, pixelOffset, pixels.length).set(pixels);
    return buf;
}
