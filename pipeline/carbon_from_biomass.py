"""Stock de carbono a partir de um mapa de biomassa aérea (AGB, Mg/ha) e do respectivo desvio-padrão.

Método (publicado na ficha "Biomassa aérea e stock de carbono"):
    C   = AGB × CF,             CF = 0,47 (IPCC 2006, vol. 4, cap. 4)
    σC  = sqrt((CF·σAGB)² + (AGB·σCF)²),   σCF = 0,025 (amplitude 0,44–0,49 tratada como ±1σ aproximado)
    CO2e = C × 44/12
Só se publicam os produtos se a licença da biomassa de origem permitir derivados (ver docs/DADOS-E-LICENCAS.md).

Uso:
    python carbon_from_biomass.py --agb agb.tif --sd agb_sd.tif --provincias mz_provincias_naturalearth.gpkg
"""
from __future__ import annotations

import argparse

import numpy as np
from osgeo import gdal

from common import OUT, clip_to_province, manifest, pixel_area_ha, province_names, provinces_gpkg, slug, write_csv

CF, SIGMA_CF = 0.47, 0.025


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--agb", required=True)
    ap.add_argument("--sd", required=True)
    ap.add_argument("--provincias")
    args = ap.parse_args()
    gpkg = provinces_gpkg(args.provincias)
    folder = OUT / "carbono"
    rows = []
    for name in province_names(gpkg):
        d = folder / slug(name)
        agb = gdal.Open(str(clip_to_province(args.agb, gpkg, name, d / f"_agb_{slug(name)}.tif", "bilinear")))
        sd = gdal.Open(str(clip_to_province(args.sd, gpkg, name, d / f"_sd_{slug(name)}.tif", "bilinear")))
        ab, sb, gt = agb.GetRasterBand(1), sd.GetRasterBand(1), agb.GetGeoTransform()
        total_c, var_c = 0.0, 0.0
        for y in range(0, ab.YSize, 1024):
            n = min(1024, ab.YSize - y)
            a = ab.ReadAsArray(0, y, ab.XSize, n).astype("float64")
            s = sb.ReadAsArray(0, y, sb.XSize, n).astype("float64")
            area = pixel_area_ha(gt, y, n)[:, None]
            valid = a > 0
            c = a * CF
            sc = np.sqrt((CF * s) ** 2 + (a * SIGMA_CF) ** 2)
            total_c += float((c * area)[valid].sum())
            var_c += float(((sc * area) ** 2)[valid].sum())  # erros independentes entre píxeis (limite inferior)
        rows.append([name, round(total_c / 1e6, 3), round(var_c ** 0.5 / 1e6, 3), round(total_c * 44 / 12 / 1e6, 3)])
        for tmp in d.glob("_*.tif"):
            tmp.unlink()
        print(f"{name}: {total_c / 1e6:,.2f} Mt C")
    write_csv(folder / "carbono_por_provincia.csv", ["provincia", "carbono_mt_c", "sigma_mt_c", "co2e_mt"], rows)
    manifest(folder)


if __name__ == "__main__":
    main()
