# Modulo Administrador

## Resumen

El modulo administrador incorpora autenticacion reforzada, MFA por correo, permisos granulares, dashboard operativo, auditoria inmutable, moderacion de contenido, respaldos y restauracion, y exportacion de reportes en PDF/Excel.

## Componentes principales

- `app/Support/AdminPermissions.php`: matriz de roles y permisos.
- `app/Http/Controllers/AuthController.php`: login, MFA, refresco de token y perfil actual.
- `app/Http/Controllers/AdminDashboardController.php`: dashboard y exportaciones.
- `app/Http/Controllers/AdminUserController.php`: gestion avanzada de usuarios.
- `app/Http/Controllers/AdminContentController.php`: alta, edicion y moderacion de contenido.
- `app/Http/Controllers/AuditLogController.php`: consulta del log de auditoria.
- `app/Http/Controllers/AdminBackupController.php`: generacion y restauracion de respaldos.
- `app/Services/AuditLogService.php`: registro centralizado de auditoria.
- `app/Services/MfaChallengeService.php`: emision y verificacion de codigos MFA.
- `app/Services/AdminDashboardService.php`: metricas operativas.
- `app/Services/AdminReportService.php`: exportacion PDF/Excel.
- `app/Services/AdminBackupService.php`: snapshot y restauracion de datos administrativos.
- `app/Services/SystemAlertService.php`: alertas por correo, log y webhook de Slack.

## Roles y permisos

### Roles incluidos

- `super_admin`: acceso total.
- `sub_admin`: operacion administrativa sin restauracion ni borrado critico.
- `custom`: permisos definidos por usuario.
- `user`: sin acceso al modulo administrador.

### Permisos disponibles

- `admin.dashboard.view`
- `admin.reports.export`
- `admin.users.view`
- `admin.users.create`
- `admin.users.update`
- `admin.users.suspend`
- `admin.users.delete`
- `admin.content.view`
- `admin.content.create`
- `admin.content.update`
- `admin.content.moderate`
- `admin.audit.view`
- `admin.audit.export`
- `admin.backups.view`
- `admin.backups.create`
- `admin.backups.restore`
- `admin.formats.manage`

## Variables de entorno

Configurar en `backend/.env`:

```env
SANCTUM_EXPIRATION=120
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173,http://localhost:5173

ADMIN_MFA_CODE_LENGTH=6
ADMIN_MFA_EXPIRES_MINUTES=10
ADMIN_MFA_REQUIRED_ROLES=super_admin,sub_admin
ADMIN_MAX_LOGIN_ATTEMPTS=5
ADMIN_LOCKOUT_MINUTES=15
ADMIN_TOKEN_REFRESH_MINUTES_BEFORE_EXPIRY=5
ADMIN_ALERTS_ENABLED=true
ADMIN_ALERT_EMAILS=admin@dominio.com,seguridad@dominio.com
ADMIN_SLACK_WEBHOOK_URL=
ADMIN_ALERT_LOG_CHANNEL=stack
ADMIN_BACKUP_DISK=local
ADMIN_BACKUP_DIRECTORY=admin_backups
ADMIN_BACKUP_SCHEDULE=02:00
```

## Despliegue

1. Usar PHP `8.3` o superior.
2. Ejecutar `composer install`.
3. Copiar `.env.example` a `.env`.
4. Configurar correo real para MFA y alertas.
5. Ejecutar `php artisan migrate`.
6. Crear el primer administrador con:

```bash
php artisan users:make-admin admin@dominio.com --name="Administrador Principal" --password="ClaveSegura123"
```

7. Ejecutar worker de cola si vas a externalizar notificaciones o procesos futuros.
8. Programar el scheduler de Laravel cada minuto:

```bash
php artisan schedule:run
```

## Operacion

### Login con MFA

1. El usuario envia correo y contrasena.
2. Si el rol exige MFA, el sistema crea un `login_challenge`.
3. Se envia un codigo por correo.
4. El frontend valida el codigo y recien entonces recibe el token Sanctum.

### Renovacion de token

- El frontend guarda `auth_expires_at`.
- Antes del vencimiento llama a `/api/token/refresh`.
- El backend revoca el token actual y emite uno nuevo.

### Auditoria

Se registran, entre otros, estos eventos:

- login correcto
- login fallido
- bloqueo por intentos
- creacion/verificacion de MFA
- gestion de usuarios
- moderacion de contenido
- creacion/restauracion de respaldos

## Respaldo y restauracion

### Respaldo manual

Desde UI o CLI:

```bash
php artisan admin:backup
```

### Respaldo automatico

- El scheduler ejecuta `admin:backup` diariamente a la hora configurada.

### Restauracion

- La restauracion reemplaza datos administrativos por el snapshot almacenado.
- Se eliminan los tokens activos para evitar sesion inconsistente.
- Tras restaurar, los usuarios deben volver a autenticarse.

## Seguridad aplicada

- contrasenas hasheadas
- MFA por correo
- expiracion y refresco de token
- bloqueo temporal por fuerza bruta
- permisos granulares
- contenido saneado con `strip_tags`
- Eloquent y validaciones para reducir riesgo de SQL injection
- CORS acotado por entorno
- auditoria persistente

## Mantenimiento

- revisar diariamente los respaldos recientes
- revisar logs `auth.login.failed` y `auth.login.locked`
- validar entregabilidad del correo MFA
- mantener PHP 8.3, Composer y dependencias actualizadas
- probar restauracion de respaldo en entorno de staging antes de usarla en produccion

## Resolucion de problemas

### No llegan correos MFA

- Verifica `MAIL_MAILER`, host SMTP, puerto, usuario y clave.
- Comprueba que `MAIL_FROM_ADDRESS` sea valido.
- Si estas en desarrollo con `MAIL_MAILER=log`, el codigo se escribira en logs y no se enviara realmente.

### El login devuelve bloqueo temporal

- Espera el tiempo configurado en `ADMIN_LOCKOUT_MINUTES`.
- O desbloquea la cuenta actualizando `locked_until` a `null`.

### El refresco de token falla

- Revisa que `SANCTUM_EXPIRATION` exista y el token actual no haya vencido.
- Verifica que el frontend conserve `auth_token` y `auth_expires_at`.

### La restauracion invalida la sesion

- Es el comportamiento esperado: se revocan tokens para evitar inconsistencias.
- Inicia sesion nuevamente con una cuenta administrativa valida.

### `php artisan test` falla por sintaxis de vendor

- El proyecto requiere PHP `8.3`.
- Si el entorno local tiene `PHP 8.2`, algunas dependencias de test no podran cargarse.
