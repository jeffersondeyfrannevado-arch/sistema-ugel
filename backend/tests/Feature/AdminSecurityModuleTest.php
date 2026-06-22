<?php

namespace Tests\Feature;

use App\Models\AdminBackup;
use App\Models\AuditLog;
use App\Models\SystemContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSecurityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_requires_mfa_and_can_be_verified(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'mfa_enabled' => true,
            'password' => bcrypt('password123'),
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('mfa_required', true);

        $challengeId = $loginResponse->json('challenge_id');

        Notification::assertCount(1);

        $challenge = \App\Models\LoginChallenge::findOrFail($challengeId);
        $challenge->update([
            'code_hash' => bcrypt('123456'),
        ]);

        $verifyResponse = $this->postJson('/api/login/mfa/verify', [
            'challenge_id' => $challengeId,
            'code' => '123456',
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_SUPER_ADMIN)
            ->assertJsonPath('user.mfa_enabled', true);
    }

    public function test_user_is_locked_after_multiple_failed_attempts(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $lockedResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $lockedResponse->assertStatus(423);
        $this->assertNotNull($user->fresh()->locked_until);
    }

    public function test_custom_role_only_accesses_granted_permissions(): void
    {
        $customAdmin = User::factory()->create([
            'role' => User::ROLE_CUSTOM,
            'custom_permissions' => ['admin.dashboard.view', 'admin.audit.view'],
        ]);

        Sanctum::actingAs($customAdmin, $customAdmin->permissions());

        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/admin/audit-logs')->assertOk();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_can_manage_content_and_audit_is_recorded(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($admin, $admin->permissions());

        $createResponse = $this->postJson('/api/admin/contents', [
            'title' => 'Aviso importante',
            'body' => '<b>Texto seguro</b>',
            'type' => 'announcement',
            'status' => 'draft',
        ]);

        $createResponse->assertCreated();
        $contentId = $createResponse->json('contenido.id');

        $this->patchJson("/api/admin/contents/{$contentId}/moderate", [
            'status' => 'flagged',
            'flagged_reason' => 'Revision manual',
            'review_notes' => 'Pendiente de aprobacion',
        ])->assertOk();

        $content = SystemContent::findOrFail($contentId);
        $this->assertTrue($content->is_flagged);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.content.moderated',
            'subject_id' => $contentId,
        ]);
    }

    public function test_dashboard_and_backup_restore_are_operational(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($admin, $admin->permissions());

        SystemContent::create([
            'title' => 'Respaldo base',
            'body' => 'Contenido a restaurar',
            'type' => 'announcement',
            'status' => 'published',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('dashboard.metrics.usuarios_totales', 1);

        $backupResponse = $this->postJson('/api/admin/backups');
        $backupResponse->assertCreated();

        $backupId = $backupResponse->json('respaldo.id');
        $this->assertDatabaseCount('admin_backups', 1);

        SystemContent::query()->delete();
        $this->assertDatabaseCount('system_contents', 0);

        $this->postJson("/api/admin/backups/{$backupId}/restore")
            ->assertOk();

        $this->assertDatabaseHas('system_contents', [
            'title' => 'Respaldo base',
        ]);

        $backup = AdminBackup::findOrFail($backupId);
        $this->assertNotNull($backup->restored_at);
    }
}
