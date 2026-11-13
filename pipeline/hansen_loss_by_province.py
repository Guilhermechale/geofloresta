"""Hansen/UMD Global Forest Change v1.12 — perda de cobertura arbórea 2001–2024 por província e ano.

Obtém os ficheiros na fonte original (UMD/GLAD), não pelo Earth Engine.
ANTES DE ALOJAR: confirmar na página de download da v1.12 que a licença é CC BY 4.0 e o padrão dos URLs.

Uso:
    python hansen_loss_by_province.py --provincias mz_provincias_naturalearth.gpkg [--limiar 30] [--so Niassa]

Saída: entregas/hansen-gfc-v1.12/
    perda_arborea_por_provincia_ano_limiar<L>.csv   (provincia, ano, perda_ha, limiar_copa_pct)
    <provincia>/hansen_lossyear_<provincia>.tif     (COG, 0 = sem perda, 1–24 = ano 2001–2024)
"""
from __future__ import annotations

import argparse

import numpy as np
from osgeo import gdal

from common import OUT, clip_to_province, manifest, pixel_area_ha, province_names, provinces_gpkg, slug, write_csv

VERSION = "GFC-2024-v1.12"
URL = "/vsicurl/https://storage.googleapis.com/earthenginepartners-hansen/{v}/Hansen_{v}_{layer}_{tile}.tif"
# Mosaicos de 10°×10° nomeados pelo canto superior esquerdo
TILES = ["10S_030E", "10S_040E", "20S_030E", "20S_040E"]


def vrt(layer: str) -> str:
    path = str(OUT / f"hansen_{layer}.vrt")
    OUT.mkdir(parents=True, exist_ok=True)
    gdal.BuildVRT(path, [URL.format(v=VERSION, layer=layer, tile=t) for t in TILES])
    return path


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--provincias")
    ap.add_argument("--limiar", type=int, default=30, help="limiar de cobertura de copa em 2000 (%%)")
    ap.add_argument("--so")
    args = ap.parse_args()

    gpkg = provinces_gpkg(args.provincias)
    loss_vrt, cover_vrt = vrt("lossyear"), vrt("treecover2000")
    folder = OUT / f"hansen-{VERSION.lower()}"
    rows = []

    for name in province_names(gpkg):
        if args.so and name != args.so:
            continue
        d = folder / slug(name)
        loss_tif = clip_to_province(loss_vrt, gpkg, name, d / f"hansen_lossyear_{slug(name)}.tif")
        cover_tif = clip_to_province(cover_vrt, gpkg, name, d / f"_treecover2000_{slug(name)}.tif")
        ls, cs = gdal.Open(str(loss_tif)), gdal.Open(str(cover_tif))
        lb, cb, gt = ls.GetRasterBand(1), cs.GetRasterBand(1), ls.GetGeoTransform()
        per_year = np.zeros(25)
        for y in range(0, lb.YSize, 1024):
            n = min(1024, lb.YSize - y)
            loss = lb.ReadAsArray(0, y, lb.XSize, n)
            cover = cb.ReadAsArray(0, y, cb.XSize, n)
            area = np.broadcast_to(pixel_area_ha(gt, y, n)[:, None], loss.shape)
            m = (cover >= args.limiar) & (loss > 0)
            per_year += np.bincount(loss[m], weights=area[m], minlength=25)[:25]
        for i in range(1, 25):
            rows.append([name, 2000 + i, round(float(per_year[i]), 1), args.limiar])
        (d / f"_treecover2000_{slug(name)}.tif").unlink(missing_ok=True)
        manifest(d)
        print(f"{name}: {per_year[1:].sum():,.0f} ha perdidos 2001–2024 (copa ≥ {args.limiar}%)")

    write_csv(folder / f"perda_arborea_por_provincia_ano_limiar{args.limiar}.csv", ["provincia", "ano", "perda_ha", "limiar_copa_pct"], rows)
    manifest(folder)


if __name__ == "__main__":
    main()
