<?php

namespace App\Services;

use App\Models\AdminBackup;
use App\Models\AuditLog;
use App\Models\LoginChallenge;
use App\Models\SystemContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdminBackupService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SystemAlertService $systemAlertService
    ) {}

    public function createBackup(?User $actor = null): AdminBackup
    {
        $disk = config('admin.backup.disk', 'local');
        $directory = trim((string) config('admin.backup.directory', 'admin_backups'), '/');
        $timestamp = now()->format('Ymd_His');
        $fileName = "{$directory}/admin_backup_{$timestamp}.json";
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

        $this->auditLogService->record($actor, 'admin.backup.created', $backup, [
            'file_name' => $fileName,
        ]);

        return $backup;
    }

    public function restoreBackup(AdminBackup $backup, ?User $actor = null): void
    {
        $content = Storage::disk($backup->disk)->get($backup->file_name);
        $snapshot = json_decode($content, true);

        if (!is_array($snapshot)) {
            throw new \RuntimeException('El respaldo no tiene un formato valido.');
        }

        DB::transaction(function () use ($snapshot, $backup, $actor) {
            DB::table('personal_access_tokens')->delete();
            DB::table('login_challenges')->delete();
            DB::table('system_contents')->delete();
            DB::table('audit_logs')->delete();
            DB::table('users')->delete();

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

            file_put_contents(
                storage_path('app/excel_format_profiles.json'),
                json_encode($snapshot['format_profiles'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $backup->forceFill([
                'restored_by' => $actor?->id,
                'restored_at' => now(),
                'status' => 'restored',
            ])->save();
        });

        $this->auditLogService->record($actor, 'admin.backup.restored', $backup, [
            'file_name' => $backup->file_name,
        ]);
    }

    public function safeCreateBackup(?User $actor = null): ?AdminBackup
    {
        try {
            return $this->createBackup($actor);
        } catch (Throwable $exception) {
            $this->systemAlertService->notify('Fallo al generar respaldo administrativo', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function filterColumns(string $table, array $payload): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($payload, $columns);
    }
}
