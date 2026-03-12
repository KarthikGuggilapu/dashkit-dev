<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashkit_user_preferences')) {
            return;
        }

        Schema::create('dashkit_user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type', 32)->default('string');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashkit_user_preferences');
    }
};
