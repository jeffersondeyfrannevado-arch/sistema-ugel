<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceRoutesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_subadmin_route_is_blocked_without_secret_token(): void
    {
        config(['admin.maintenance_token' => 'test_secret_token_12345']);

        $response = $this->getJson('/api/create-sanjuan-subadmin');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_create_subadmin_route_succeeds_with_valid_secret_token(): void
    {
        config(['admin.maintenance_token' => 'test_secret_token_12345']);

        $response = $this->withHeader('X-Maintenance-Token', 'test_secret_token_12345')
            ->getJson('/api/create-sanjuan-subadmin');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => 'jeffersondeyfrannevado@gmail.com',
            'role' => 'super_admin',
        ]);
    }

    public function test_view_logs_is_blocked_without_token_or_permission(): void
    {
        config(['admin.maintenance_token' => 'test_secret_token_12345']);

        $response = $this->getJson('/api/view-logs');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_view_logs_succeeds_with_valid_secret_token(): void
    {
        config(['admin.maintenance_token' => 'test_secret_token_12345']);

        $response = $this->withHeader('X-Maintenance-Token', 'test_secret_token_12345')
            ->get('/api/view-logs');

        $response->assertOk();
    }

    public function test_view_logs_succeeds_with_authenticated_audit_user(): void
    {
        config(['admin.maintenance_token' => 'test_secret_token_12345']);

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/view-logs');

        $response->assertOk();
    }
}
