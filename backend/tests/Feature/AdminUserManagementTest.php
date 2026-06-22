<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_assigns_standard_role_by_default(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Operador Uno',
            'email' => 'operador@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_USER)
            ->assertJsonPath('user.is_active', true);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'bloqueado@example.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_USER,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu cuenta se encuentra desactivada. Contacta al administrador.');
    }

    public function test_only_admin_can_manage_users(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $usuarioComun = User::factory()->create([
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($usuarioComun);
        $this->getJson('/api/admin/users')->assertForbidden();

        Sanctum::actingAs($admin);

        $createdResponse = $this->postJson('/api/admin/users', [
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password123',
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);

        $createdResponse
            ->assertCreated()
            ->assertJsonPath('usuario.email', 'nuevo@example.com');

        $createdUserId = $createdResponse->json('usuario.id');

        $this->patchJson("/api/admin/users/{$createdUserId}/toggle-status")
            ->assertOk()
            ->assertJsonPath('usuario.is_active', false);

        $this->putJson("/api/admin/users/{$createdUserId}", [
            'name' => 'Usuario Editado',
            'email' => 'editado@example.com',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('usuario.name', 'Usuario Editado')
            ->assertJsonPath('usuario.role', User::ROLE_ADMIN);

        $this->deleteJson("/api/admin/users/{$createdUserId}")
            ->assertOk();
    }
}
