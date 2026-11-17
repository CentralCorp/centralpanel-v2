<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('background_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->integer('position')->default(0);
            $table->boolean('maintenance')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->json('maintenance_bypass_roles')->nullable();
            $table->json('maintenance_bypass_users')->nullable();
            $table->string('minecraft_version', 50)->default('1.20.1');
            $table->string('loader_type', 50)->nullable();
            $table->string('loader_build_version', 100)->nullable();
            $table->string('loader_forge_version', 100)->nullable();
            $table->boolean('loader_activation')->default(false);
            $table->boolean('mods_enabled')->default(true);
            $table->boolean('file_verification')->default(true);
            $table->boolean('embedded_java')->default(true);
            $table->integer('java_version')->nullable();
            $table->json('jvm_args')->nullable();
            $table->json('game_args')->nullable();
            $table->boolean('auto_connect')->default(false);
            $table->integer('min_ram')->default(2048);
            $table->integer('max_ram')->default(4096);
            $table->foreignId('server_id')->nullable()->constrained('options_server')->nullOnDelete();
            $table->boolean('whitelist_activate')->default(false);
            $table->string('custom_data_dir')->nullable();
            $table->timestamps();
        });

        Schema::table('mods', function (Blueprint $table) {
            $table->foreignId('instance_id')->nullable()->after('id')->constrained('instances')->cascadeOnDelete();
        });

        Schema::table('ignored_folders', function (Blueprint $table) {
            $table->foreignId('instance_id')->nullable()->after('id')->constrained('instances')->cascadeOnDelete();
        });

        Schema::table('whitelist', function (Blueprint $table) {
            $table->foreignId('instance_id')->nullable()->after('id')->constrained('instances')->cascadeOnDelete();
        });

        Schema::table('whitelist_roles', function (Blueprint $table) {
            $table->foreignId('instance_id')->nullable()->after('id')->constrained('instances')->cascadeOnDelete();
        });

        Schema::table('options_security', function (Blueprint $table) {
            $table->json('maintenance_bypass_roles')->nullable()->after('maintenance_message');
            $table->json('maintenance_bypass_users')->nullable()->after('maintenance_bypass_roles');
        });

        Schema::table('options_server', function (Blueprint $table) {
            $table->integer('server_id')->nullable()->change();
        });

        // Data Migration: if legacy singleton tables already hold configuration, migrate to default Instance
        $hasExistingSettings = DB::table('options_general')->exists()
            || DB::table('options_loader')->exists()
            || DB::table('options_security')->exists();

        if ($hasExistingSettings && !DB::table('instances')->exists()) {
            $general = DB::table('options_general')->first();
            $loader = DB::table('options_loader')->first();
            $security = DB::table('options_security')->first();
            $defaultServer = DB::table('options_server')->where('is_default', true)->first()
                ?? DB::table('options_server')->first();

            $legacyDataPath = storage_path('app/public/data');
            $hasLegacyFiles = is_dir($legacyDataPath)
                && count(array_diff(scandir($legacyDataPath) ?: [], ['.', '..', '.gitignore'])) > 0;

            $now = now();
            $instanceId = DB::table('instances')->insertGetId([
                'slug' => 'default',
                'name' => 'Instance Principale',
                'description' => null,
                'icon_path' => null,
                'background_path' => null,
                'is_default' => true,
                'is_enabled' => true,
                'position' => 0,
                'maintenance' => (bool) ($security->maintenance ?? false),
                'maintenance_message' => $security->maintenance_message ?? null,
                'maintenance_bypass_roles' => json_encode([]),
                'maintenance_bypass_users' => json_encode([]),
                'minecraft_version' => $loader->minecraft_version ?? '1.20.1',
                'loader_type' => $loader->loader_type ?? 'forge',
                'loader_build_version' => $loader->loader_build_version ?? null,
                'loader_forge_version' => $loader->loader_forge_version ?? null,
                'loader_activation' => (bool) ($loader->loader_activation ?? false),
                'mods_enabled' => (bool) ($general->mods_enabled ?? true),
                'file_verification' => (bool) ($general->file_verification ?? true),
                'embedded_java' => (bool) ($general->embedded_java ?? true),
                'java_version' => null,
                'jvm_args' => json_encode([]),
                'game_args' => json_encode([]),
                'auto_connect' => false,
                'min_ram' => (int) ($general->min_ram ?? 2048),
                'max_ram' => (int) ($general->max_ram ?? 4096),
                'server_id' => $defaultServer->id ?? null,
                'whitelist_activate' => (bool) ($security->whitelist ?? false),
                'custom_data_dir' => $hasLegacyFiles ? 'data' : 'instances/default',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('mods')->whereNull('instance_id')->update(['instance_id' => $instanceId]);
            DB::table('ignored_folders')->whereNull('instance_id')->update(['instance_id' => $instanceId]);
            DB::table('whitelist')->whereNull('instance_id')->update(['instance_id' => $instanceId]);
            DB::table('whitelist_roles')->whereNull('instance_id')->update(['instance_id' => $instanceId]);
        }
    }

    public function down(): void
    {
        Schema::table('options_security', function (Blueprint $table) {
            $table->dropColumn(['maintenance_bypass_roles', 'maintenance_bypass_users']);
        });

        Schema::table('whitelist_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instance_id');
        });

        Schema::table('whitelist', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instance_id');
        });

        Schema::table('ignored_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instance_id');
        });

        Schema::table('mods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instance_id');
        });

        Schema::dropIfExists('instances');
    }
};
