#!/bin/bash
# Implantação da GEOFLORESTA em hospedagem partilhada (Hostinger).
# Uso: deploy/deploy.sh staging|production
#   staging    -> ~/domains/geofloresta.com/public_html/novo  (palavra-passe HTTP + noindex)
#   production -> ~/domains/geofloresta.com/public_html       (só no M10)
set -euo pipefail
TARGET="${1:-staging}"
APP="$HOME/geofloresta"
SECRETS="$HOME/geofloresta-secrets"
export PATH=/opt/alt/alt-nodejs24/root/usr/bin:$PATH

case "$TARGET" in
  staging)    WEB="$HOME/domains/geofloresta.com/public_html/novo" ;;
  production) WEB="$HOME/domains/geofloresta.com/public_html" ;;
  *) echo "alvo desconhecido: $TARGET"; exit 1 ;;
esac

# Hospedagem partilhada limita processos/threads: o Vite (Rolldown/Rust) tem de correr com 1 thread.
export RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1 NODE_OPTIONS="--max-old-space-size=1024"

cd "$APP"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php
php artisan down --render="errors::503" --retry=30 || true
composer install --no-dev --optimize-autoloader --no-interaction --quiet
# npm install (não ci): o package.json vem do computador local e o lock é mantido aqui
npm install --no-audit --no-fund --loglevel=error
npm run build --loglevel=error
php artisan migrate --force
php artisan db:seed --class=ContentSeeder --force   # só cria o que falta; nunca sobrepõe o painel
php artisan db:seed --class=ExternalDatasetsSeeder --force
rm -f public/robots.txt                             # robots.txt é dinâmico (bloqueia tudo fora de produção)
php artisan storage:link --quiet || true
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan filament:optimize --quiet || true

mkdir -p "$WEB"
# Copia public/ para a raiz web. Nunca apaga pastas de outros sites (novo/, staging/) na produção.
rsync -a --delete \
  --exclude='/novo' --exclude='/staging' --exclude='/.well-known' \
  --exclude='/index.php' --exclude='/.htaccess' --exclude='/storage' \
  "$APP/public/" "$WEB/"

# index.php com os caminhos da aplicação fora da raiz web
sed -e "s#__DIR__.'/../storage/framework/maintenance.php'#'$APP/storage/framework/maintenance.php'#" \
    -e "s#__DIR__.'/../vendor/autoload.php'#'$APP/vendor/autoload.php'#" \
    -e "s#__DIR__.'/../bootstrap/app.php'#'$APP/bootstrap/app.php'#" \
    "$APP/public/index.php" > "$WEB/index.php"

# Ficheiros públicos enviados pelo painel
ln -sfn "$APP/storage/app/public" "$WEB/storage"

# .htaccess: o do Laravel, mais a protecção do ambiente de teste
{
  if [ "$TARGET" = staging ]; then
    ALLOW_IPS=""
    [ -f "$SECRETS/ip-permitido.txt" ] && ALLOW_IPS=$(tr '\n' ' ' < "$SECRETS/ip-permitido.txt")
    cat <<EOF
# --- Ambiente de teste: palavra-passe HTTP e noindex ---
AuthType Basic
AuthName "GEOFLORESTA - teste"
AuthUserFile $SECRETS/.htpasswd
<RequireAny>
  Require valid-user
$( [ -n "$ALLOW_IPS" ] && echo "  Require ip $ALLOW_IPS" )
</RequireAny>
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>
EOF
  fi
  cat "$APP/public/.htaccess"
} > "$WEB/.htaccess"

php artisan up
echo "Implantado em $TARGET -> $WEB"
