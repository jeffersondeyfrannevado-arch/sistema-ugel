<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\AdminBackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:make-admin {email} {--name=Administrador} {--password=}', function (string $email) {
    $password = (string) ($this->option('password') ?: Str::password(12));
    $name = (string) ($this->option('name') ?: 'Administrador');

    $user = User::where('email', $email)->first();
    $created = false;

    if (!$user) {
        $user = new User();
        $user->email = $email;
        $created = true;
    }

    $user->name = $user->name ?: $name;
    $user->role = User::ROLE_SUPER_ADMIN;
    $user->is_active = true;

    if ($created || $this->option('password')) {
        $user->password = Hash::make($password);
    }

    $user->save();

    $this->info($created
        ? 'Administrador creado correctamente.'
        : 'Usuario promovido correctamente a administrador.');

    $this->line('Email: ' . $user->email);

    if ($created || $this->option('password')) {
        $this->line('Password: ' . $password);
    }
})->purpose('Crear o promover un usuario administrador');

Artisan::command('users:make-subadmin {email} {--name=SubAdministrador} {--password=}', function (string $email) {
    $password = (string) ($this->option('password') ?: Str::password(12));
    $name = (string) ($this->option('name') ?: 'SubAdministrador');

    $user = User::where('email', $email)->first();
    $created = false;

    if (!$user) {
        $user = new User();
        $user->email = $email;
        $created = true;
    }

    $user->name = $user->name ?: $name;
    $user->role = User::ROLE_SUB_ADMIN;
    $user->is_active = true;

    if ($created || $this->option('password')) {
        $user->password = Hash::make($password);
    }

    $user->save();

    $this->info($created
        ? 'SubAdministrador creado correctamente.'
        : 'Usuario promovido correctamente a subadministrador.');

    $this->line('Email: ' . $user->email);

    if ($created || $this->option('password')) {
        $this->line('Password: ' . $password);
    }
})->purpose('Crear o promover un usuario subadministrador');

Artisan::command('admin:backup', function (AdminBackupService $backupService) {
    $backup = $backupService->safeCreateBackup();

    $this->info('Respaldo generado correctamente.');
    $this->line('Archivo: ' . $backup?->file_name);
})->purpose('Generar un respaldo administrativo del sistema');

Schedule::command('admin:backup')->dailyAt(config('admin.backup.schedule', '02:00'));
