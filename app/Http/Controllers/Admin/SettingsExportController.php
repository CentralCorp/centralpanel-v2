<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\MicrosoftPlayer;
use App\Models\News;
use App\Models\OptionsBg;
use App\Models\OptionsGeneral;
use App\Models\OptionsIgnore;
use App\Models\OptionsLoader;
use App\Models\OptionsMods;
use App\Models\OptionsRPC;
use App\Models\OptionsSecurity;
use App\Models\OptionsServer;
use App\Models\OptionsUI;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use App\Models\PanelRole;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsExportController extends Controller
{
    private const SUPPORTED_VERSIONS = ['1.0', '2.0'];

    /**
     * Ordered parent -> child for FK-safe insertion (reversed for deletion).
     */
    private const ORDERED_TABLES = [
        'options_general',
        'options_ui',
        'options_rpc',
        'options_security',
        'options_loader',
        'options_bgs',
        'options_server',
        'panel_roles',
        'instances',
        'news',
        'microsoft_players',
        'mods',
        'ignored_folders',
        'whitelist',
        'whitelist_roles',
    ];

    public function export()
    {
        $settings = [
            'version' => '2.0',
            'export_date' => now()->format('Y-m-d H:i:s'),
            'data' => [
                'options_general' => OptionsGeneral::all(),
                'options_ui' => OptionsUI::all(),
                'options_server' => OptionsServer::all(),
                'options_rpc' => OptionsRPC::all(),
                'options_security' => OptionsSecurity::all(),
                'options_loader' => OptionsLoader::all(),
                'options_bgs' => OptionsBg::all(),
                'panel_roles' => PanelRole::all(),
                'instances' => Instance::all(),
                'news' => News::all(),
                'microsoft_players' => MicrosoftPlayer::all(),
                'mods' => OptionsMods::all(),
                'ignored_folders' => OptionsIgnore::all(),
                'whitelist' => OptionsWhitelist::all(),
                'whitelist_roles' => OptionsWhitelistRole::all(),
            ],
        ];

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = 'centralcorp_settings_' . date('Y-m-d_H-i-s') . '.centralcorp';

        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function import(Request $request)
    {
        $request->validate([
            'settings_file' => 'required|file|max:10240',
        ]);

        $rawContent = file_get_contents($request->file('settings_file')->path());
        $settings = is_string($rawContent) ? json_decode($rawContent, true) : null;

        if (!is_array($settings) || !isset($settings['data']) || !is_array($settings['data'])) {
            return back()->with('error', __('messages.flash.import_invalid_file'));
        }

        $version = (string) ($settings['version'] ?? '');
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return back()->with('error', __('messages.flash.import_unsupported_version'));
        }

        foreach (array_keys($settings['data']) as $table) {
            if (!in_array($table, self::ORDERED_TABLES, true)) {
                return back()->with('error', __('messages.flash.import_error') . " Table non autorisée : {$table}");
            }
        }

        try {
            DB::transaction(function () use ($settings) {
                $tablesInPayload = array_values(array_filter(
                    self::ORDERED_TABLES,
                    fn (string $table) => array_key_exists($table, $settings['data']) && Schema::hasTable($table)
                ));

                if (!array_key_exists('instances', $settings['data']) && Schema::hasTable('instances')) {
                    DB::table('instances')->delete();
                }

                // Delete child tables before parent tables
                foreach (array_reverse($tablesInPayload) as $table) {
                    DB::table($table)->delete();
                }

                // Insert parent tables before child tables
                foreach ($tablesInPayload as $table) {
                    $columns = Schema::getColumnListing($table);

                    foreach ($settings['data'][$table] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        unset($row['created_at'], $row['updated_at']);

                        $filtered = [];
                        foreach ($row as $col => $val) {
                            if (!in_array($col, $columns, true)) {
                                continue;
                            }
                            $filtered[$col] = is_array($val) ? json_encode($val) : $val;
                        }

                        if ($filtered !== []) {
                            DB::table($table)->insert($filtered);
                        }
                    }
                }
            });

            // Ensure roles and default instance exist even after importing a legacy v1.0 archive
            PanelOptions::ensureRoles();
            PanelOptions::defaultInstance();
            LauncherCache::flush();

            return back()->with('success', __('messages.flash.config_imported'));
        } catch (\Throwable $e) {
            return back()->with('error', __('messages.flash.import_error') . ' ' . $e->getMessage());
        }
    }
}
