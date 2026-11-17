<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Models\OptionsServer;
use App\Models\PanelRole;
use App\Request\AzuriomApi;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminInstanceController extends Controller
{
    public function index()
    {
        $instances = ActiveInstance::all()->load('server');
        $activeInstance = ActiveInstance::current();

        return view('admin.instances.index', compact('instances', 'activeInstance'));
    }

    public function create()
    {
        $servers = OptionsServer::orderByDesc('is_default')->orderBy('server_name')->get();
        $availableRoles = $this->collectAvailableRoleNames();

        return view('admin.instances.create', compact('servers', 'availableRoles'));
    }

    public function store(Request $request)
    {
        $rawSlug = trim((string) $request->input('slug', ''));
        $slugCandidate = $rawSlug !== '' ? Str::slug($rawSlug) : Str::slug((string) $request->input('name', 'instance'));
        if ($slugCandidate === '') {
            $slugCandidate = 'instance-' . Str::lower(Str::random(4));
        }

        $request->merge(['slug' => $slugCandidate]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-_]+$/', 'unique:instances,slug'],
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'background' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'position' => 'nullable|integer|min:0|max:9999',
            'minecraft_version' => 'required|string|max:50',
            'loader_activation' => 'boolean',
            'loader_type' => 'nullable|string|in:forge,fabric,legacyfabric,neoForge,neoforge,quilt',
            'loader_build_version' => 'nullable|string|max:100',
            'mods_enabled' => 'boolean',
            'file_verification' => 'boolean',
            'embedded_java' => 'boolean',
            'java_version' => 'nullable|integer|min:8|max:30',
            'jvm_args_text' => 'nullable|string|max:4000',
            'game_args_text' => 'nullable|string|max:4000',
            'auto_connect' => 'boolean',
            'min_ram' => 'required|integer|min:512|max:65536',
            'max_ram' => 'required|integer|min:512|max:65536|gte:min_ram',
            'server_id' => 'nullable|integer|exists:options_server,id',
            'whitelist_activate' => 'boolean',
            'maintenance' => 'boolean',
            'maintenance_message' => 'nullable|string|max:500',
            'maintenance_bypass_roles' => 'nullable|array',
            'maintenance_bypass_roles.*' => 'string|max:100',
            'maintenance_bypass_roles_text' => 'nullable|string|max:1000',
            'maintenance_bypass_users_text' => 'nullable|string|max:2000',
        ]);

        $slug = $validated['slug'];
        $iconPath = $request->hasFile('icon')
            ? $request->file('icon')->store("instance_assets/{$slug}", 'public')
            : null;
        $bgPath = $request->hasFile('background')
            ? $request->file('background')->store("instance_assets/{$slug}", 'public')
            : null;

        $bypassRoles = $this->mergeStringList(
            $validated['maintenance_bypass_roles'] ?? [],
            $validated['maintenance_bypass_roles_text'] ?? ''
        );
        $bypassUsers = $this->parseDelimitedList($validated['maintenance_bypass_users_text'] ?? '');

        $isFirst = Instance::count() === 0;
        $makeDefault = $isFirst || $request->boolean('is_default');

        $instance = DB::transaction(function () use ($validated, $slug, $iconPath, $bgPath, $bypassRoles, $bypassUsers, $makeDefault, $request) {
            if ($makeDefault) {
                Instance::where('is_default', true)->update(['is_default' => false]);
            }

            return Instance::create([
                'slug' => $slug,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'icon_path' => $iconPath,
                'background_path' => $bgPath,
                'is_default' => $makeDefault,
                'is_enabled' => $request->boolean('is_enabled', true),
                'position' => (int) ($validated['position'] ?? 0),
                'maintenance' => $request->boolean('maintenance'),
                'maintenance_message' => $validated['maintenance_message'] ?? null,
                'maintenance_bypass_roles' => $bypassRoles,
                'maintenance_bypass_users' => $bypassUsers,
                'minecraft_version' => $validated['minecraft_version'],
                'loader_type' => $validated['loader_type'] ?? 'forge',
                'loader_build_version' => $validated['loader_build_version'] ?? null,
                'loader_forge_version' => $validated['loader_build_version'] ?? null,
                'loader_activation' => $request->boolean('loader_activation'),
                'mods_enabled' => $request->boolean('mods_enabled', true),
                'file_verification' => $request->boolean('file_verification', true),
                'embedded_java' => $request->boolean('embedded_java', true),
                'java_version' => $validated['java_version'] ?? null,
                'jvm_args' => $this->parseArgsList($validated['jvm_args_text'] ?? ''),
                'game_args' => $this->parseArgsList($validated['game_args_text'] ?? ''),
                'auto_connect' => $request->boolean('auto_connect'),
                'min_ram' => (int) $validated['min_ram'],
                'max_ram' => (int) $validated['max_ram'],
                'server_id' => $validated['server_id'] ?? null,
                'whitelist_activate' => $request->boolean('whitelist_activate'),
                'custom_data_dir' => 'instances/' . $slug,
            ]);
        });

        $instance->ensureDirectoriesExist();
        if ($instance->is_default) {
            $this->syncLegacySingletonsFromDefault($instance);
        }

        ActiveInstance::set($instance);
        LauncherCache::flush();

        return redirect()->route('admin.instances')->with('success', __('messages.flash.instance_created', ['name' => $instance->name]));
    }

    public function edit(Instance $instance)
    {
        $servers = OptionsServer::orderByDesc('is_default')->orderBy('server_name')->get();
        $availableRoles = $this->collectAvailableRoleNames();

        return view('admin.instances.edit', compact('instance', 'servers', 'availableRoles'));
    }

    public function update(Request $request, Instance $instance)
    {
        $rawSlug = trim((string) $request->input('slug', $instance->slug));
        $slugCandidate = Str::slug($rawSlug) ?: $instance->slug;
        $request->merge(['slug' => $slugCandidate]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-_]+$/',
                Rule::unique('instances', 'slug')->ignore($instance->id),
            ],
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'background' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'remove_icon' => 'boolean',
            'remove_background' => 'boolean',
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'position' => 'nullable|integer|min:0|max:9999',
            'minecraft_version' => 'required|string|max:50',
            'loader_activation' => 'boolean',
            'loader_type' => 'nullable|string|in:forge,fabric,legacyfabric,neoForge,neoforge,quilt',
            'loader_build_version' => 'nullable|string|max:100',
            'mods_enabled' => 'boolean',
            'file_verification' => 'boolean',
            'embedded_java' => 'boolean',
            'java_version' => 'nullable|integer|min:8|max:30',
            'jvm_args_text' => 'nullable|string|max:4000',
            'game_args_text' => 'nullable|string|max:4000',
            'auto_connect' => 'boolean',
            'min_ram' => 'required|integer|min:512|max:65536',
            'max_ram' => 'required|integer|min:512|max:65536|gte:min_ram',
            'server_id' => 'nullable|integer|exists:options_server,id',
            'whitelist_activate' => 'boolean',
            'maintenance' => 'boolean',
            'maintenance_message' => 'nullable|string|max:500',
            'maintenance_bypass_roles' => 'nullable|array',
            'maintenance_bypass_roles.*' => 'string|max:100',
            'maintenance_bypass_roles_text' => 'nullable|string|max:1000',
            'maintenance_bypass_users_text' => 'nullable|string|max:2000',
        ]);

        $oldSlug = $instance->slug;
        $slug = $validated['slug'];
        $iconPath = $instance->icon_path;
        $bgPath = $instance->background_path;

        if ($request->boolean('remove_icon') && $iconPath) {
            Storage::disk('public')->delete($iconPath);
            $iconPath = null;
        }
        if ($request->hasFile('icon')) {
            if ($iconPath) {
                Storage::disk('public')->delete($iconPath);
            }
            $iconPath = $request->file('icon')->store("instance_assets/{$slug}", 'public');
        }

        if ($request->boolean('remove_background') && $bgPath) {
            Storage::disk('public')->delete($bgPath);
            $bgPath = null;
        }
        if ($request->hasFile('background')) {
            if ($bgPath) {
                Storage::disk('public')->delete($bgPath);
            }
            $bgPath = $request->file('background')->store("instance_assets/{$slug}", 'public');
        }

        $bypassRoles = $this->mergeStringList(
            $validated['maintenance_bypass_roles'] ?? [],
            $validated['maintenance_bypass_roles_text'] ?? ''
        );
        $bypassUsers = $this->parseDelimitedList($validated['maintenance_bypass_users_text'] ?? '');

        $makeDefault = $request->boolean('is_default') || ($instance->is_default && Instance::count() === 1);
        $customDataDir = $instance->custom_data_dir;
        if ($customDataDir && $customDataDir !== 'data' && $slug !== $oldSlug) {
            $oldAbs = Storage::disk('public')->path('instances/' . $oldSlug);
            $newAbs = Storage::disk('public')->path('instances/' . $slug);
            if (is_dir($oldAbs) && !file_exists($newAbs)) {
                @rename($oldAbs, $newAbs);
            }
            $oldPkgScope = 'instance-' . (preg_replace('/[^a-zA-Z0-9_-]+/', '-', $oldSlug) ?: $instance->id);
            Storage::disk('local')->deleteDirectory('launcher-packages/' . $oldPkgScope);
            $customDataDir = 'instances/' . $slug;
        }

        DB::transaction(function () use ($instance, $validated, $slug, $iconPath, $bgPath, $bypassRoles, $bypassUsers, $makeDefault, $customDataDir, $request) {
            if ($makeDefault) {
                Instance::where('id', '!=', $instance->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $instance->update([
                'slug' => $slug,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'icon_path' => $iconPath,
                'background_path' => $bgPath,
                'is_default' => $makeDefault ?: $instance->is_default,
                'is_enabled' => $makeDefault ? true : $request->boolean('is_enabled', true),
                'position' => (int) ($validated['position'] ?? $instance->position),
                'maintenance' => $request->boolean('maintenance'),
                'maintenance_message' => $validated['maintenance_message'] ?? null,
                'maintenance_bypass_roles' => $bypassRoles,
                'maintenance_bypass_users' => $bypassUsers,
                'minecraft_version' => $validated['minecraft_version'],
                'loader_type' => $validated['loader_type'] ?? 'forge',
                'loader_build_version' => $validated['loader_build_version'] ?? null,
                'loader_forge_version' => $validated['loader_build_version'] ?? null,
                'loader_activation' => $request->boolean('loader_activation'),
                'mods_enabled' => $request->boolean('mods_enabled'),
                'file_verification' => $request->boolean('file_verification'),
                'embedded_java' => $request->boolean('embedded_java'),
                'java_version' => $validated['java_version'] ?? null,
                'jvm_args' => $this->parseArgsList($validated['jvm_args_text'] ?? ''),
                'game_args' => $this->parseArgsList($validated['game_args_text'] ?? ''),
                'auto_connect' => $request->boolean('auto_connect'),
                'min_ram' => (int) $validated['min_ram'],
                'max_ram' => (int) $validated['max_ram'],
                'server_id' => $validated['server_id'] ?? null,
                'whitelist_activate' => $request->boolean('whitelist_activate'),
                'custom_data_dir' => $customDataDir,
            ]);
        });

        $instance->refresh();
        $instance->ensureDirectoriesExist();
        if ($instance->is_default) {
            $this->syncLegacySingletonsFromDefault($instance);
        }

        LauncherCache::flush();

        return redirect()->route('admin.instances')->with('success', __('messages.flash.instance_updated', ['name' => $instance->name]));
    }

    public function destroy(Instance $instance)
    {
        if (Instance::count() <= 1) {
            return redirect()->route('admin.instances')->with('error', __('messages.flash.instance_cannot_delete_last'));
        }

        $name = $instance->name;
        $wasDefault = $instance->is_default;
        $wasActive = (int) session(ActiveInstance::SESSION_KEY) === (int) $instance->id;
        $pkgScope = 'instance-' . (preg_replace('/[^a-zA-Z0-9_-]+/', '-', $instance->slug) ?: $instance->id);

        if ($instance->icon_path) {
            Storage::disk('public')->delete($instance->icon_path);
        }
        if ($instance->background_path) {
            Storage::disk('public')->delete($instance->background_path);
        }
        Storage::disk('local')->deleteDirectory('launcher-packages/' . $pkgScope);

        $instance->delete();

        $replacement = Instance::orderBy('position')->orderBy('id')->first();
        if ($wasDefault && $replacement) {
            $replacement->update(['is_default' => true, 'is_enabled' => true]);
            $this->syncLegacySingletonsFromDefault($replacement);
        }

        if ($wasActive && $replacement) {
            ActiveInstance::set($replacement);
        }

        LauncherCache::flush();

        return redirect()->route('admin.instances')->with('success', __('messages.flash.instance_deleted', ['name' => $name]));
    }

    public function switchInstance(Request $request)
    {
        $validated = $request->validate([
            'instance_id' => 'required|integer|exists:instances,id',
        ]);

        $instance = Instance::findOrFail($validated['instance_id']);
        ActiveInstance::set($instance);

        return redirect()->back()->with('success', __('messages.flash.instance_switched', ['name' => $instance->name]));
    }

    public function setDefault(Instance $instance)
    {
        DB::transaction(function () use ($instance) {
            Instance::where('is_default', true)->update(['is_default' => false]);
            $instance->update(['is_default' => true, 'is_enabled' => true]);
        });

        $instance->refresh();
        $this->syncLegacySingletonsFromDefault($instance);
        LauncherCache::flush();

        return redirect()->route('admin.instances')->with('success', __('messages.flash.instance_set_default', ['name' => $instance->name]));
    }

    private function syncLegacySingletonsFromDefault(Instance $instance): void
    {
        PanelOptions::general()->update([
            'mods_enabled' => $instance->mods_enabled,
            'file_verification' => $instance->file_verification,
            'embedded_java' => $instance->embedded_java,
            'min_ram' => $instance->min_ram,
            'max_ram' => $instance->max_ram,
        ]);

        PanelOptions::loader()->update([
            'minecraft_version' => $instance->minecraft_version,
            'loader_activation' => $instance->loader_activation,
            'loader_type' => $instance->loader_type ?: 'forge',
            'loader_build_version' => $instance->loader_build_version,
            'loader_forge_version' => $instance->loader_forge_version,
        ]);
    }

    private function parseArgsList(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $lines = preg_split('/[\r\n]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $arg) => $arg !== ''));
    }

    private function parseDelimitedList(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn (string $v) => $v !== '')));
    }

    private function mergeStringList(array $checked, ?string $extraRaw): array
    {
        return array_values(array_unique(array_filter(array_merge(
            array_map('trim', $checked),
            $this->parseDelimitedList($extraRaw)
        ), fn (string $v) => $v !== '')));
    }

    private function collectAvailableRoleNames(): array
    {
        $names = PanelRole::orderByDesc('power')->pluck('name')->all();

        try {
            $api = new AzuriomApi();
            foreach ($api->getRoles() as $role) {
                if (!empty($role['name'])) {
                    $names[] = $role['name'];
                }
            }
        } catch (\Throwable) {
            // Azuriom not configured
        }

        return array_values(array_unique($names));
    }
}
