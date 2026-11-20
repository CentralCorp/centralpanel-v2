<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('curseforge_modpacks')) {
            Schema::create('curseforge_modpacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
                $table->unsignedBigInteger('curseforge_modpack_id');
                $table->unsignedBigInteger('curseforge_file_id');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('logo_url', 512)->nullable();
                $table->string('file_name')->nullable();
                $table->string('version')->nullable();
                $table->string('mc_version', 64)->nullable();
                $table->string('loader', 32)->nullable();
                $table->unsignedInteger('mods_count')->default(0);
                $table->string('download_url', 1024)->nullable();
                $table->timestamps();

                $table->unique(['instance_id', 'curseforge_modpack_id'], 'cf_modpacks_instance_pack_unique');
            });
        }

        if (Schema::hasTable('curseforge_mods') && !Schema::hasColumn('curseforge_mods', 'modpack_id')) {
            Schema::table('curseforge_mods', function (Blueprint $table) {
                $table->foreignId('modpack_id')
                    ->nullable()
                    ->after('instance_id')
                    ->constrained('curseforge_modpacks')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('curseforge_mods') && Schema::hasColumn('curseforge_mods', 'modpack_id')) {
            Schema::table('curseforge_mods', function (Blueprint $table) {
                $table->dropConstrainedForeignId('modpack_id');
            });
        }

        Schema::dropIfExists('curseforge_modpacks');
    }
};
