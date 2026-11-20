<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('curseforge_mods')) {
            Schema::create('curseforge_mods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('instance_id')->constrained('instances')->cascadeOnDelete();
                $table->unsignedBigInteger('curseforge_mod_id');
                $table->unsignedBigInteger('curseforge_file_id');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('logo_url', 512)->nullable();
                $table->string('file_name');
                $table->string('version')->nullable();
                $table->string('mc_version', 64)->nullable();
                $table->string('loader', 32)->nullable();
                $table->unsignedBigInteger('fingerprint')->nullable();
                $table->string('download_url', 1024)->nullable();
                $table->timestamps();

                $table->unique(['instance_id', 'curseforge_mod_id'], 'cf_mods_instance_mod_unique');
                $table->index(['instance_id', 'file_name'], 'cf_mods_instance_file_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('curseforge_mods');
    }
};
