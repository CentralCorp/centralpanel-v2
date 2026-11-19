<?php

namespace App\Http\Controllers;

use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;

class AdminRpcController extends Controller
{
    public function show()
    {
        $rpcOptions = PanelOptions::rpc();

        return view('admin.rpc', compact('rpcOptions'));
    }

    public function update(Request $request)
    {
        $rpcEnabled = $request->boolean('rpc_activation');

        $validated = $request->validate([
            'rpc_activation' => 'boolean',
            'rpc_id' => ($rpcEnabled ? 'required' : 'nullable') . '|string|max:100',
            'rpc_details' => 'nullable|string|max:255',
            'rpc_state' => 'nullable|string|max:255',
            'rpc_large_image' => 'nullable|string|max:255',
            'rpc_large_text' => 'nullable|string|max:255',
            'rpc_small_image' => 'nullable|string|max:255',
            'rpc_small_text' => 'nullable|string|max:255',
            'rpc_button1' => 'nullable|string|max:50',
            'rpc_button1_url' => 'nullable|url|max:200',
            'rpc_button2' => 'nullable|string|max:50',
            'rpc_button2_url' => 'nullable|url|max:200',
        ]);

        $validated['rpc_activation'] = $rpcEnabled;

        PanelOptions::rpc()->update($validated);
        LauncherCache::flushMetadata();

        return redirect()->route('admin.rpc')->with('success', __('messages.flash.rpc_updated'));
    }
}
