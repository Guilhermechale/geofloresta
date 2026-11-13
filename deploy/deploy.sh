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

cd "$APP"
php artisan down --render="errors::503" --retry=30 || true
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --no-audit --no-fund --silent
npm run build --silent
php artisan migrate --force
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
    cat <<EOF
# --- Ambiente de teste: palavra-passe HTTP e noindex ---
AuthType Basic
AuthName "GEOFLORESTA - teste"
AuthUserFile $SECRETS/.htpasswd
Require valid-user
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>
EOF
  fi
  cat "$APP/public/.htaccess"
} > "$WEB/.htaccess"

php artisan up
echo "Implantado em $TARGET -> $WEB"
