<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('player_api_tokens')) {
            Schema::create('player_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('token_hash', 64)->unique();
                $table->string('token_prefix', 12);
                $table->json('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('microsoft_players') && !Schema::hasColumn('microsoft_players', 'banned_until')) {
            Schema::table('microsoft_players', function (Blueprint $table) {
                $table->timestamp('banned_until')->nullable()->after('ban_reason');
            });
        }

        if (Schema::hasTable('options_security') && !Schema::hasColumn('options_security', 'player_api_enabled')) {
            Schema::table('options_security', function (Blueprint $table) {
                $table->boolean('player_api_enabled')->default(false)->after('whitelist');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_api_tokens');

        if (Schema::hasTable('microsoft_players') && Schema::hasColumn('microsoft_players', 'banned_until')) {
            Schema::table('microsoft_players', function (Blueprint $table) {
                $table->dropColumn('banned_until');
            });
        }

        if (Schema::hasTable('options_security') && Schema::hasColumn('options_security', 'player_api_enabled')) {
            Schema::table('options_security', function (Blueprint $table) {
                $table->dropColumn('player_api_enabled');
            });
        }
    }
};
