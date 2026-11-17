<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;

class Instance extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'instances';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon_path',
        'background_path',
        'is_default',
        'is_enabled',
        'position',
        'maintenance',
        'maintenance_message',
        'maintenance_bypass_roles',
        'maintenance_bypass_users',
        'minecraft_version',
        'loader_type',
        'loader_build_version',
        'loader_forge_version',
        'loader_activation',
        'mods_enabled',
        'file_verification',
        'embedded_java',
        'java_version',
        'jvm_args',
        'game_args',
        'auto_connect',
        'min_ram',
        'max_ram',
        'server_id',
        'whitelist_activate',
        'custom_data_dir',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'position' => 'integer',
        'maintenance' => 'boolean',
        'maintenance_bypass_roles' => 'array',
        'maintenance_bypass_users' => 'array',
        'loader_activation' => 'boolean',
        'mods_enabled' => 'boolean',
        'file_verification' => 'boolean',
        'embedded_java' => 'boolean',
        'java_version' => 'integer',
        'jvm_args' => 'array',
        'game_args' => 'array',
        'auto_connect' => 'boolean',
        'min_ram' => 'integer',
        'max_ram' => 'integer',
        'server_id' => 'integer',
        'whitelist_activate' => 'boolean',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(OptionsServer::class, 'server_id');
    }

    public function mods(): HasMany
    {
        return $this->hasMany(OptionsMods::class, 'instance_id');
    }

    public function ignoredFolders(): HasMany
    {
        return $this->hasMany(OptionsIgnore::class, 'instance_id');
    }

    public function whitelists(): HasMany
    {
        return $this->hasMany(OptionsWhitelist::class, 'instance_id');
    }

    public function whitelistRoles(): HasMany
    {
        return $this->hasMany(OptionsWhitelistRole::class, 'instance_id');
    }

    /**
     * Relative directory under storage/app/public (e.g. 'data' or 'instances/survival').
     */
    public function resolvedDataDir(): string
    {
        $dir = trim((string) ($this->custom_data_dir ?: ('instances/' . $this->slug)), '/\\');

        return $dir !== '' ? str_replace('\\', '/', $dir) : ('instances/' . $this->slug);
    }

    public function resolvedStoragePath(): string
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->path($this->resolvedDataDir());
    }

    public function resolvedPublicUrlPrefix(?string $baseUrl = null): string
    {
        $prefix = 'storage/' . $this->resolvedDataDir();

        return $baseUrl ? rtrim($baseUrl, '/') . '/' . $prefix : url($prefix);
    }

    public function ensureDirectoriesExist(): void
    {
        $root = $this->resolvedStoragePath();
        $mods = $root . DIRECTORY_SEPARATOR . 'mods';

        if (!File::isDirectory($root)) {
            File::makeDirectory($root, 0755, true);
        }

        if (!File::isDirectory($mods)) {
            File::makeDirectory($mods, 0755, true);
        }
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? asset('storage/' . ltrim($this->icon_path, '/')) : null;
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_path ? asset('storage/' . ltrim($this->background_path, '/')) : null;
    }

    public function toLauncherPayload(
        string $baseURL,
        ?OptionsServer $fallbackServer = null,
        array $globalWhitelist = [],
        array $globalWhitelistRoles = [],
        array $globalIgnored = []
    ): array {
        $baseURL = rtrim($baseURL, '/');
        $server = $this->relationLoaded('server') ? $this->server : ($this->server_id ? $this->server()->first() : null);
        $server ??= $fallbackServer;

        $instanceWhitelist = $this->relationLoaded('whitelists')
            ? $this->whitelists->pluck('users')->filter()->all()
            : $this->whitelists()->pluck('users')->filter()->all();

        $instanceWhitelistRoles = $this->relationLoaded('whitelistRoles')
            ? $this->whitelistRoles->pluck('role')->filter()->all()
            : $this->whitelistRoles()->pluck('role')->filter()->all();

        $instanceIgnored = $this->relationLoaded('ignoredFolders')
            ? $this->ignoredFolders->pluck('folder_name')->filter()->all()
            : $this->ignoredFolders()->pluck('folder_name')->filter()->all();

        $mergedWhitelist = array_values(array_unique(array_merge($globalWhitelist, $instanceWhitelist)));
        $mergedWhitelistRoles = array_values(array_unique(array_merge($globalWhitelistRoles, $instanceWhitelistRoles)));
        $mergedIgnored = array_values(array_unique(array_merge($globalIgnored, $instanceIgnored)));

        $port = $server ? $server->server_port : 25565;
        $portValue = is_numeric($port) ? (int) $port : $port;

        return [
            'id' => (int) $this->id,
            'slug' => (string) $this->slug,
            'name' => (string) $this->name,
            'description' => $this->description,
            'icon' => $this->icon_path ? ($baseURL . '/storage/' . ltrim($this->icon_path, '/')) : null,
            'background' => $this->background_path ? ($baseURL . '/storage/' . ltrim($this->background_path, '/')) : null,
            'is_default' => (bool) $this->is_default,
            'position' => (int) $this->position,
            'maintenance' => (bool) $this->maintenance,
            'maintenance_message' => $this->maintenance_message,
            'maintenance_bypass_roles' => array_values(array_filter($this->maintenance_bypass_roles ?? [])),
            'maintenance_bypass_users' => array_values(array_filter($this->maintenance_bypass_users ?? [])),
            'game_version' => (string) ($this->minecraft_version ?: '1.20.1'),
            'verify' => (bool) $this->file_verification,
            'modde' => (bool) $this->mods_enabled,
            'java' => (bool) $this->embedded_java,
            'java_version' => $this->java_version !== null ? (int) $this->java_version : null,
            'jvm_args' => array_values(array_filter($this->jvm_args ?? [], fn ($v) => $v !== null && $v !== '')),
            'game_args' => array_values(array_filter($this->game_args ?? [], fn ($v) => $v !== null && $v !== '')),
            'auto_connect' => (bool) $this->auto_connect,
            'ram_min' => ($this->min_ram ?: 2048) / 1024,
            'ram_max' => ($this->max_ram ?: 4096) / 1024,
            'status' => [
                'nameServer' => $server ? $server->server_name : $this->name,
                'ip' => $server ? $server->server_ip : '127.0.0.1',
                'port' => $portValue,
            ],
            'server_icon' => $server ? $server->iconUrlFor($baseURL) : null,
            'loader' => [
                'type' => $this->loader_type ?: 'forge',
                'build' => (string) ($this->loader_build_version ?? $this->loader_forge_version ?? ''),
                'enable' => (bool) $this->loader_activation,
            ],
            'whitelist_activate' => (bool) $this->whitelist_activate,
            'whitelist' => $mergedWhitelist,
            'whitelist_roles' => $mergedWhitelistRoles,
            'ignored' => $mergedIgnored,
            'files_url' => $baseURL . '/utils/instances/' . rawurlencode($this->slug) . '/files',
            'mods_url' => $baseURL . '/utils/instances/' . rawurlencode($this->slug) . '/mods',
        ];
    }
}
