#!/bin/sh

echo "=== INICIANDO SISTEMA UGEL API ==="

APP_DIR=/var/www/html
cd "$APP_DIR" || exit 1

# 0. Railway/Render: garantizar un solo MPM de Apache (evita "More than one MPM loaded")
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null
[ -f /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null 2>&1

# Puerto dinamico (Render inyecta PORT=10000, Railway inyecta variable)
export PORT="${PORT:-80}"
echo "Puerto HTTP configurado: $PORT"

# Configurar Apache para escuchar en 80, 10000, y $PORT para responder a cualquier proxy
cat << 'EOF' > /etc/apache2/ports.conf
Listen 80
Listen 10000
EOF

if [ -n "$PORT" ] && [ "$PORT" != "80" ] && [ "$PORT" != "10000" ]; then
    echo "Listen $PORT" >> /etc/apache2/ports.conf
fi

# 1. Asegurar directorios de base de datos, almacenamiento y cache
mkdir -p "$APP_DIR/database" \
         "$APP_DIR/storage/app/public" \
         "$APP_DIR/storage/framework/cache/data" \
         "$APP_DIR/storage/framework/sessions" \
         "$APP_DIR/storage/framework/views" \
         "$APP_DIR/storage/logs" \
         "$APP_DIR/bootstrap/cache"
touch "$APP_DIR/database/database.sqlite"
chown -R www-data:www-data "$APP_DIR/database" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/database" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 2. Limpiar caches antiguas
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true

# 3. Migraciones rápidas (no bloquean indefinidamente el arranque de Apache)
echo "Verificando base de datos y migraciones..."
php artisan migrate --force --no-interaction || echo "Aviso: Migraciones no completadas inmediatamente, continuando arranque..."

# 4. Sembrado si está habilitado
if [ "${SEED_ON_BOOT:-false}" = "true" ]; then
    echo "Sembrando base de datos..."
    php artisan db:seed --force --no-interaction || true
fi

# 5. Enlace de storage publico y cache de configuracion para produccion
php artisan storage:link >/dev/null 2>&1 || true
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache || true
fi

# 6. Re-asegurar permisos para www-data
chown -R www-data:www-data "$APP_DIR/database" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/database" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 7. Iniciar Apache en primer plano
echo "Iniciando servidor web Apache..."
exec apache2-foreground
