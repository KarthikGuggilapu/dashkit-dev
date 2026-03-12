<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashkit_audit_logs')) {
            return;
        }

        Schema::create('dashkit_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 128);
            $table->string('target_type', 128)->nullable();
            $table->string('target_key', 255)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['target_type', 'target_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashkit_audit_logs');
    }
};
