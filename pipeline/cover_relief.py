# Desenha a capa 1600×900 (cores por altitude, sombreamento, curvas de 100 m e de 500 m) a partir de chim.npy.
import numpy as np, matplotlib; matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.colors import LinearSegmentedColormap, LightSource
from scipy.ndimage import zoom, gaussian_filter
from PIL import Image
D=np.load('chim.npy').astype(float)
W,H=1600,900
D=zoom(D,(H/D.shape[0],W/D.shape[1]),order=3)
Ds=gaussian_filter(D,1.2); Dc=gaussian_filter(D,3.0)
cmap=LinearSegmentedColormap.from_list('gf',[(0,'#123a24'),(0.12,'#1f5a35'),(0.28,'#4f8a46'),(0.45,'#a7b65f'),(0.6,'#d8c37a'),(0.75,'#b9824a'),(0.88,'#7d4e33'),(1,'#f1e9d6')])
ls=LightSource(azdeg=315,altdeg=35)
rgb=ls.shade(Ds,cmap=cmap,blend_mode='soft',vert_exag=2.2,dx=33,dy=33,vmin=np.percentile(Ds,1),vmax=Ds.max())
fig=plt.figure(figsize=(W/100,H/100),dpi=100); ax=fig.add_axes([0,0,1,1]); ax.axis('off')
ax.imshow(rgb,interpolation='bilinear')
lv=np.arange(200,2500,100); ix=lv[lv%500==0]
ax.contour(Dc,levels=lv,colors='#fff8e6',linewidths=0.6,alpha=0.6)
ax.contour(Dc,levels=ix,colors='#fff8e6',linewidths=1.6,alpha=0.9)
# vinheta suave para dar profundidade
yy,xx=np.mgrid[0:H,0:W]; r=np.hypot((xx-W/2)/(W/2),(yy-H/2)/(H/2)); v=np.clip((r-0.55)/0.6,0,1)**1.6
ax.imshow(np.dstack([np.zeros((H,W,3)),v*0.45]),interpolation='bilinear')
ax.set_xlim(0,W); ax.set_ylim(H,0)
fig.savefig('cover.png',dpi=100); plt.close(fig)
Image.open('cover.png').convert('RGB').save('curvas-de-nivel.webp','WEBP',quality=84,method=6)
import os; print('webp KB', os.path.getsize('curvas-de-nivel.webp')//1024, Image.open('curvas-de-nivel.webp').size)
