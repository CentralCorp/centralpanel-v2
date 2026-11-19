<?php

namespace App\Http\Controllers;

use App\Models\OptionsServer;
use App\Request\AzuriomApi;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminServerController extends Controller
{
    private ?AzuriomApi $azuriomApi = null;

    public function __construct()
    {
        try {
            $this->azuriomApi = new AzuriomApi();
        } catch (\RuntimeException) {
            $this->azuriomApi = null;
        }
    }

    public function show()
    {
        $options = PanelOptions::general();
        $servers = OptionsServer::query()->orderByDesc('is_default')->orderBy('server_name')->get();
        $hasAzuriomApi = $this->azuriomApi !== null;

        $azuriomUrl = $options->azuriom_url;
        $defaultServers = [];
        $serversArray = $servers->map(function (OptionsServer $server) use (&$defaultServers, $azuriomUrl) {
            $lookupKey = $server->server_id ?? $server->id;
            $defaultServers[$lookupKey] = (bool) $server->is_default;

            return [
                'pk' => $server->id,
                'id' => $lookupKey,
                'server_id' => $server->server_id,
                'is_manual' => $server->server_id === null || $server->server_id < 0,
                'name' => $server->server_name,
                'address' => $server->server_ip,
                'port' => $server->server_port,
                'type' => $server->type ?? 'minecraft',
                'icon' => $server->icon,
                'icon_local' => $server->icon_local,
                'icon_url' => $server->iconUrlFor(null, $azuriomUrl, true),
            ];
        })->toArray();

        return view('admin.server', [
            'servers' => $serversArray,
            'options' => $options,
            'error' => null,
            'defaultServers' => $defaultServers,
            'hasAzuriomApi' => $hasAzuriomApi,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip' => 'required|string|max:255',
            'server_port' => 'required|string|max:10',
            'type' => 'nullable|string|max:50',
            'is_default' => 'boolean',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $iconLocal = $request->hasFile('icon')
            ? $request->file('icon')->store('server_icons', 'public')
            : null;

        $hasDefault = OptionsServer::where('is_default', true)->exists();
        $makeDefault = !$hasDefault || $request->boolean('is_default');

        $minServerId = (int) OptionsServer::min('server_id');
        $nextLocalServerId = min(0, $minServerId) - 1;

        $server = DB::transaction(function () use ($validated, $iconLocal, $makeDefault, $nextLocalServerId) {
            if ($makeDefault) {
                OptionsServer::where('is_default', true)->update(['is_default' => false]);
            }

            return OptionsServer::create([
                'server_id' => $nextLocalServerId,
                'server_name' => trim($validated['server_name']),
                'server_ip' => trim($validated['server_ip']),
                'server_port' => trim($validated['server_port']),
                'type' => ($validated['type'] ?? null) ?: 'minecraft',
                'icon_local' => $iconLocal,
                'is_default' => $makeDefault,
            ]);
        });

        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_created', ['name' => $server->server_name]));
    }

    public function updateServer(Request $request, int $id)
    {
        $server = $this->findServer($id);
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip' => 'required|string|max:255',
            'server_port' => 'required|string|max:10',
            'type' => 'nullable|string|max:50',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($server->icon_local) {
                Storage::disk('public')->delete($server->icon_local);
            }
            $server->icon_local = $request->file('icon')->store('server_icons', 'public');
        }

        $server->fill([
            'server_name' => trim($validated['server_name']),
            'server_ip' => trim($validated['server_ip']),
            'server_port' => trim($validated['server_port']),
            'type' => ($validated['type'] ?? null) ?: ($server->type ?: 'minecraft'),
        ])->save();

        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_updated'));
    }

    public function destroy(int $id)
    {
        $server = $this->findServer($id);
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        $name = $server->server_name;
        $wasDefault = $server->is_default;

        if ($server->icon_local) {
            Storage::disk('public')->delete($server->icon_local);
        }

        $server->delete();

        if ($wasDefault) {
            $replacement = OptionsServer::orderBy('id')->first();
            $replacement?->update(['is_default' => true]);
        }

        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_deleted', ['name' => $name]));
    }

    public function sync()
    {
        $options = PanelOptions::general();

        if (!$options->azuriom_url) {
            return redirect()->route('admin.server')->with('error', __('messages.server.config_error'));
        }

        try {
            $api = $this->azuriomApi ?: new AzuriomApi();
            $serversResponse = $api->getServers();
            if (!$serversResponse->successful()) {
                throw new \RuntimeException('Impossible de contacter l\'API Azuriom');
            }

            $servers = $serversResponse->json();
            $syncedCount = 0;
            $hasDefaultServer = OptionsServer::where('is_default', true)->exists();

            DB::transaction(function () use ($servers, &$syncedCount, &$hasDefaultServer) {
                foreach ($servers as $server) {
                    $iconPath = $this->normalizeIconPath($server['icon'] ?? null);
                    $serverModel = OptionsServer::updateOrCreate(
                        ['server_id' => $server['id']],
                        [
                            'server_name' => $server['name'],
                            'server_ip' => $server['address'],
                            'server_port' => (string) $server['port'],
                            'icon' => $iconPath,
                            'type' => $server['type'] ?? 'minecraft',
                        ]
                    );

                    if (!$hasDefaultServer) {
                        $serverModel->forceFill(['is_default' => true])->save();
                        $hasDefaultServer = true;
                    }

                    $syncedCount++;
                }
            });

            LauncherCache::flushMetadata();

            return redirect()->route('admin.server')->with('success', __('messages.flash.server_sync_success', ['count' => $syncedCount]));
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_sync_error') . ' ' . $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'server_ip' => 'required|string|max:255',
            'server_port' => 'required|string|max:10',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $serverOptions = OptionsServer::firstOrFail();

        if ($request->hasFile('icon')) {
            if ($serverOptions->icon_local) {
                Storage::disk('public')->delete($serverOptions->icon_local);
            }
            $serverOptions->icon_local = $request->file('icon')->store('server_icons', 'public');
        }

        $serverOptions->fill(collect($validated)->except('icon')->all())->save();
        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_updated'));
    }

    public function setDefaultServer(Request $request)
    {
        $validated = $request->validate([
            'server_id' => 'required|integer',
        ]);

        $target = $this->findServer((int) $validated['server_id']);
        if (!$target) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        DB::transaction(function () use ($target) {
            OptionsServer::where('is_default', true)->update(['is_default' => false]);
            $target->forceFill(['is_default' => true])->save();
        });

        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_set_default', ['name' => $target->server_name]));
    }

    public function updateIcon(Request $request, $serverId)
    {
        $request->validate([
            'icon' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $server = $this->findServer((int) $serverId);
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        if ($server->icon_local) {
            Storage::disk('public')->delete($server->icon_local);
        }

        $server->icon_local = $request->file('icon')->store('server_icons', 'public');
        $server->save();
        LauncherCache::flushMetadata();

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_updated', ['name' => $server->server_name]));
    }

    public function deleteIcon($serverId)
    {
        $server = $this->findServer((int) $serverId);
        if (!$server) {
            return redirect()->route('admin.server')->with('error', __('messages.flash.server_not_found'));
        }

        if ($server->icon_local) {
            Storage::disk('public')->delete($server->icon_local);
            $server->forceFill(['icon_local' => null])->save();
            LauncherCache::flushMetadata();
        }

        return redirect()->route('admin.server')->with('success', __('messages.flash.server_icon_deleted', ['name' => $server->server_name]));
    }

    private function findServer(int $identifier): ?OptionsServer
    {
        return OptionsServer::where('server_id', $identifier)->first()
            ?? OptionsServer::find($identifier);
    }

    private function normalizeIconPath(?string $iconPath): ?string
    {
        if (!$iconPath) {
            return null;
        }

        $iconPath = ltrim($iconPath, '/');

        return str_starts_with($iconPath, 'storage/') ? substr($iconPath, 8) : $iconPath;
    }
}
