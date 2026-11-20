<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('options_general') && !Schema::hasColumn('options_general', 'curseforge_api_key')) {
            Schema::table('options_general', function (Blueprint $table) {
                $table->string('curseforge_api_key', 255)->nullable()->after('azuriom_api_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('options_general') && Schema::hasColumn('options_general', 'curseforge_api_key')) {
            Schema::table('options_general', function (Blueprint $table) {
                $table->dropColumn('curseforge_api_key');
            });
        }
    }
};
