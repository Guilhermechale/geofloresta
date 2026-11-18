"""Figuras e capa do tutorial Random Forest (Chitwan). Lê rf_chitwan.npz, rf_resultado.npz/.json.
Uso: python rf_chitwan_figures.py <pasta_dados> <pasta_fontes_ttf> <pasta_saida> [pt|fr|en]
"""
import json
import sys
from pathlib import Path

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import numpy as np
from matplotlib import font_manager
from matplotlib.colors import ListedColormap
from matplotlib.patches import Patch
from PIL import Image

D, F, O = map(Path, sys.argv[1:4])
LANG = sys.argv[4] if len(sys.argv) > 4 else 'pt'
O.mkdir(parents=True, exist_ok=True)
for f in ('plex400.ttf', 'plex600.ttf', 'newsreader500.ttf'):
    font_manager.fontManager.addfont(str(F / f))
plt.rcParams.update({'font.family': 'IBM Plex Sans', 'font.size': 11, 'axes.edgecolor': '#c9c3b5',
                     'text.color': '#1f2a24', 'axes.labelcolor': '#1f2a24', 'xtick.color': '#4a554e', 'ytick.color': '#4a554e'})

T = {
    'pt': dict(classes=['Floresta', 'Pradaria e matagal', 'Agricultura', 'Área urbana', 'Areia e solo exposto', 'Água'],
               ref='Referência (amostras de validação)', map='Classificação Random Forest', imp='Importância relativa (%)',
               oa='Precisão global', train='treino', val='validação', rgb='Cor natural', fcc='Falsa cor (NIR-SWIR-vermelho)'),
    'fr': dict(classes=['Forêt', 'Prairie et fourrés', 'Agriculture', 'Zone urbaine', 'Sable et sol nu', 'Eau'],
               ref='Référence (échantillons de validation)', map='Classification Random Forest', imp='Importance relative (%)',
               oa='Précision globale', train='apprentissage', val='validation', rgb='Couleurs naturelles', fcc='Fausses couleurs (PIR-SWIR-rouge)'),
    'en': dict(classes=['Forest', 'Grassland and shrubland', 'Cropland', 'Built-up', 'Sand and bare soil', 'Water'],
               ref='Reference (validation samples)', map='Random Forest classification', imp='Relative importance (%)',
               oa='Overall accuracy', train='training', val='validation', rgb='Natural colour', fcc='False colour (NIR-SWIR-red)'),
}[LANG]
COLORS = ['#1f6b3a', '#a9c25d', '#f2c14e', '#d1495b', '#d8c8a8', '#2f7fc1']
CMAP = ListedColormap(['#000000'] + COLORS)

d = np.load(D / 'rf_chitwan.npz')
r = np.load(D / 'rf_resultado.npz')
res = json.loads((D / 'rf_resultado.json').read_text())
s2 = d['s2']


def stretch(a, lo=2, hi=98, g=0.9):
    p1, p2 = np.nanpercentile(a, [lo, hi])
    return np.clip((a - p1) / (p2 - p1), 0, 1) ** g


rgb = np.dstack([stretch(s2[2]), stretch(s2[1]), stretch(s2[0])])
fcc = np.dstack([stretch(s2[3]), stretch(s2[4]), stretch(s2[2])])
cls = r['cls']


def save(fig, name, w=1600):
    png = O / f'{name}.png'
    fig.savefig(png, dpi=200, facecolor='white')
    plt.close(fig)
    im = Image.open(png).convert('RGB')
    if im.width > w:
        im = im.resize((w, round(im.height * w / im.width)), Image.LANCZOS)
    im.save(O / f'{name}.webp', 'WEBP', quality=85, method=6)
    png.unlink()


def legend(ax, **kw):
    ax.legend(handles=[Patch(color=c, label=n) for c, n in zip(COLORS, T['classes'])], frameon=False, **kw)


# 1. Composição Sentinel-2 em cor natural e falsa cor
fig, axs = plt.subplots(1, 2, figsize=(10, 4.6))
for ax, im, t in zip(axs, (rgb, fcc), (T['rgb'], T['fcc'])):
    ax.imshow(im); ax.set_title(t, fontsize=12, fontweight=600); ax.axis('off')
fig.tight_layout(); save(fig, 'fig1-composicao-sentinel2')

# 2. Amostras de treino e validação sobre a imagem
fig, ax = plt.subplots(figsize=(8, 7))
ax.imshow(rgb, alpha=0.9); ax.axis('off')
for c in range(1, 7):
    m = r['ys'] == c
    ax.scatter(r['cols'][m & r['train']], r['rows'][m & r['train']], s=9, c=COLORS[c - 1], edgecolors='white', linewidths=0.4)
    ax.scatter(r['cols'][m & ~r['train']], r['rows'][m & ~r['train']], s=11, c=COLORS[c - 1], marker='^', edgecolors='black', linewidths=0.4)
h = [Patch(color=c, label=n) for c, n in zip(COLORS, T['classes'])]
h += [plt.Line2D([], [], marker='o', ls='', color='grey', label=T['train']), plt.Line2D([], [], marker='^', ls='', color='grey', markeredgecolor='black', label=T['val'])]
ax.legend(handles=h, loc='upper center', bbox_to_anchor=(0.5, -0.01), ncol=4, frameon=False, fontsize=10)
fig.tight_layout(); save(fig, 'fig2-amostras')

# 3. Mapa classificado
fig, ax = plt.subplots(figsize=(8, 7.3))
ax.imshow(cls, cmap=CMAP, vmin=0, vmax=6, interpolation='nearest'); ax.axis('off')
ax.set_title(T['map'], fontsize=13, fontweight=600)
legend(ax, loc='upper center', bbox_to_anchor=(0.5, -0.01), ncol=3, fontsize=10)
fig.tight_layout(); save(fig, 'fig3-mapa-classificado')

# 4. Matriz de confusão e importância das variáveis
cm = np.array(res['confusion'])
fig, (a1, a2) = plt.subplots(1, 2, figsize=(12, 5.2), gridspec_kw={'width_ratios': [1.15, 1]})
a1.imshow(cm / cm.sum(1, keepdims=True), cmap='Greens', vmin=0, vmax=1)
for i in range(6):
    for j in range(6):
        if cm[i, j]:
            a1.text(j, i, cm[i, j], ha='center', va='center', fontsize=11, color='white' if i == j else '#1f2a24', fontweight=600 if i == j else 400)
short = [n if len(n) < 13 else n.split(' ')[0] + '…' for n in T['classes']]
a1.set_xticks(range(6), short, rotation=35, ha='right'); a1.set_yticks(range(6), short)
a1.set_ylabel(T['ref']); a1.set_xlabel(T['map'])
a1.set_title(f"{T['oa']} {res['overall_accuracy']*100:.1f} %  ·  Kappa {res['kappa']:.2f}".replace('.', ',' if LANG != 'en' else '.'), fontsize=12, fontweight=600)
imp = sorted(res['importance'].items(), key=lambda kv: kv[1])
a2.barh([k for k, _ in imp], [v * 100 for _, v in imp], color='#2c6e49')
for i, (_, v) in enumerate(imp):
    a2.text(v * 100 + 0.3, i, f'{v*100:.1f}'.replace('.', ',' if LANG != 'en' else '.'), va='center', fontsize=10)
a2.set_xlabel(T['imp']); a2.spines[['top', 'right']].set_visible(False)
fig.tight_layout(); save(fig, 'fig4-precisao-importancia')

# Capa 1600×900: imagem de satélite que passa a mapa classificado ao longo de uma diagonal
if LANG == 'pt':
    H, W = cls.shape
    y0 = int(H * 0.05); h = int(W * 9 / 16)
    if y0 + h > H:
        h = H - y0
        w = int(h * 16 / 9)
    else:
        w = W
    x0 = (W - w) // 2
    sat = np.dstack([stretch(s2[2], 1, 99, 0.8), stretch(s2[1], 1, 99, 0.8), stretch(s2[0], 1, 99, 0.8)])[y0:y0 + h, x0:x0 + w]
    cm_rgb = np.array([[0, 0, 0]] + [[int(c[i:i + 2], 16) / 255 for i in (1, 3, 5)] for c in COLORS])[cls[y0:y0 + h, x0:x0 + w]]
    sat = np.array(Image.fromarray((sat * 255).astype('uint8')).resize((1600, 900), Image.LANCZOS)) / 255
    cm_rgb = np.array(Image.fromarray((cm_rgb * 255).astype('uint8')).resize((1600, 900), Image.NEAREST)) / 255
    yy, xx = np.mgrid[0:900, 0:1600]
    t = np.clip(((xx - 640) + (yy - 450) * 0.45) / 90 + 0.5, 0, 1)[..., None]   # transição suave na diagonal
    out = sat * (1 - t) + (0.82 * cm_rgb + 0.18 * sat) * t
    edge = np.exp(-(((xx - 640) + (yy - 450) * 0.45) / 6) ** 2)[..., None]
    out = out * (1 - 0.85 * edge) + 0.85 * edge
    Image.fromarray((np.clip(out, 0, 1) * 255).astype('uint8')).save(O / 'random-forest.webp', 'WEBP', quality=84, method=6)
print('ok', sorted(p.name for p in O.iterdir()))
