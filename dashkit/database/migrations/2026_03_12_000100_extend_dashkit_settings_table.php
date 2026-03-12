<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashkit_settings')) {
            return;
        }

        Schema::table('dashkit_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashkit_settings', 'group')) {
                $table->string('group', 64)->default('general')->after('key');
                $table->index('group');
            }

            if (! Schema::hasColumn('dashkit_settings', 'value_type')) {
                $table->string('value_type', 32)->default('string')->after('value');
            }

            if (! Schema::hasColumn('dashkit_settings', 'autoload')) {
                $table->boolean('autoload')->default(true)->after('is_secret');
                $table->index('autoload');
            }

            if (! Schema::hasColumn('dashkit_settings', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('autoload');
                $table->index('updated_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashkit_settings')) {
            return;
        }

        Schema::table('dashkit_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('dashkit_settings', 'updated_by')) {
                $table->dropIndex(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('dashkit_settings', 'autoload')) {
                $table->dropIndex(['autoload']);
                $table->dropColumn('autoload');
            }

            if (Schema::hasColumn('dashkit_settings', 'value_type')) {
                $table->dropColumn('value_type');
            }

            if (Schema::hasColumn('dashkit_settings', 'group')) {
                $table->dropIndex(['group']);
                $table->dropColumn('group');
            }
        });
    }
};
