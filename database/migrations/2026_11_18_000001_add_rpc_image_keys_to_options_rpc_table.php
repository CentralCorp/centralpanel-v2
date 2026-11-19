<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('options_rpc')) {
            return;
        }

        Schema::table('options_rpc', function (Blueprint $table) {
            if (!Schema::hasColumn('options_rpc', 'rpc_large_image')) {
                $table->string('rpc_large_image')->nullable()->default('large')->after('rpc_state');
            }
            if (!Schema::hasColumn('options_rpc', 'rpc_small_image')) {
                $table->string('rpc_small_image')->nullable()->default('small')->after('rpc_large_text');
            }
        });

        DB::table('options_rpc')->whereNull('rpc_large_image')->update(['rpc_large_image' => 'large']);
        DB::table('options_rpc')->whereNull('rpc_small_image')->update(['rpc_small_image' => 'small']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('options_rpc')) {
            return;
        }

        $columns = array_values(array_filter(
            ['rpc_large_image', 'rpc_small_image'],
            static fn (string $column): bool => Schema::hasColumn('options_rpc', $column)
        ));

        if ($columns !== []) {
            Schema::table('options_rpc', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
