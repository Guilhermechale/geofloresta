# Pipeline de dados

Há duas partes: o que corre **no servidor** (dados vectoriais e tabelas leves, em PHP) e o que corre **no seu computador**
(rasters, com Python e GDAL). O site serve sempre ficheiros já preparados; nada pesado corre no alojamento.

## 1. No servidor (já em funcionamento)

O servidor não tem GDAL, por isso os formatos vectoriais são escritos por código próprio em `app/Geo/`
(GeoJSON, GeoPackage 1.4, Shapefile, CSV). Cada ZIP leva `LEIAME.txt` (PT/FR/EN), `LICENCA.txt` e `CITACAO.txt`;
o tamanho e o SHA-256 são calculados no fim.

```bash
cd ~/geofloresta
php artisan geofloresta:datasets provinces          # Natural Earth, domínio público
php artisan geofloresta:datasets districts          # geoBoundaries MOZ ADM2 (INE, OCHA ROSEA), CC BY 3.0 IGO
php artisan geofloresta:datasets fires --year=2025  # NASA FIRMS, arquivo anual VIIRS S-NPP, sem chave (~4 min)
```

- Os datasets novos ficam em **rascunho**: verifique a ficha no painel e publique (ou use `--publish`).
- Repetir um comando regenera os ficheiros e actualiza os campos técnicos; os textos editados no painel não são sobrepostos.
- As fontes descarregadas ficam em `storage/app/sources/` (reutilizadas). Apague-as para forçar nova descarga.
- Antes de alojar, o comando dos distritos verifica que a licença indicada pela API do geoBoundaries continua a ser CC BY 3.0;
  se mudar, pára com erro.
- Depois de mudar o site para o domínio definitivo, regenere os ZIP para o `LEIAME.txt` apontar para geofloresta.com.

Produtos gerados: 11 províncias (com a Cidade de Maputo separada, código MZ-MPM), 159 distritos com a respectiva província,
766 112 focos VIIRS de 2025 com província e distrito, e a tabela de focos por província e mês.

## 2. No seu computador (rasters: WorldCover, Hansen, carbono)

> **Estado:** os scripts estão escritos mas **não foram executados** — nem o seu computador nem o servidor têm GDAL.
> Teste primeiro com uma só província (`--so Sofala`).

### Instalar (uma vez, gratuito)

1. Instale o [Miniforge](https://github.com/conda-forge/miniforge) (conda livre).
2. Na pasta `pipeline/`:
   ```bash
   conda env create -f environment.yml
   conda activate geofloresta
   ```
3. Descarregue do site o GeoPackage das províncias (ficha «Províncias de Moçambique») e extraia
   `mz_provincias_naturalearth.gpkg`.

### Correr

```bash
python worldcover_provinces.py --provincias mz_provincias_naturalearth.gpkg --so Sofala
python worldcover_provinces.py --provincias mz_provincias_naturalearth.gpkg
python hansen_loss_by_province.py --provincias mz_provincias_naturalearth.gpkg --limiar 30
```

Os resultados ficam em `pipeline/entregas/`, cada pasta com `SHA256SUMS`. Os rasters lêem-se por HTTP com `/vsicurl/`:
o GDAL só descarrega os blocos que intersectam cada província.

**Hansen v1.12:** antes de correr, confirme na página de download da UMD/GLAD o padrão dos URLs
(`GFC-2024-v1.12/Hansen_GFC-2024-v1.12_<camada>_<mosaico>.tif`) e que a licença continua CC BY 4.0.

**Carbono:** `carbon_from_biomass.py` precisa de um mapa de biomassa e do desvio-padrão. Só publique o resultado se a
licença da biomassa de origem permitir produtos derivados (a ESA CCI Biomass tem termos próprios do CEDA).

### Enviar para o site

```bash
scp -P 65002 -r entregas/worldcover-2021 u923245343@45.132.157.10:~/entregas/
ssh -p 65002 u923245343@45.132.157.10
cd ~/geofloresta
php artisan geofloresta:attach cobertura-do-solo-esa-worldcover-2021 ~/entregas/worldcover-2021/sofala/* \
    --format=cog --name=worldcover_2021_sofala --label-pt=Sofala --label-fr=Sofala --label-en=Sofala --host
```

O `--host` muda o dataset de «Ligação externa» para «Alojado». Use-o só depois de confirmar a licença em
`docs/DADOS-E-LICENCAS.md`.
