# Implantação (Hostinger, alojamento partilhado)

## Onde está cada coisa no servidor

| Caminho | O quê |
|---|---|
| `~/geofloresta/` | Aplicação Laravel (fora da raiz web). Repositório Git `main`. |
| `~/repos/geofloresta.git` | Espelho Git privado (bare), remoto `origin`. |
| `~/geofloresta/.env` | Configuração e segredos (modo 600, fora do Git). |
| `~/geofloresta-secrets/` | `.htpasswd` do ambiente de teste, credenciais temporárias do painel e do teste. |
| `~/domains/geofloresta.com/public_html/novo/` | Raiz web de `novo.geofloresta.com` (cópia de `public/`). |
| `~/backups-wordpress/AAAA-MM-DD/` | Backup do WordPress (ficheiros + SQL + SHA256SUMS). |

## Ligar por SSH

```bash
ssh -i keys/geofloresta_deploy -p 65002 u923245343@45.132.157.10
```

O domínio passa pela CDN da Hostinger; para SSH usa-se o IP do servidor (`ftp.geofloresta.com`), porta 65002.

## Implantar

```bash
cd ~/geofloresta && git pull && deploy/deploy.sh staging
```

O script `deploy/deploy.sh`:
1. põe o site em manutenção (página 503 própria);
2. `composer install --no-dev --optimize-autoloader`;
3. `npm ci` + `npm run build` com o Node 24 de `/opt/alt/alt-nodejs24` e **uma só thread**
   (`RAYON_NUM_THREADS=1`: a Hostinger limita threads e o Vite 8 aborta sem isto);
4. `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `filament:optimize`;
5. copia `public/` para a raiz web com `rsync`, gera `index.php` com os caminhos absolutos da aplicação e
   cria a ligação `storage` (o `artisan storage:link` não funciona: `exec()` está desactivado);
6. no ambiente `staging` acrescenta ao `.htaccess` a palavra-passe HTTP e `X-Robots-Tag: noindex, nofollow`.

## Notas da Hostinger verificadas

- PHP 8.3.36 CLI, Composer 2.9.8, Git 2.52, MariaDB 11.8, Node 18/20/22/24 em `/opt/alt` (não está no PATH).
- `exec()` desactivado no PHP; processos em segundo plano morrem ao fechar a sessão SSH
  (tarefas longas: correr em primeiro plano ou por cron).
- O LiteSpeed só aceita `.htpasswd` em formato APR1 (`openssl passwd -apr1`), não bcrypt.
- Base de dados da aplicação: `u923245343_gfsite` em `127.0.0.1:3306`.
