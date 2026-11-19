<?php

namespace App\Http\Controllers;

use App\Models\PanelRole;
use App\Request\AzuriomApi;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;

class AdminSecurityController extends Controller
{
    public function show()
    {
        $securityOptions = PanelOptions::security();
        $activeInstance = ActiveInstance::current();
        $availableRoles = $this->collectAvailableRoleNames();

        return view('admin.security', compact('securityOptions', 'activeInstance', 'availableRoles'));
    }

    public function update(Request $request)
    {
        $scope = $request->input('scope', 'global');

        if ($scope === 'instance') {
            $validated = $request->validate([
                'instance_maintenance' => 'boolean',
                'instance_maintenance_message' => 'nullable|string|max:500',
                'instance_bypass_roles' => 'nullable|array',
                'instance_bypass_roles.*' => 'string|max:100',
                'instance_bypass_roles_text' => 'nullable|string|max:1000',
                'instance_bypass_users_text' => 'nullable|string|max:2000',
            ]);

            $activeInstance = ActiveInstance::current();
            $bypassRoles = $this->mergeList(
                $validated['instance_bypass_roles'] ?? [],
                $validated['instance_bypass_roles_text'] ?? ''
            );
            $bypassUsers = $this->parseDelimited($validated['instance_bypass_users_text'] ?? '');

            $activeInstance->update([
                'maintenance' => $request->boolean('instance_maintenance'),
                'maintenance_message' => $validated['instance_maintenance_message'] ?? null,
                'maintenance_bypass_roles' => $bypassRoles,
                'maintenance_bypass_users' => $bypassUsers,
            ]);

            LauncherCache::flushMetadata();

            return redirect()->route('admin.security')->with('success', __('messages.flash.security_updated'));
        }

        $validated = $request->validate([
            'maintenance' => 'boolean',
            'maintenance_message' => 'required|string|max:500',
            'maintenance_bypass_roles' => 'nullable|array',
            'maintenance_bypass_roles.*' => 'string|max:100',
            'maintenance_bypass_roles_text' => 'nullable|string|max:1000',
            'maintenance_bypass_users_text' => 'nullable|string|max:2000',
        ]);

        $security = PanelOptions::security();
        $updatePayload = [
            'maintenance' => $request->boolean('maintenance'),
            'maintenance_message' => $validated['maintenance_message'],
        ];

        if ($request->hasAny(['maintenance_bypass_roles', 'maintenance_bypass_roles_text', 'bypass_submitted'])) {
            $updatePayload['maintenance_bypass_roles'] = $this->mergeList(
                $validated['maintenance_bypass_roles'] ?? [],
                $validated['maintenance_bypass_roles_text'] ?? ''
            );
        }

        if ($request->hasAny(['maintenance_bypass_users_text', 'bypass_submitted'])) {
            $updatePayload['maintenance_bypass_users'] = $this->parseDelimited($validated['maintenance_bypass_users_text'] ?? '');
        }

        $security->update($updatePayload);
        LauncherCache::flushMetadata();

        return redirect()->route('admin.security')->with('success', __('messages.flash.security_updated'));
    }

    private function parseDelimited(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn (string $v) => $v !== '')));
    }

    private function mergeList(array $checked, ?string $extraRaw): array
    {
        return array_values(array_unique(array_filter(array_merge(
            array_map('trim', $checked),
            $this->parseDelimited($extraRaw)
        ), fn (string $v) => $v !== '')));
    }

    private function collectAvailableRoleNames(): array
    {
        PanelOptions::ensureRoles();
        $names = PanelRole::orderByDesc('power')->pluck('name')->all();

        try {
            $api = new AzuriomApi();
            foreach ($api->getRoles() as $role) {
                if (!empty($role['name'])) {
                    $names[] = $role['name'];
                }
            }
        } catch (\Throwable) {
            // Azuriom API optional
        }

        return array_values(array_unique($names));
    }
}
