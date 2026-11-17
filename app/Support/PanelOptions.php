<?php

namespace App\Support;

use App\Models\Instance;
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

class PanelOptions
{
    public static function general(): OptionsGeneral
    {
        return OptionsGeneral::firstOrCreate([], [
            'mods_enabled' => true,
            'file_verification' => true,
            'embedded_java' => false,
            'game_folder_name' => 'centralcorp',
            'email_verified' => false,
            'auth_mode' => 'azuriom',
            'role_display' => true,
            'money_display' => false,
            'microsoft_client_id' => null,
            'news_source' => 'azuriom',
            'min_ram' => 2048,
            'max_ram' => 4096,
        ]);
    }

    public static function ui(): OptionsUI
    {
        return OptionsUI::firstOrCreate([], [
            'alert_activation' => true,
            'alert_scroll' => false,
            'alert_msg' => 'Bienvenue sur le launcher',
            'video_activation' => false,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'splash' => 'Ceci est du code',
            'splash_author' => 'Riptiaz',
            'accent_color' => '#FFA500',
        ]);
    }

    public static function security(): OptionsSecurity
    {
        return OptionsSecurity::firstOrCreate([], [
            'maintenance' => false,
            'maintenance_message' => 'Maintenance en cours.',
            'maintenance_bypass_roles' => [],
            'maintenance_bypass_users' => [],
            'whitelist' => false,
        ]);
    }

    public static function rpc(): OptionsRPC
    {
        return OptionsRPC::firstOrCreate([], [
            'rpc_activation' => true,
            'rpc_id' => '1144257170561581097',
            'rpc_details' => 'Dans le launcher',
            'rpc_state' => 'En exploration',
            'rpc_large_text' => 'Minecraft',
            'rpc_small_text' => 'Multiplayer server',
            'rpc_button1' => 'Discord',
            'rpc_button1_url' => 'https://discord.gg/VCmNXHvf77',
            'rpc_button2' => 'Site Web',
            'rpc_button2_url' => 'https://conflictura.eu',
        ]);
    }

    public static function loader(): OptionsLoader
    {
        return OptionsLoader::firstOrCreate([], [
            'minecraft_version' => '1.20.1',
            'loader_activation' => true,
            'loader_type' => 'forge',
            'loader_forge_version' => null,
            'loader_build_version' => null,
        ]);
    }

    public static function ensureRoles(): PanelRole
    {
        if (PanelRole::count() === 0) {
            $defaultRole = PanelRole::create([
                'name' => 'Membre',
                'color' => '#00bd7a',
                'power' => 0,
                'is_default' => true,
                'bypass_maintenance' => false,
            ]);

            PanelRole::create([
                'name' => 'Admin',
                'color' => '#ef4444',
                'power' => 100,
                'is_default' => false,
                'bypass_maintenance' => true,
            ]);

            return $defaultRole;
        }

        $defaultRole = PanelRole::where('is_default', true)->first();
        if (!$defaultRole) {
            $defaultRole = PanelRole::orderBy('power')->orderBy('id')->first();
            $defaultRole?->update(['is_default' => true]);
        }

        return $defaultRole;
    }

    public static function defaultInstance(): Instance
    {
        $existing = Instance::where('is_default', true)->first()
            ?? Instance::orderBy('position')->orderBy('id')->first();

        if ($existing) {
            if (!$existing->is_default && !Instance::where('is_default', true)->exists()) {
                $existing->update(['is_default' => true]);
            }
            $existing->ensureDirectoriesExist();

            return $existing;
        }

        $general = OptionsGeneral::first();
        $loader = OptionsLoader::first();
        $security = OptionsSecurity::first();
        $defaultServer = OptionsServer::where('is_default', true)->first()
            ?? OptionsServer::first();

        $legacyDataPath = storage_path('app/public/data');
        $hasLegacyFiles = is_dir($legacyDataPath)
            && count(array_diff(scandir($legacyDataPath) ?: [], ['.', '..', '.gitignore'])) > 0;

        $instance = Instance::create([
            'slug' => 'default',
            'name' => 'Instance Principale',
            'description' => null,
            'is_default' => true,
            'is_enabled' => true,
            'position' => 0,
            'maintenance' => (bool) ($security?->maintenance ?? false),
            'maintenance_message' => $security?->maintenance_message,
            'maintenance_bypass_roles' => [],
            'maintenance_bypass_users' => [],
            'minecraft_version' => $loader?->minecraft_version ?: '1.20.1',
            'loader_type' => $loader?->loader_type ?: 'forge',
            'loader_build_version' => $loader?->loader_build_version,
            'loader_forge_version' => $loader?->loader_forge_version,
            'loader_activation' => (bool) ($loader?->loader_activation ?? true),
            'mods_enabled' => (bool) ($general?->mods_enabled ?? true),
            'file_verification' => (bool) ($general?->file_verification ?? true),
            'embedded_java' => (bool) ($general?->embedded_java ?? true),
            'java_version' => null,
            'jvm_args' => [],
            'game_args' => [],
            'auto_connect' => false,
            'min_ram' => (int) ($general?->min_ram ?? 2048),
            'max_ram' => (int) ($general?->max_ram ?? 4096),
            'server_id' => $defaultServer?->id,
            'whitelist_activate' => (bool) ($security?->whitelist ?? false),
            'custom_data_dir' => $hasLegacyFiles ? 'data' : 'instances/default',
        ]);

        OptionsMods::whereNull('instance_id')->update(['instance_id' => $instance->id]);
        OptionsIgnore::whereNull('instance_id')->update(['instance_id' => $instance->id]);
        OptionsWhitelist::whereNull('instance_id')->update(['instance_id' => $instance->id]);
        OptionsWhitelistRole::whereNull('instance_id')->update(['instance_id' => $instance->id]);

        $instance->ensureDirectoriesExist();

        return $instance;
    }

    public static function ensureDefaults(): void
    {
        self::general();
        self::ui();
        self::security();
        self::rpc();
        self::loader();
        self::ensureRoles();
        self::defaultInstance();
    }
}
