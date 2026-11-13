"""Funções partilhadas do pipeline GEOFLORESTA (Python 3.10+, GDAL >= 3.6, numpy).

Os rasters lêem-se por HTTP com /vsicurl/ (o GDAL só descarrega os blocos necessários),
são recortados por província e gravados como COG em entregas/.
"""
from __future__ import annotations

import csv
import hashlib
import math
import os
import urllib.request
from pathlib import Path

import numpy as np
from osgeo import gdal, ogr

gdal.UseExceptions()
gdal.SetConfigOption("GDAL_DISABLE_READDIR_ON_OPEN", "EMPTY_DIR")
gdal.SetConfigOption("CPL_VSIL_CURL_ALLOWED_EXTENSIONS", ".tif")
gdal.SetConfigOption("GDAL_HTTP_MAX_RETRY", "4")

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "entregas"
CACHE = ROOT / "cache"

# Limites das províncias publicados no próprio site (Natural Earth, domínio público)
PROVINCES_URL = "https://geofloresta.com/descarregar/{id}/mz_provincias_naturalearth_gpkg.zip"


def provinces_gpkg(path: str | None) -> Path:
    """Devolve o GeoPackage das províncias (caminho local ou o ZIP descarregado do site e extraído)."""
    if path:
        return Path(path)
    p = CACHE / "mz_provincias_naturalearth.gpkg"
    if not p.exists():
        raise SystemExit(
            "Indique --provincias com o ficheiro mz_provincias_naturalearth.gpkg "
            "(descarregue-o da ficha 'Províncias de Moçambique' em geofloresta.com/dados)."
        )
    return p


def province_names(gpkg: Path) -> list[str]:
    ds = ogr.Open(str(gpkg))
    layer = ds.GetLayer(0)
    return [f.GetField("nome") for f in layer]


def slug(s: str) -> str:
    import unicodedata
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return "-".join("".join(c.lower() if c.isalnum() else " " for c in s).split())


def clip_to_province(src: str, gpkg: Path, name: str, out: Path, resampling: str = "near") -> Path:
    """Recorta um raster (ou VRT) pelo polígono da província e grava COG comprimido."""
    out.parent.mkdir(parents=True, exist_ok=True)
    gdal.Warp(
        str(out), src,
        format="COG",
        cutlineDSName=str(gpkg),
        cutlineWhere=f"nome = '{name.replace(chr(39), chr(39) * 2)}'",
        cropToCutline=True,
        dstNodata=0,
        resampleAlg=resampling,
        multithread=True,
        creationOptions=["COMPRESS=DEFLATE", "PREDICTOR=2", "BIGTIFF=IF_SAFER", "OVERVIEWS=AUTO"],
    )
    return out


def pixel_area_ha(gt, row: int, nrows: int = 1) -> np.ndarray:
    """Área (ha) de cada linha de píxeis em graus, na latitude da linha (aproximação esférica WGS 84)."""
    lat = gt[3] + (np.arange(row, row + nrows) + 0.5) * gt[5]
    dx_m = abs(gt[1]) * 111_320.0 * np.cos(np.radians(lat))
    dy_m = abs(gt[5]) * 110_574.0
    return dx_m * dy_m / 10_000.0


def iter_blocks(band, rows: int = 1024):
    ysize, xsize = band.YSize, band.XSize
    for y in range(0, ysize, rows):
        n = min(rows, ysize - y)
        yield y, n, band.ReadAsArray(0, y, xsize, n)


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def write_csv(path: Path, header: list[str], rows: list[list]) -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f)
        w.writerow(header)
        w.writerows(rows)
    return path


def manifest(folder: Path) -> None:
    """Escreve SHA256SUMS na pasta de entrega (verificação antes de enviar por SSH)."""
    lines = [f"{sha256(p)}  {p.name}" for p in sorted(folder.glob("*")) if p.is_file() and p.name != "SHA256SUMS"]
    (folder / "SHA256SUMS").write_text("\n".join(lines) + "\n", encoding="utf-8")
