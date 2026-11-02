<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (config('database.default') === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && !file_exists($dbPath) && str_contains($dbPath, '.sqlite')) {
                    @mkdir(dirname($dbPath), 0755, true);
                    @touch($dbPath);
                }
            }

            // Solo migrar si la tabla principal de usuarios no existe
            if (!\Illuminate\Support\Facades\Schema::hasTable('users')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }

            // Solo sembrar usuarios iniciales una vez (si no existe el super admin)
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\App\Models\User::where('email', 'jeffersondeyfrannevado@gmail.com')->exists()) {
                $accounts = [
                    [
                        'email' => 'jeffersondeyfrannevado@gmail.com',
                        'role' => 'super_admin',
                        'name' => 'Super Administrador Jefferson'
                    ],
                    [
                        'email' => 'sanjuanmiraflores67@gmail.com',
                        'role' => 'sub_admin',
                        'name' => 'Sub Administrador San Juan'
                    ],
                    [
                        'email' => 'en1774121@gmail.com',
                        'role' => 'custom',
                        'name' => 'Administrador Personalizado'
                    ],
                    [
                        'email' => 'fiestasadmin@ejemplo.com',
                        'role' => 'user',
                        'name' => 'fiestas'
                    ],
                    [
                        'email' => 'usuario@ejemplo.com',
                        'role' => 'user',
                        'name' => 'JUMAIRA'
                    ]
                ];

                $hashedPassword = \Illuminate\Support\Facades\Hash::make('ClaveSegura123');

                foreach ($accounts as $acc) {
                    \App\Models\User::firstOrCreate(
                        ['email' => $acc['email']],
                        [
                            'name' => $acc['name'],
                            'role' => $acc['role'],
                            'is_active' => true,
                            'failed_login_attempts' => 0,
                            'locked_until' => null,
                            'password' => $hashedPassword,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("AppServiceProvider auto-setup warning: " . $e->getMessage());
        }
    }
}
