# Registo de fontes de dados e licenças

Princípio: **ligar primeiro, alojar depois**. Só se alojam ficheiros de fontes cuja licença permita uso comercial e
redistribuição, com atribuição. Licença desconhecida, não comercial ou restritiva → só ligação externa.
A página pública «Dados e Licenças» é gerada a partir de `config/sources.php` (manter os dois em sintonia).

| Fonte | Uso no site | Licença | Situação | Verificada em | Como foi verificada |
|---|---|---|---|---|---|
| Natural Earth Admin 1 (1:10m) | Províncias (alojado) | Domínio público | **Alojado** | 2026-11-13 | naturalearthdata.com/about/terms-of-use: domínio público, uso comercial e redistribuição permitidos, atribuição não obrigatória |
| geoBoundaries gbOpen MOZ ADM2 (INE, OCHA ROSEA) | Distritos (alojado) | CC BY 3.0 IGO | **Alojado** | 2026-11-13 | API geoboundaries.org: `boundaryLicense` = CC BY 3.0 IGO, ano 2019, fonte da licença data.humdata.org. O comando de geração volta a verificar a cada execução |
| NASA FIRMS (VIIRS S-NPP 375 m) | Focos de incêndio (alojado) e camada do mapa | Política de dados abertos da NASA | **Alojado** | 2026-11-13 | Página FAQ do FIRMS: partilha aberta, texto de agradecimento sugerido (usado nas fichas) |
| NASA GIBS | Imagem diária no mapa | Dados abertos da NASA | Serviço | — | Atribuição pedida no briefing; reconfirmar no M7 |
| OpenFreeMap / OpenMapTiles / OSM | Mapa de base | ODbL (dados OSM) | Serviço | — | Reconfirmar no M7 |
| ESA WorldCover 2021 v200 | Cobertura do solo | CC BY 4.0 | Ligação externa (raster: pipeline) | — | A confirmar na página da ESA antes de `--host` |
| Hansen/UMD GFC v1.12 | Perda arbórea | CC BY 4.0 (versões anteriores) | Ligação externa | — | **Confirmar a licença da v1.12** antes de alojar |
| WRI / Google DeepMind — causas da perda | Perda arbórea | CC BY 4.0 | Ligação externa | — | A confirmar no portal GFW |
| HydroSHEDS (HydroRIVERS, HydroBASINS) | Hidrologia | Acordo de licença HydroSHEDS | Ligação externa | — | **Ler as restrições de redistribuição** do acordo |
| JRC Global Surface Water | Hidrologia | Por confirmar | Ligação externa | — | — |
| ESA CCI Biomass v7 | Biomassa e carbono | Termos próprios CEDA | Ligação externa | — | Só alojar derivados se os termos permitirem |

Serviços proibidos neste projecto (pagos, com chave paga ou sem uso comercial livre): Google Maps, Mapbox, MapTiler,
CARTO, Esri World Imagery, EOX Sentinel-2 cloudless, GADM, mosaicos de `tile.openstreetmap.org` em produção.

## Ao acrescentar uma fonte

1. Ler a licença na página oficial e anotar aqui a data e o que diz.
2. Acrescentar a entrada em `config/sources.php` (`verified` com a data).
3. Criar o dataset no painel com a licença SPDX e a atribuição **exacta** (copiada, nunca traduzida).
4. Só depois alojar ficheiros.
