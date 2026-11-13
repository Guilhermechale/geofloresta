"""ESA WorldCover 2021 v200 (10 m, CC BY 4.0) — recortes por província de Moçambique + tabela de áreas por classe.

Uso:
    python worldcover_provinces.py --provincias caminho/mz_provincias_naturalearth.gpkg [--so Sofala]

Saída: entregas/worldcover-2021/<provincia>/
    worldcover_2021_<provincia>.tif   (COG, classes originais, 0 = sem dados)
    worldcover_2021_<provincia>_areas.csv
    SHA256SUMS
Fonte: bucket S3 público da ESA (sem conta). Licença: CC BY 4.0 — atribuição obrigatória:
    © ESA WorldCover project 2021 / Contains modified Copernicus Sentinel data (2021) processed by ESA WorldCover consortium
"""
from __future__ import annotations

import argparse

import numpy as np
from osgeo import gdal

from common import OUT, clip_to_province, iter_blocks, manifest, pixel_area_ha, province_names, provinces_gpkg, slug, write_csv

URL = "/vsicurl/https://esa-worldcover.s3.eu-central-1.amazonaws.com/v200/2021/map/ESA_WorldCover_10m_2021_v200_{tile}_Map.tif"

CLASSES = {
    10: "Coberto arbóreo / Tree cover", 20: "Arbustos / Shrubland", 30: "Herbáceo / Grassland",
    40: "Agricultura / Cropland", 50: "Construído / Built-up", 60: "Solo nu ou vegetação esparsa / Bare-sparse vegetation",
    70: "Neve e gelo / Snow and ice", 80: "Água permanente / Permanent water bodies", 90: "Zona húmida herbácea / Herbaceous wetland",
    95: "Mangal / Mangroves", 100: "Musgos e líquenes / Moss and lichen",
}


def tiles_for_mozambique() -> list[str]:
    """Mosaicos de 3°×3° (canto inferior esquerdo) que cobrem Moçambique (30–41 °E, 27–10 °S)."""
    out = []
    for lat in range(-27, -9, 3):
        for lon in range(30, 42, 3):
            ns = f"{'N' if lat >= 0 else 'S'}{abs(lat):02d}"
            out.append(f"{ns}E{lon:03d}")
    return out


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--provincias", help="GeoPackage das províncias (do site)")
    ap.add_argument("--so", help="processar só esta província (nome exacto)")
    args = ap.parse_args()

    gpkg = provinces_gpkg(args.provincias)
    sources = []
    for t in tiles_for_mozambique():
        try:
            gdal.Info(URL.format(tile=t))
            sources.append(URL.format(tile=t))
        except RuntimeError:
            pass  # mosaico só com mar: não existe no bucket
    vrt = str(OUT / "worldcover_moz.vrt")
    OUT.mkdir(parents=True, exist_ok=True)
    gdal.BuildVRT(vrt, sources)

    for name in province_names(gpkg):
        if args.so and name != args.so:
            continue
        folder = OUT / "worldcover-2021" / slug(name)
        tif = clip_to_province(vrt, gpkg, name, folder / f"worldcover_2021_{slug(name)}.tif")
        ds = gdal.Open(str(tif))
        band, gt = ds.GetRasterBand(1), ds.GetGeoTransform()
        totals: dict[int, float] = {}
        for y, n, block in iter_blocks(band):
            areas = pixel_area_ha(gt, y, n)[:, None]
            for c in CLASSES:
                m = block == c
                if m.any():
                    totals[c] = totals.get(c, 0.0) + float((m * areas).sum())
        total = sum(totals.values()) or 1.0
        rows = [[c, CLASSES[c], round(a, 1), round(100 * a / total, 2)] for c, a in sorted(totals.items())]
        write_csv(folder / f"worldcover_2021_{slug(name)}_areas.csv", ["classe", "nome_classe", "area_ha", "percentagem"], rows)
        manifest(folder)
        print(f"{name}: {len(rows)} classes, {total:,.0f} ha")


if __name__ == "__main__":
    main()
