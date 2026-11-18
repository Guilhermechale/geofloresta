"""Random Forest em Chitwan (Nepal): reproduz localmente, com scikit-learn, o que o tutorial faz no Earth Engine.

Lê rf_chitwan.npz (de rf_chitwan_data.py), gera amostras estratificadas a partir do ESA WorldCover
(só píxeis no centro de janelas 5×5 homogéneas), treina 100 árvores com 70 % das amostras, valida
com os outros 30 % e classifica a área toda. Grava rf_resultado.npz e rf_resultado.json.
Uso: python rf_chitwan_classify.py [pasta]
"""
import json
import sys
from pathlib import Path

import numpy as np
from scipy.ndimage import maximum_filter, minimum_filter
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import cohen_kappa_score, confusion_matrix

D = Path(sys.argv[1] if len(sys.argv) > 1 else '.')
CLASSES = ['Floresta', 'Pradaria e matagal', 'Agricultura', 'Área urbana', 'Areia e solo exposto', 'Água']
WC_TO = {10: 1, 20: 2, 30: 2, 40: 3, 50: 4, 60: 5, 80: 6}
N_PER_CLASS, SEED, RES = 250, 42, 20

d = np.load(D / 'rf_chitwan.npz')
b2, b3, b4, b8, b11, b12 = d['s2']
dem = d['dem']
gy, gx = np.gradient(dem, RES)
slope = np.degrees(np.arctan(np.hypot(gx, gy)))
ndvi = (b8 - b4) / (b8 + b4)
ndwi = (b3 - b8) / (b3 + b8)
NAMES = ['B2', 'B3', 'B4', 'B8', 'B11', 'B12', 'NDVI', 'NDWI', 'elevation', 'slope']
X = np.stack([b2, b3, b4, b8, b11, b12, ndvi, ndwi, dem, slope]).astype('float32')
H, W = dem.shape

lab = np.zeros(d['wc'].shape, 'uint8')
for k, v in WC_TO.items():
    lab[d['wc'] == k] = v
homog = (maximum_filter(lab, 5) == minimum_filter(lab, 5)) & (lab > 0)
homog[:3, :] = homog[-3:, :] = homog[:, :3] = homog[:, -3:] = False

rng = np.random.default_rng(SEED)
rows, cols, ys = [], [], []
for c in range(1, 7):
    idx = np.flatnonzero(homog & (lab == c))
    pick = rng.choice(idx, size=min(N_PER_CLASS, idx.size), replace=False)
    r, cc = np.unravel_index(pick, (H, W))
    rows += list(r); cols += list(cc); ys += [c] * len(pick)
rows, cols, ys = map(np.array, (rows, cols, ys))
feat = X[:, rows, cols].T
train = rng.random(len(ys)) < 0.7                 # como randomColumn() < 0.7 no Earth Engine

rf = RandomForestClassifier(n_estimators=100, max_features='sqrt', random_state=SEED, n_jobs=-1)
rf.fit(feat[train], ys[train])
pred_val = rf.predict(feat[~train])
cm = confusion_matrix(ys[~train], pred_val, labels=range(1, 7))
oa = np.trace(cm) / cm.sum()
kappa = cohen_kappa_score(ys[~train], pred_val)
producers = np.diag(cm) / cm.sum(1)               # acerto do produtor (por linha: referência)
consumers = np.diag(cm) / cm.sum(0)               # acerto do utilizador (por coluna: mapa)

flat = X.reshape(len(NAMES), -1).T
ok = np.isfinite(flat).all(1)
cls = np.zeros(H * W, 'uint8')
cls[ok] = rf.predict(flat[ok])
cls = cls.reshape(H, W)
area_ha = {CLASSES[c - 1]: round(float((cls == c).sum() * RES * RES / 1e4)) for c in range(1, 7)}
agree = float((cls[lab > 0] == lab[lab > 0]).mean())

res = {
    'classes': CLASSES, 'features': NAMES,
    'n_samples': int(len(ys)), 'n_train': int(train.sum()), 'n_val': int((~train).sum()),
    'confusion': cm.tolist(), 'overall_accuracy': round(float(oa), 4), 'kappa': round(float(kappa), 4),
    'producers': [round(float(x), 3) for x in producers], 'consumers': [round(float(x), 3) for x in consumers],
    'importance': dict(zip(NAMES, [round(float(x), 4) for x in rf.feature_importances_])),
    'area_ha': area_ha, 'agreement_worldcover': round(agree, 4),
}
np.savez_compressed(D / 'rf_resultado.npz', cls=cls, rows=rows, cols=cols, ys=ys, train=train)
(D / 'rf_resultado.json').write_text(json.dumps(res, ensure_ascii=False, indent=1))
print(json.dumps(res, ensure_ascii=False, indent=1))
