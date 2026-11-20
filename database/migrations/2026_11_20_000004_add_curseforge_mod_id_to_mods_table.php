<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mods') && !Schema::hasColumn('mods', 'curseforge_mod_id')) {
            Schema::table('mods', function (Blueprint $table) {
                if (Schema::hasTable('curseforge_mods')) {
                    $table->foreignId('curseforge_mod_id')
                        ->nullable()
                        ->after('instance_id')
                        ->constrained('curseforge_mods')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('curseforge_mod_id')
                        ->nullable()
                        ->after('instance_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mods') && Schema::hasColumn('mods', 'curseforge_mod_id')) {
            Schema::table('mods', function (Blueprint $table) {
                if (Schema::hasTable('curseforge_mods')) {
                    $table->dropConstrainedForeignId('curseforge_mod_id');
                } else {
                    $table->dropColumn('curseforge_mod_id');
                }
            });
        }
    }
};
