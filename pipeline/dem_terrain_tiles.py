# Capa do tutorial «Curvas de nível em 2 minutos»: relevo real da serra de Chimanimani (Terrain Tiles).
# Uso: python3 dem.py 32.80 -19.98 33.30 -19.70 12 chim.npy && python3 cover_relief.py
import io, math, sys, urllib.request, numpy as np
from PIL import Image
def lon2x(lon,z): return (lon+180)/360*2**z
def lat2y(lat,z):
    s=math.sin(math.radians(lat)); return (0.5-math.log((1+s)/(1-s))/(4*math.pi))*2**z
def fetch(bbox, z):
    w,s,e,n=bbox; x0,x1=int(lon2x(w,z)),int(lon2x(e,z)); y0,y1=int(lat2y(n,z)),int(lat2y(s,z))
    M=np.zeros(((y1-y0+1)*256,(x1-x0+1)*256),np.float32)
    for ty in range(y0,y1+1):
        for tx in range(x0,x1+1):
            b=urllib.request.urlopen(f"https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{tx}/{ty}.png").read()
            a=np.asarray(Image.open(io.BytesIO(b)).convert('RGB')).astype(np.float32)
            M[(ty-y0)*256:(ty-y0+1)*256,(tx-x0)*256:(tx-x0+1)*256]=a[...,0]*256+a[...,1]+a[...,2]/256-32768
    # recorte exacto ao bbox (píxeis Web Mercator)
    px0=int((lon2x(w,z)-x0)*256); px1=int((lon2x(e,z)-x0)*256); py0=int((lat2y(n,z)-y0)*256); py1=int((lat2y(s,z)-y0)*256)
    return M[py0:py1,px0:px1]
if __name__=='__main__':
    bbox=[float(v) for v in sys.argv[1:5]]; z=int(sys.argv[5]); D=fetch(bbox,z); np.save(sys.argv[6],D)
    i=np.unravel_index(D.argmax(),D.shape); print('shape',D.shape,'min',D.min(),'max',D.max(),'argmax row/col',i)
