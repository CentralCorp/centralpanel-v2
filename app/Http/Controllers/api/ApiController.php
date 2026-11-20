<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\OptionsBg;
use App\Models\OptionsGeneral;
use App\Models\OptionsIgnore;
use App\Models\OptionsLoader;
use App\Models\OptionsRPC;
use App\Models\OptionsSecurity;
use App\Models\OptionsServer;
use App\Models\OptionsUI;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use App\Models\PanelRole;
use App\Support\LauncherCache;
use App\Support\LauncherResponse;
use App\Support\YouTubeUrl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function getOptions(Request $request): Response
    {
        $baseURL = $this->resolveBaseUrl($request);

        $data = LauncherCache::remember('launcher_options', sha1($baseURL), now()->addMinutes(30), function () use ($baseURL) {
            $general = OptionsGeneral::first();
            $security = OptionsSecurity::first();
            $ui = OptionsUI::first();
            $rpc = OptionsRPC::first();
            $loader = OptionsLoader::first();
            $defaultServer = OptionsServer::where('is_default', true)->first()
                ?? OptionsServer::first();

            $azuriomUrl = $general?->azuriom_url;
            $panelBypassRoles = PanelRole::where('bypass_maintenance', true)->pluck('name')->all();

            $globalIgnored = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();
            $globalWhitelist = OptionsWhitelist::whereNull('instance_id')->pluck('users')->filter()->values()->all();
            $globalWhitelistRoles = OptionsWhitelistRole::whereNull('instance_id')->pluck('role')->filter()->values()->all();

            $roleData = $this->buildRoleData($baseURL);

            $instances = Instance::with(['server', 'whitelists', 'whitelistRoles', 'ignoredFolders'])
                ->where('is_enabled', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            if ($instances->isNotEmpty()) {
                $instancePayloads = $instances->map(
                    fn (Instance $instance) => $instance->toLauncherPayload(
                        $baseURL,
                        $defaultServer,
                        $globalWhitelist,
                        $globalWhitelistRoles,
                        $globalIgnored,
                        $azuriomUrl,
                        true,
                        $panelBypassRoles,
                        $rpc,
                        true
                    )
                )->values()->all();

                $defaultPayload = collect($instancePayloads)->firstWhere('is_default', true) ?? $instancePayloads[0];
            } else {
                $defaultPayload = $this->buildLegacyFallbackInstancePayload(
                    $baseURL,
                    $general,
                    $loader,
                    $security,
                    $defaultServer,
                    $globalWhitelist,
                    $globalWhitelistRoles,
                    $globalIgnored,
                    $azuriomUrl,
                    $panelBypassRoles,
                    $rpc
                );
                $instancePayloads = [$defaultPayload];
            }

            $authMode = in_array($general?->auth_mode, ['azuriom', 'microsoft', 'both'], true)
                ? $general->auth_mode
                : 'azuriom';
            $azuriomAuth = in_array($authMode, ['azuriom', 'both'], true);
            $microsoftAuth = in_array($authMode, ['microsoft', 'both'], true);
            $clientId = $microsoftAuth
                ? ($general?->microsoft_client_id ?: '00000000402b5328')
                : '';

            $newsSource = in_array($general?->news_source, ['panel', 'azuriom', 'both'], true)
                ? $general->news_source
                : 'azuriom';

            $bypassRoles = array_values(array_unique(array_merge(
                array_filter($security?->maintenance_bypass_roles ?? []),
                $panelBypassRoles
            )));
            $bypassUsers = array_values(array_filter($security?->maintenance_bypass_users ?? []));

            return [
                'skin_api' => true,
                'auth_mode' => $authMode,
                'azuriom_auth' => $azuriomAuth,
                'microsoft_auth' => $microsoftAuth,
                'client_id' => $clientId,
                'news_source' => $newsSource,
                'news_url' => $baseURL . '/utils/news',
                'maintenance' => $security ? (bool) $security->maintenance : false,
                'maintenance_message' => $security ? $security->maintenance_message : 'Please define a maintenance message',
                'maintenance_bypass_roles' => $bypassRoles,
                'maintenance_bypass_users' => $bypassUsers,
                'game_version' => $defaultPayload['game_version'],
                'verify' => $defaultPayload['verify'],
                'modde' => $defaultPayload['modde'],
                'java' => $defaultPayload['java'],
                'java_version' => $defaultPayload['java_version'],
                'dataDirectory' => $general ? ($general->game_folder_name ?: 'centralcorp') : 'centralcorp',
                'status' => $defaultPayload['status'],
                'loader' => $defaultPayload['loader'],
                'ram_min' => $defaultPayload['ram_min'],
                'ram_max' => $defaultPayload['ram_max'],
                'online' => 'true',
                'jvm_args' => $defaultPayload['jvm_args'],
                'game_args' => $defaultPayload['game_args'],
                'auto_connect' => $defaultPayload['auto_connect'],
                'money' => $general ? (bool) $general->money_display : true,
                'role' => $general ? (bool) $general->role_display : true,
                'splash' => $ui ? ($ui->splash ?? '') : 'Ceci est du code',
                'splash_author' => $ui ? ($ui->splash_author ?? '') : 'Riptiaz',
                'accent_color' => $ui ? $ui->accent_color : '#FFA500',
                'azauth' => $general ? $general->azuriom_url : null,
                'rpc_activation' => $rpc ? (bool) $rpc->rpc_activation : true,
                'rpc_id' => $rpc ? $rpc->rpc_id : '114425717056158109',
                'rpc_details' => $rpc ? $rpc->rpc_details : 'Dans le launcher',
                'rpc_state' => $rpc ? $rpc->rpc_state : 'En exploration',
                'rpc_large_image' => $rpc ? $rpc->rpc_large_image : 'large',
                'rpc_large_text' => $rpc ? $rpc->rpc_large_text : 'Minecraft',
                'rpc_small_image' => $rpc ? $rpc->rpc_small_image : 'small',
                'rpc_small_text' => $rpc ? $rpc->rpc_small_text : 'Multiplayer server',
                'rpc_button1' => $rpc ? $rpc->rpc_button1 : 'Discord',
                'rpc_button1_url' => $rpc ? $rpc->rpc_button1_url : 'https://discord.gg/VCmNXHvf77',
                'rpc_button2' => $rpc ? $rpc->rpc_button2 : 'Site Web',
                'rpc_button2_url' => $rpc ? $rpc->rpc_button2_url : 'https://conflictura.eu',
                'whitelist_activate' => $defaultPayload['whitelist_activate'],
                'alert_activate' => $ui ? (bool) $ui->alert_activation : true,
                'alert_scroll' => $ui ? (bool) $ui->alert_scroll : true,
                'alert_msg' => $ui ? ($ui->alert_msg ?? '') : 'Test',
                'video_activate' => $ui ? (bool) $ui->video_activation : true,
                'video_url' => $ui ? (YouTubeUrl::extractVideoId($ui->video_url) ?? '') : 'a336KPLjsZU',
                'video_type' => $ui ? YouTubeUrl::detectType($ui->video_url) : 'short',
                'email_verified' => $general ? (bool) $general->email_verified : false,
                'server_icon' => $defaultPayload['server_icon'],
                'role_data' => (object) $roleData,
                'ignored' => $defaultPayload['ignored'],
                'whitelist' => $defaultPayload['whitelist'],
                'whitelist_roles' => $defaultPayload['whitelist_roles'],
                'instances' => $instancePayloads,
            ];
        });

        $includeRaw = (string) $request->query('include', '');
        if ($includeRaw !== '') {
            $includes = array_values(array_unique(array_filter(
                array_map(static fn (string $token): string => strtolower(trim($token)), explode(',', $includeRaw))
            )));

            if (in_array('mods', $includes, true)) {
                $data['mods_by_instance'] = (object) ModController::getCachedModsByInstance($baseURL);
            }

            if (in_array('news', $includes, true) && in_array($data['news_source'] ?? 'azuriom', ['panel', 'both'], true)) {
                $data['included_news'] = NewsController::getCachedPublishedNews($baseURL);
            }
        }

        return LauncherResponse::json($request, $data);
    }

    public function getInstances(Request $request): Response
    {
        $baseURL = $this->resolveBaseUrl($request);

        $payloads = LauncherCache::remember('launcher_instances', sha1($baseURL), now()->addMinutes(30), function () use ($baseURL) {
            return $this->computeInstancePayloads($baseURL);
        });

        return LauncherResponse::json($request, $payloads);
    }

    public function getInstance(Request $request, string $slug): Response
    {
        $baseURL = $this->resolveBaseUrl($request);

        $payloads = LauncherCache::remember('launcher_instances', sha1($baseURL), now()->addMinutes(30), function () use ($baseURL) {
            return $this->computeInstancePayloads($baseURL);
        });

        $found = collect($payloads)->firstWhere('slug', $slug);
        if (!$found) {
            return LauncherResponse::json($request, [
                'status' => 'error',
                'message' => 'Instance not found',
            ], 404);
        }

        return LauncherResponse::json($request, $found);
    }

    private function computeInstancePayloads(string $baseURL): array
    {
        $general = OptionsGeneral::first();
        $rpc = OptionsRPC::first();
        $azuriomUrl = $general?->azuriom_url;
        $panelBypassRoles = PanelRole::where('bypass_maintenance', true)->pluck('name')->all();

        $defaultServer = OptionsServer::where('is_default', true)->first()
            ?? OptionsServer::first();
        $globalIgnored = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();
        $globalWhitelist = OptionsWhitelist::whereNull('instance_id')->pluck('users')->filter()->values()->all();
        $globalWhitelistRoles = OptionsWhitelistRole::whereNull('instance_id')->pluck('role')->filter()->values()->all();

        $instances = Instance::with(['server', 'whitelists', 'whitelistRoles', 'ignoredFolders'])
            ->where('is_enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($instances->isNotEmpty()) {
            return $instances->map(
                fn (Instance $instance) => $instance->toLauncherPayload(
                    $baseURL,
                    $defaultServer,
                    $globalWhitelist,
                    $globalWhitelistRoles,
                    $globalIgnored,
                    $azuriomUrl,
                    true,
                    $panelBypassRoles,
                    $rpc,
                    true
                )
            )->values()->all();
        }

        return [
            $this->buildLegacyFallbackInstancePayload(
                $baseURL,
                $general,
                OptionsLoader::first(),
                OptionsSecurity::first(),
                $defaultServer,
                $globalWhitelist,
                $globalWhitelistRoles,
                $globalIgnored,
                $azuriomUrl,
                $panelBypassRoles,
                $rpc
            ),
        ];
    }

    private function buildRoleData(string $baseURL): array
    {
        $roleData = [];

        foreach (OptionsBg::all() as $role) {
            $roleData['role' . $role->id] = [
                'name' => $role->role_name,
                'background' => $baseURL . '/storage/' . ltrim($role->image_path, '/'),
            ];
        }

        foreach (PanelRole::whereNotNull('background_path')->get() as $panelRole) {
            if ($panelRole->background_path === '') {
                continue;
            }
            $roleData['panel_role' . $panelRole->id] = [
                'name' => $panelRole->name,
                'background' => $baseURL . '/storage/' . ltrim($panelRole->background_path, '/'),
            ];
        }

        return $roleData;
    }

    private function buildLegacyFallbackInstancePayload(
        string $baseURL,
        ?OptionsGeneral $general,
        ?OptionsLoader $loader,
        ?OptionsSecurity $security,
        ?OptionsServer $server,
        array $whitelist,
        array $whitelistRoles,
        array $ignored,
        ?string $azuriomUrl = null,
        array $panelBypassRoles = [],
        ?OptionsRPC $rpc = null
    ): array {
        $port = $server ? $server->server_port : 25566;
        $portValue = is_numeric($port) ? (int) $port : $port;
        $instanceName = $server ? $server->server_name : 'Instance Principale';

        $bypassRoles = array_values(array_unique(array_merge(
            array_filter($security?->maintenance_bypass_roles ?? []),
            array_filter($panelBypassRoles)
        )));

        return [
            'id' => 1,
            'slug' => 'default',
            'name' => $instanceName,
            'description' => null,
            'icon' => null,
            'background' => null,
            'is_default' => true,
            'position' => 0,
            'maintenance' => $security ? (bool) $security->maintenance : false,
            'maintenance_message' => $security ? $security->maintenance_message : null,
            'maintenance_bypass_roles' => $bypassRoles,
            'maintenance_bypass_users' => array_values(array_filter($security?->maintenance_bypass_users ?? [])),
            'game_version' => $loader ? ($loader->minecraft_version ?: '1.7.10') : '1.7.10',
            'verify' => $general ? (bool) $general->file_verification : true,
            'modde' => $general ? (bool) $general->mods_enabled : true,
            'java' => $general ? (bool) $general->embedded_java : true,
            'java_version' => null,
            'jvm_args' => [],
            'game_args' => [],
            'auto_connect' => false,
            'ram_min' => $general ? ($general->min_ram / 1024) : 2,
            'ram_max' => $general ? ($general->max_ram / 1024) : 4,
            'status' => [
                'nameServer' => $server ? $server->server_name : 'Syphera',
                'ip' => $server ? (string) $server->server_ip : '',
                'port' => $portValue,
            ],
            'server_icon' => $server ? $server->iconUrlFor($baseURL, $azuriomUrl, true) : null,
            'loader' => [
                'type' => $loader ? ($loader->loader_type ?: 'forge') : 'forge',
                'build' => $loader ? (string) ($loader->loader_build_version ?? '1.7.10-10.13.4.1614-1.7.10') : '1.7.10-10.13.4.1614-1.7.10',
                'enable' => $loader ? (bool) $loader->loader_activation : true,
            ],
            'rpc_details' => str_replace('{instance}', (string) $instanceName, (string) ($rpc?->rpc_details ?? 'Dans le launcher')),
            'rpc_state' => str_replace('{instance}', (string) $instanceName, (string) ($rpc?->rpc_state ?? 'En exploration')),
            'whitelist_activate' => $security ? (bool) $security->whitelist : false,
            'whitelist' => $whitelist,
            'whitelist_roles' => $whitelistRoles,
            'ignored' => $ignored,
            'files_url' => $baseURL . '/utils/instances/default/files',
            'mods_url' => $baseURL . '/utils/instances/default/mods',
        ];
    }

    private function resolveBaseUrl(Request $request): string
    {
        $domain = $request->getHost();
        $protocol = $request->isSecure() ? 'https' : 'http';
        $port = $request->getPort();

        return $protocol . '://' . $domain . (($port && !in_array($port, [80, 443], true)) ? ":$port" : '');
    }
}
