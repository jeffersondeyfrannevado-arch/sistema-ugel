<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Expands storage capacity of critical tables to prevent overflow/truncation errors.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_contents')) {
            Schema::table('system_contents', function (Blueprint $table) {
                $table->longText('body')->change();
                $table->text('flagged_reason')->nullable()->change();
                $table->longText('review_notes')->nullable()->change();
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('action', 255)->change();
                $table->text('target_label')->nullable()->change();
                $table->string('ip_address', 100)->nullable()->change();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 100)->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('system_contents')) {
            Schema::table('system_contents', function (Blueprint $table) {
                $table->text('body')->change();
                $table->string('flagged_reason')->nullable()->change();
                $table->text('review_notes')->nullable()->change();
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('action', 120)->change();
                $table->string('target_label')->nullable()->change();
                $table->string('ip_address', 45)->nullable()->change();
            });
        }
    }
};
