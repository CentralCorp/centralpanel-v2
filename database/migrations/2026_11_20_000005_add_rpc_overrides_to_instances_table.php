<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('instances')) {
            Schema::table('instances', function (Blueprint $table) {
                if (!Schema::hasColumn('instances', 'rpc_details')) {
                    $table->string('rpc_details', 255)->nullable()->after('custom_data_dir');
                }
                if (!Schema::hasColumn('instances', 'rpc_state')) {
                    $table->string('rpc_state', 255)->nullable()->after('rpc_details');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('instances')) {
            Schema::table('instances', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['rpc_details', 'rpc_state'],
                    static fn (string $col): bool => Schema::hasColumn('instances', $col)
                ));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
