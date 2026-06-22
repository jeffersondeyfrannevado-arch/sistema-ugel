<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type', 50)->default('announcement');
            $table->string('status', 30)->default('draft');
            $table->boolean('is_flagged')->default(false);
            $table->string('flagged_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_contents');
    }
};
