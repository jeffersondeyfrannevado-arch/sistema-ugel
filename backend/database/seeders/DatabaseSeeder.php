<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            [
                'email' => 'jeffersondeyfrannevado@gmail.com',
                'role' => 'super_admin',
                'name' => 'Super Administrador Jefferson',
                'password' => 'ClaveSegura123',
            ],
            [
                'email' => 'sanjuanmiraflores67@gmail.com',
                'role' => 'sub_admin',
                'name' => 'Sub Administrador San Juan',
                'password' => 'ClaveSegura123',
            ],
            [
                'email' => 'en1774121@gmail.com',
                'role' => 'custom',
                'name' => 'Administrador Personalizado',
                'password' => 'ClaveSegura123',
            ],
            [
                'email' => 'fiestasadmin@ejemplo.com',
                'role' => 'user',
                'name' => 'fiestas',
                'password' => 'ClaveSegura123',
            ],
            [
                'email' => 'usuario@ejemplo.com',
                'role' => 'user',
                'name' => 'JUMAIRA',
                'password' => 'ClaveSegura123',
            ],
        ];

        foreach ($accounts as $acc) {
            User::updateOrCreate(
                ['email' => $acc['email']],
                [
                    'name' => $acc['name'],
                    'role' => $acc['role'],
                    'is_active' => true,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'password' => \Illuminate\Support\Facades\Hash::make($acc['password']),
                ]
            );
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('system_contents')) {
            \App\Models\SystemContent::firstOrCreate(
                ['title' => 'Bienvenido al Sistema UGEL'],
                [
                    'body' => 'El sistema se encuentra operativo y listo para procesar nóminas de matrícula y reportes Nexus.',
                    'type' => 'announcement',
                    'status' => 'published',
                    'is_flagged' => false,
                ]
            );
        }
    }
}
