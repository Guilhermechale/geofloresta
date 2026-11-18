"""Prepara os dados do tutorial «Classificação supervisionada com Random Forest» (Chitwan, Nepal).

Lê só a janela da área (por HTTP, blocos COG) e grava rf_chitwan.npz com:
  - mediana Sentinel-2 L2A sem nuvens (4 datas de Out–Nov 2025), bandas B2 B3 B4 B8 B11 B12;
  - altitude (Copernicus DEM GLO-30);
  - ESA WorldCover 2021 (usado como referência para gerar amostras).
Grelha: UTM 45N (EPSG:32645), 20 m.   Uso: python rf_chitwan_data.py [pasta_saida]
"""
import sys
from pathlib import Path

import numpy as np
import rasterio
from rasterio.enums import Resampling
from rasterio.vrt import WarpedVRT
from rasterio.warp import transform_bounds
from rasterio.transform import from_origin

OUT = Path(sys.argv[1] if len(sys.argv) > 1 else '.')
BBOX = (84.25, 27.45, 84.65, 27.75)          # lon/lat: Bharatpur, rio Narayani, Parque Nacional de Chitwan
CRS, RES = 'EPSG:32645', 20
S2 = 'https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/45/R/TL/{y}/{m}/{id}/{b}.tif'
SCENES = ['S2B_45RTL_20251011_0_L2A', 'S2C_45RTL_20251016_0_L2A', 'S2C_45RTL_20251026_0_L2A', 'S2A_45RTL_20251107_0_L2A']
BANDS = ['B02', 'B03', 'B04', 'B08', 'B11', 'B12']
DEM = 'https://copernicus-dem-30m.s3.amazonaws.com/Copernicus_DSM_COG_10_N27_00_E084_00_DEM/Copernicus_DSM_COG_10_N27_00_E084_00_DEM.tif'
WC = 'https://esa-worldcover.s3.eu-central-1.amazonaws.com/v200/2021/map/ESA_WorldCover_10m_2021_v200_N27E084_Map.tif'

l, b, r, t = transform_bounds('EPSG:4326', CRS, *BBOX)
l, b, r, t = (np.floor(l / RES) * RES, np.floor(b / RES) * RES, np.ceil(r / RES) * RES, np.ceil(t / RES) * RES)
W, H = int((r - l) / RES), int((t - b) / RES)
TR = from_origin(l, t, RES, RES)


def read(url, resampling, dtype='float32'):
    with rasterio.Env(GDAL_DISABLE_READDIR_ON_OPEN='EMPTY_DIR', GDAL_HTTP_MAX_RETRY='4'):
        with rasterio.open(url) as src:
            with WarpedVRT(src, crs=CRS, transform=TR, width=W, height=H, resampling=resampling) as v:
                return v.read(1).astype(dtype)


stack = []
for sid in SCENES:
    d = sid.split('_')[2]
    url = lambda band: S2.format(y=d[:4], m=str(int(d[4:6])), id=sid, b=band)
    scl = read(url('SCL'), Resampling.nearest, 'uint8')
    bad = np.isin(scl, [0, 1, 3, 8, 9, 10, 11])       # sem dados, saturado, sombra, nuvens, cirros, neve
    img = np.stack([read(url(bn), Resampling.bilinear) for bn in BANDS])
    bad |= (img == 0).any(axis=0)                      # 0 = sem dados
    img = img / 10000                                  # nestes COG o offset -1000 já vem aplicado (boa_offset_applied)
    img[:, bad] = np.nan
    stack.append(img)
    print(sid, f'{bad.mean():.1%} mascarado', flush=True)
med = np.nanmedian(np.stack(stack), axis=0).astype('float32')
dem = read(DEM, Resampling.bilinear)
wc = read(WC, Resampling.mode, 'uint8')
np.savez_compressed(OUT / 'rf_chitwan.npz', s2=med, dem=dem, wc=wc, transform=np.array(TR)[:6], shape=(H, W))
print('grelha', W, 'x', H, 'píxeis de', RES, 'm')
