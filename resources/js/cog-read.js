/*
 * Leitura de imagens COG (Sentinel-2, Sentinel-1 RTC...) para uma grelha WGS 84, partilhada pelos trabalhadores
 * do uso do solo e do detector de desmatamento.
 */
import { fromUrl } from 'geotiff';
import { allowSparseTiles, toUtm } from './lulc-core.js';

// ---------- mapeamento píxel -> UTM (grelha grossa interpolada: rápido e preciso) ----------
export function utmMapper(grid, epsg, step = 16) {
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
        const a = Math.max(0, Math.min(cw - 2, Math.floor(i / step))), b = Math.max(0, Math.min(ch - 2, Math.floor(j / step)));
        const fa = i / step - a, fb = j / step - b;
        const k = b * cw + a;
        out[0] = (gx[k] * (1 - fa) + gx[k + 1] * fa) * (1 - fb) + (gx[k + cw] * (1 - fa) + gx[k + cw + 1] * fa) * fb;
        out[1] = (gy[k] * (1 - fa) + gy[k + 1] * fa) * (1 - fb) + (gy[k + cw] * (1 - fa) + gy[k + cw + 1] * fa) * fb;
    };
}

/** Lê a banda de um COG para a grelha (vizinho mais próximo), usando a visão geral com resolução mais adequada. */
export async function readToGrid(href, grid, map, nodataTo = NaN, opts = {}) {
    // Falhas passageiras de rede (ou o servidor a pedir para abrandar) deixam blocos em falta e a geotiff.js
    // não repete: repetimos a leitura inteira até 3 vezes, com pausa crescente.
    let err;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            return await readToGridOnce(href, grid, map, nodataTo, opts);
        } catch (e) {
            err = e;
            await new Promise((r) => setTimeout(r, 600 * (attempt + 1) ** 2));
        }
    }
    throw err;
}

async function readToGridOnce(href, grid, map, nodataTo = NaN, { supersample = 1, finer = 1 } = {}) {
    const tiff = await fromUrl(href, { allowFullFile: false, cacheSize: 2000 });
    const n = await tiff.getImageCount();
    let img = allowSparseTiles(await tiff.getImage(0));
    const base = img;
    for (let k = 1; k < n; k++) {
        const cand = allowSparseTiles(await tiff.getImage(k));
        const res = base.getWidth() / cand.getWidth() * base.getResolution()[0];
        if (res <= grid.res / finer + 0.1) img = cand; else break;
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
    if (supersample > 1) {
        // média de s x s sub-píxeis por célula (radar: reduz o ruído «speckle» sem perder a posição)
        const s = supersample;
        for (let j = 0; j < H; j++) {
            for (let i = 0; i < W; i++) {
                let sum = 0, n = 0;
                for (let b = 0; b < s; b++) {
                    for (let a = 0; a < s; a++) {
                        map(i - 0.5 + (a + 0.5) / s, j - 0.5 + (b + 0.5) / s, p);
                        const c = Math.floor((p[0] - ox) / rx) - c0, r = Math.floor((oy - p[1]) / ry) - r0;
                        if (c < 0 || r < 0 || c >= ww || r >= r1 - r0) continue;
                        const v = data[r * ww + c];
                        if (v !== 0 && v === v) { sum += v; n++; }
                    }
                }
                if (n) out[j * W + i] = sum / n;
            }
        }
        return out;
    }
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

