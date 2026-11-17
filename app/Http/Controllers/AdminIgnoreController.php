<?php

namespace App\Http\Controllers;

use App\Models\OptionsIgnore;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Illuminate\Http\Request;

class AdminIgnoreController extends Controller
{
    public function index()
    {
        $activeInstance = ActiveInstance::current();

        $folders = OptionsIgnore::query()
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id)
                    ->orWhereNull('instance_id');
            })
            ->orderByRaw('CASE WHEN instance_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('folder_name')
            ->get();

        return view('admin.ignore', compact('folders', 'activeInstance'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ignored_folders' => 'nullable|string|max:2000',
            'scope' => 'nullable|in:instance,global',
        ]);

        $activeInstance = ActiveInstance::current();
        $instanceId = ($validated['scope'] ?? 'instance') === 'global' ? null : $activeInstance->id;

        $folders = collect(explode(',', $validated['ignored_folders'] ?? ''))
            ->map(fn ($folder) => trim($folder))
            ->filter()
            ->unique();

        foreach ($folders as $folder) {
            OptionsIgnore::firstOrCreate([
                'instance_id' => $instanceId,
                'folder_name' => $folder,
            ]);
        }

        LauncherCache::flush();

        return redirect()->route('admin.ignore')->with('success', __('messages.flash.ignore_updated'));
    }

    public function destroyFolder($id)
    {
        OptionsIgnore::findOrFail($id)->delete();
        LauncherCache::flush();

        return redirect()->route('admin.ignore')->with('success', __('messages.flash.ignore_deleted'));
    }
}
