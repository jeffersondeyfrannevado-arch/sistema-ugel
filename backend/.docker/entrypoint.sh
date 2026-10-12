#!/bin/sh

echo "=== INICIANDO SISTEMA UGEL API ==="

# 1. Asegurar directorios de base de datos, almacenamiento y cache
mkdir -p /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache
touch /var/www/html/database/database.sqlite
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache

# 2. Intentar ejecutar migraciones sin tumbar el contenedor si la BD externa esta pausada o inalcanzable
echo "Verificando y ejecutando migraciones de base de datos..."
if php artisan migrate --force; then
    echo "Migraciones ejecutadas exitosamente."
else
    echo "======================================================================"
    echo "ADVERTENCIA: No se pudieron ejecutar las migraciones en el arranque."
    echo "Causa: La base de datos externa (Supabase) esta en pausa o no responde."
    echo "El servidor web continuara iniciando para mantener el servicio activo en Render."
    echo "======================================================================"
fi

# 3. Re-asegurar permisos para www-data
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Iniciar Apache en primer plano
echo "Iniciando servidor web Apache..."
exec apache2-foreground
