<?php

namespace App\Http\Controllers;

use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;

class AdminRpcController extends Controller
{
    public function show()
    {
        $rpcOptions = PanelOptions::rpc();
        $activeInstance = ActiveInstance::current();

        return view('admin.rpc', compact('rpcOptions', 'activeInstance'));
    }

    public function update(Request $request)
    {
        $rpcEnabled = $request->boolean('rpc_activation');

        $validated = $request->validate([
            'rpc_activation' => 'boolean',
            'rpc_id' => ($rpcEnabled ? 'required' : 'nullable') . '|string|max:100',
            'rpc_details' => 'nullable|string|max:255',
            'rpc_state' => 'nullable|string|max:255',
            'instance_rpc_details' => 'nullable|string|max:255',
            'instance_rpc_state' => 'nullable|string|max:255',
            'rpc_large_image' => 'nullable|string|max:255',
            'rpc_large_text' => 'nullable|string|max:255',
            'rpc_small_image' => 'nullable|string|max:255',
            'rpc_small_text' => 'nullable|string|max:255',
            'rpc_button1' => 'nullable|string|max:50',
            'rpc_button1_url' => 'nullable|url|max:200',
            'rpc_button2' => 'nullable|string|max:50',
            'rpc_button2_url' => 'nullable|url|max:200',
        ]);

        $instanceDetails = array_key_exists('instance_rpc_details', $validated)
            ? trim((string) ($validated['instance_rpc_details'] ?? ''))
            : null;
        $instanceState = array_key_exists('instance_rpc_state', $validated)
            ? trim((string) ($validated['instance_rpc_state'] ?? ''))
            : null;

        unset($validated['instance_rpc_details'], $validated['instance_rpc_state']);
        $validated['rpc_activation'] = $rpcEnabled;

        PanelOptions::rpc()->update($validated);

        if ($request->has('instance_rpc_details') || $request->has('instance_rpc_state')) {
            $activeInstance = ActiveInstance::current();
            $instancePayload = [];
            if ($request->has('instance_rpc_details')) {
                $instancePayload['rpc_details'] = $instanceDetails !== '' ? $instanceDetails : null;
            }
            if ($request->has('instance_rpc_state')) {
                $instancePayload['rpc_state'] = $instanceState !== '' ? $instanceState : null;
            }
            $activeInstance->update($instancePayload);
        }

        LauncherCache::flushMetadata();

        return redirect()->route('admin.rpc')->with('success', __('messages.flash.rpc_updated'));
    }
}
