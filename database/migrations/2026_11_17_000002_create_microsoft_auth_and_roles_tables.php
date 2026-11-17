<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('options_general', function (Blueprint $table) {
            $table->string('auth_mode', 20)->default('azuriom')->after('email_verified');
            $table->string('microsoft_client_id', 255)->nullable()->after('azuriom_api_key');
            $table->string('news_source', 20)->default('azuriom')->after('microsoft_client_id');
        });

        Schema::create('panel_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->default('#00bd7a');
            $table->string('background_path')->nullable();
            $table->integer('power')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('bypass_maintenance')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('panel_roles')->insert([
            [
                'name' => 'Membre',
                'color' => '#00bd7a',
                'background_path' => null,
                'power' => 0,
                'is_default' => true,
                'bypass_maintenance' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Admin',
                'color' => '#ef4444',
                'background_path' => null,
                'power' => 100,
                'is_default' => false,
                'bypass_maintenance' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('microsoft_players', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->nullable()->unique();
            $table->string('username')->unique();
            $table->string('xuid', 64)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('panel_roles')->nullOnDelete();
            $table->integer('money')->default(0);
            $table->boolean('is_banned')->default(false);
            $table->string('ban_reason')->nullable();
            $table->string('skin_url', 500)->nullable();
            $table->string('cape_url', 500)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microsoft_players');
        Schema::dropIfExists('panel_roles');

        Schema::table('options_general', function (Blueprint $table) {
            $table->dropColumn(['auth_mode', 'microsoft_client_id', 'news_source']);
        });
    }
};
