<?php
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'custom_permissions')) {
                $table->json('custom_permissions')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'mfa_enabled')) {
                $table->boolean('mfa_enabled')->default(false)->after('custom_permissions');
            }
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('mfa_enabled');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('locked_until');
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => AdminPermissions::ROLE_SUPER_ADMIN]);
    }
    public function down(): void
    {
        DB::table('users')
            ->where('role', AdminPermissions::ROLE_SUPER_ADMIN)
            ->update(['role' => 'admin']);
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            $allColumns = [
                'custom_permissions',
                'mfa_enabled',
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
                'last_login_ip',
            ];
            foreach ($allColumns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $columns[] = $col;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
