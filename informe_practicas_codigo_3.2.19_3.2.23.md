# Código de Implementación para el Informe de Prácticas (sistemas-doc1)

Este documento contiene los fragmentos clave de código de backend (Laravel) y frontend (React) correspondientes a cada uno de los puntos del informe de prácticas, listos para ser incorporados como evidencia técnica.

---

## 3.2.19. Registros de exportes y controles de envío

### Código en el Servicio (`MatriculaService.php`)

Este fragmento muestra la lógica del método `procesarArchivo` encargado de clasificar por modalidad (Público/Privado), agrupar por distrito y generar de forma asíncrona un archivo Excel por distrito y modalidad:

```php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\UploadedFile;

class MatriculaService
{
    private const TIPOS_PUBLICOS = ['A1', 'A2', 'A3', 'A4'];

    public function procesarArchivo(UploadedFile $archivo, array $columnasResaltadas): array
    {
        // Limpiar el directorio de salida temporal
        $this->limpiarDirectorio($this->outputBase);

        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        // Resuelve las cabeceras y el contexto del nivel del Excel (Inicial, Primaria, Secundaria)
        [$dataStartRow, $headerRowIndex, $subHeaderRowIndex, $formato, $columnasDetectadas, $perfil] = 
            $this->resolverContextoExcel($rawRows, $sheet->getTitle());

        $registros = [];
        $errores = [];

        // Leer fila por fila y validar los campos requeridos
        for ($i = $dataStartRow; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            if ($this->filaVacia($row)) continue;

            $resultado = $this->limpiarYValidarFila($row, $i + 1, $formato, $columnasDetectadas);
            if ($resultado['valida']) {
                $registros[] = $resultado['datos'];
            } else {
                $errores[] = $resultado['error'];
            }
        }

        // Filtro activo: Solo conservar registros con alumnos con matrícula en proceso
        $registros = array_values(array_filter($registros, function (array $registro) {
            return ($registro['MATRICULA_EN_PROCESO'] ?? 0) > 0;
        }));

        // Segmentar por gestión administrativa (Modalidad)
        $publicos = array_filter($registros, fn ($r) => $r['_modalidad'] === 'PUBLICO');
        $privados = array_filter($registros, fn ($r) => $r['_modalidad'] === 'PRIVADO');

        $archivosGenerados = [];

        // Agrupar por distrito y generar archivos Excel independientes
        foreach (['PUBLICO' => $publicos, 'PRIVADO' => $privados] as $modalidad => $grupo) {
            if (empty($grupo)) continue;

            $porDistrito = $this->agruparPorDistrito($grupo);
            foreach ($porDistrito as $distrito => $filas) {
                $nombreArchivo = $this->generarExcelDistrito(
                    $modalidad, $distrito, $filas, $columnasResaltadas, $formato
                );

                $archivosGenerados[] = [
                    'modalidad' => $modalidad,
                    'distrito' => $distrito,
                    'nivel' => $formato['titulo'],
                    'archivo' => $nombreArchivo,
                    'ruta' => base64_encode("{$modalidad}/{$distrito}/{$nombreArchivo}"),
                    'registros' => count($filas),
                ];
            }
        }

        return [
            'nivel' => $formato['titulo'],
            'estadisticas' => [
                'total' => count($registros),
                'publicos' => count($publicos),
                'privados' => count($privados),
                'errores' => count($errores),
                'distritos' => count(array_unique(array_column($registros, 'DISTRITO'))),
            ],
            'archivos' => $archivosGenerados,
            'errores' => $errores,
        ];
    }
}
```

### Código en el Controlador (`MatriculaController.php`)

Lógica de los endpoints encargados de despachar las descargas en PDF consolidado y el paquete comprimido ZIP de control de envíos:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MatriculaService;
use ZipArchive;

class MatriculaController extends Controller
{
    public function __construct(protected MatriculaService $service) {}

    // Descarga en formato PDF generado mediante Dompdf a partir de Excel
    public function descargarPdf($filename)
    {
        $path = base64_decode($filename);
        $fullPath = storage_path('app/matricula_output/' . $path);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        [$pdfContent, $pdfName] = $this->service->generarPdfDesdeExcel($fullPath);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $pdfName . '"',
        ]);
    }

    // Compresión recursiva del directorio de salida y descarga en ZIP (Control de Despacho)
    public function descargarZip(Request $request)
    {
        $outputDir = storage_path('app/matricula_output');

        if (!is_dir($outputDir)) {
            return response()->json(['error' => 'Sin archivos generados'], 404);
        }

        $zipPath = storage_path('app/matricula_exportacion_' . now()->format('Ymd_His') . '.zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getRealPath(), strlen($outputDir) + 1);
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
```

---

## 3.2.20. Generación de alertas y notificaciones del sistema

### Código en el Servicio (`SystemAlertService.php`)

Mecanismo centralizado que despacha errores catastróficos o incidencias de seguridad a través de Logs, correo electrónico SMTP y la API de webhooks de Slack:

```php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemAlertService
{
    // Notificación principal del sistema
    public function notify(string $title, array $context = []): void
    {
        try {
            // 1. Registrar en Logs de Laravel
            Log::channel(config('admin.alerts.log_channel'))
                ->error($title, $context);

            // 2. Enviar correos SMTP a la lista configurada
            $this->sendEmails($title, $context);

            // 3. Postear en el canal de Slack
            $this->sendSlack($title, $context);
        } catch (Throwable $notificationException) {
            Log::error('No se pudo emitir una alerta del sistema: ' . $notificationException->getMessage());
        }
    }

    // Notificar directamente a todos los Administradores activos
    public function notifyAdmins(string $title, array $context = []): void
    {
        $adminEmails = User::query()
            ->whereIn('role', ['super_admin', 'sub_admin'])
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->all();

        $this->sendEmails($title, $context, $adminEmails);
    }

    private function sendEmails(string $title, array $context, array $overrideRecipients = []): void
    {
        $recipients = $overrideRecipients !== [] ? $overrideRecipients : config('admin.alerts.emails', []);
        if ($recipients === []) return;

        $body = $title . PHP_EOL . PHP_EOL . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        foreach ($recipients as $recipient) {
            Mail::raw($body, function ($message) use ($recipient, $title) {
                $message->to($recipient)->subject($title);
            });
        }
    }

    private function sendSlack(string $title, array $context): void
    {
        $webhook = config('admin.alerts.slack_webhook');
        if (!$webhook) return;

        Http::timeout(5)->post($webhook, [
            'text' => $title . PHP_EOL . json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
```

---

## 3.2.21. Auditoría de operaciones y logs de seguridad

### Código en el Servicio (`AuditLogService.php`)

Clase que intercepta los eventos operativos de seguridad para registrarlos de forma inmutable junto con la IP y el User Agent del dispositivo:

```php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        ?User $actor,
        string $action,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        $subjectType = null;
        $subjectId = null;
        $targetLabel = null;

        // Si el sujeto es un modelo Eloquent, extraer su modelo y identificador de llave primaria
        if ($subject instanceof Model) {
            $subjectType = $subject::class;
            $subjectId = $subject->getKey();
            $targetLabel = method_exists($subject, 'getAttribute')
                ? (string) ($subject->getAttribute('name')
                    ?? $subject->getAttribute('title')
                    ?? $subject->getKey())
                : (string) $subject->getKey();
        } elseif (is_string($subject) && $subject !== '') {
            $targetLabel = $subject;
        }

        // Crear el registro de auditoría en la base de datos
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'target_label' => $targetLabel,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
```

---

## 3.2.22. Respaldos y restauración del sistema (Backups y restauración)

### Código en el Servicio (`AdminBackupService.php`)

Este servicio encapsula la lógica para realizar respaldos en formato JSON y restaurar el sistema entero en una sola transacción segura de base de datos:

```php
namespace App\Services;

use App\Models\AdminBackup;
use App\Models\AuditLog;
use App\Models\LoginChallenge;
use App\Models\SystemContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminBackupService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemAlertService $systemAlertService
    ) {}

    // Generar Backup Completo en JSON
    public function createBackup(?User $actor = null): AdminBackup
    {
        $disk = config('admin.backup.disk', 'local');
        $directory = trim((string) config('admin.backup.directory', 'admin_backups'), '/');
        $fileName = "{$directory}/admin_backup_" . now()->format('Ymd_His') . ".json";
        $profilesPath = storage_path('app/excel_format_profiles.json');

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'users' => User::query()->orderBy('id')->get()->toArray(),
            'contents' => SystemContent::query()->orderBy('id')->get()->toArray(),
            'audit_logs' => AuditLog::query()->orderBy('id')->get()->toArray(),
            'login_challenges' => LoginChallenge::query()->orderBy('id')->get()->toArray(),
            'format_profiles' => is_file($profilesPath) ? json_decode((string) file_get_contents($profilesPath), true) : [],
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Storage::disk($disk)->put($fileName, $encoded ?: '{}');

        $backup = AdminBackup::create([
            'file_name' => $fileName,
            'disk' => $disk,
            'status' => 'completed',
            'size_bytes' => strlen((string) $encoded),
            'created_by' => $actor?->id,
            'metadata' => [
                'users' => count($payload['users']),
                'contents' => count($payload['contents']),
                'audit_logs' => count($payload['audit_logs']),
            ],
        ]);

        $this->auditLogService->record($actor, 'admin.backup.created', $backup, ['file_name' => $fileName]);

        return $backup;
    }

    // Restauración Transaccional y Revocación de Sesiones Activas
    public function restoreBackup(AdminBackup $backup, ?User $actor = null): void
    {
        $content = Storage::disk($backup->disk)->get($backup->file_name);
        $snapshot = json_decode($content, true);

        if (!is_array($snapshot)) {
            throw new \RuntimeException('El respaldo no tiene un formato válido.');
        }

        DB::transaction(function () use ($snapshot, $backup, $actor) {
            // 1. Revocar de forma inmediata todas las sesiones activas (tokens Sanctum)
            DB::table('personal_access_tokens')->delete();
            DB::table('login_challenges')->delete();
            DB::table('system_contents')->delete();
            DB::table('audit_logs')->delete();
            DB::table('users')->delete();

            // 2. Insertar los datos respaldados validando las columnas contra el esquema
            foreach ($snapshot['users'] ?? [] as $row) {
                DB::table('users')->insert($this->filterColumns('users', $row));
            }
            foreach ($snapshot['contents'] ?? [] as $row) {
                DB::table('system_contents')->insert($this->filterColumns('system_contents', $row));
            }
            foreach ($snapshot['audit_logs'] ?? [] as $row) {
                DB::table('audit_logs')->insert($this->filterColumns('audit_logs', $row));
            }
            foreach ($snapshot['login_challenges'] ?? [] as $row) {
                DB::table('login_challenges')->insert($this->filterColumns('login_challenges', $row));
            }

            // 3. Restaurar plantillas físicas de Excel
            file_put_contents(
                storage_path('app/excel_format_profiles.json'),
                json_encode($snapshot['format_profiles'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 4. Actualizar estado de la restauración
            $backup->forceFill([
                'restored_by' => $actor?->id,
                'restored_at' => now(),
                'status' => 'restored',
            ])->save();
        });

        $this->auditLogService->record($actor, 'admin.backup.restored', $backup, ['file_name' => $backup->file_name]);
    }

    private function filterColumns(string $table, array $payload): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        return array_intersect_key($payload, $columns);
    }
}
```

---

## 3.2.23. Pruebas integrales del sistema completo

### Pruebas de Integración Automatizadas (`MatriculaTest.php` / PHPUnit)

Código de prueba automatizada de integración que simula la subida del Excel para verificar la correcta clasificación y estructuración de archivos generados:

```php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class MatriculaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function un_administrador_puede_cargar_y_procesar_matriculas_exitosamente()
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Storage::fake('local');

        // Simular un archivo Excel de matrícula de nivel Secundaria
        $excelSimulado = UploadedFile::fake()->create('matricula_secundaria.xlsx', 100);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/matricula/procesar', [
                'archivo' => $excelSimulado,
                'columnas_resaltadas' => ['Matricula En Proceso']
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'mensaje',
                'nivel',
                'estadisticas' => ['total', 'publicos', 'privados', 'errores', 'distritos'],
                'archivos',
                'errores'
            ]);
    }
}
```
