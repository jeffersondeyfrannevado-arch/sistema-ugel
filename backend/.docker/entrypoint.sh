#!/bin/sh

echo "=== INICIANDO SISTEMA UGEL API ==="

APP_DIR=/var/www/html
cd "$APP_DIR" || exit 1

# 0. Railway/Render: garantizar un solo MPM de Apache (evita "More than one MPM loaded")
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null
[ -f /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null 2>&1

# Puerto dinamico (Railway inyecta $PORT)
export PORT="${PORT:-80}"
echo "Puerto HTTP: $PORT"

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

# 2. Limpiar caches antiguas para que tome las variables de entorno actuales
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true

# 3. Ejecutar migraciones con reintentos (la BD puede tardar unos segundos en aceptar conexiones)
echo "Verificando y ejecutando migraciones de base de datos..."
MIGRATED=0
ATTEMPT=1
MAX_ATTEMPTS="${DB_MIGRATE_ATTEMPTS:-10}"
while [ "$ATTEMPT" -le "$MAX_ATTEMPTS" ]; do
    if php artisan migrate --force; then
        MIGRATED=1
        echo "Migraciones ejecutadas exitosamente."
        break
    fi
    echo "Intento $ATTEMPT/$MAX_ATTEMPTS fallido. Reintentando en 5 segundos..."
    ATTEMPT=$((ATTEMPT + 1))
    sleep 5
done

if [ "$MIGRATED" -eq 0 ]; then
    echo "======================================================================"
    echo "ADVERTENCIA: No se pudieron ejecutar las migraciones en el arranque."
    echo "Revisa las variables DB_* / DB_URL del servicio."
    echo "El servidor web continuara iniciando para mantener el servicio activo."
    echo "======================================================================"
else
    # 4. Sembrar usuarios iniciales SOLO si la tabla users esta vacia
    #    (asi no se reinician las contrasenas en cada despliegue).
    USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n 1 | tr -dc '0-9')
    if [ "${SEED_ON_BOOT:-false}" = "true" ] || [ "${USER_COUNT:-0}" = "0" ]; then
        echo "Sembrando datos iniciales (usuarios)..."
        php artisan db:seed --force || echo "ADVERTENCIA: la siembra fallo."
    else
        echo "Usuarios existentes: $USER_COUNT. Se omite la siembra."
    fi
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
