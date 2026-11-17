<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PanelCache;
use App\Support\PanelVersion;
use App\Updates\UpdateManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateController extends Controller
{
    public function index(Request $request)
    {
        $currentVersion = PanelVersion::current();
        $manager = new UpdateManager(new Filesystem(), $currentVersion);
        $info = $manager->fetchUpdateInfo();
        $hasUpdate = $manager->hasUpdate($info);
        return view('admin.update', [
            'info' => $info,
            'hasUpdate' => $hasUpdate,
            'currentVersion' => $currentVersion,
        ]);
    }

    public function update(Request $request)
    {
        $currentVersion = PanelVersion::current();
        $manager = new UpdateManager(new Filesystem(), $currentVersion);
        try {
            $updated = $manager->updateIfAvailable();
            if ($updated) {
                return redirect()->back()->with('success', __('messages.flash.update_success'));
            } else {
                return redirect()->back()->with('info', __('messages.flash.update_none'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages.flash.update_error') . ' ' . $e->getMessage());
        }
    }

    public function clearCache(Request $request)
    {
        $validated = $request->validate([
            'target' => ['required', Rule::in(['all', 'application', 'bootstrap', 'views'])],
        ]);

        $results = match ($validated['target']) {
            'application' => PanelCache::clearApplication(),
            'bootstrap' => PanelCache::clearBootstrap(),
            'views' => PanelCache::clearViews(),
            default => PanelCache::clearAll(),
        };

        \App\Support\LauncherCache::flush();

        return redirect()
            ->back()
            ->with('success', __('messages.flash.cache_cleared', ['count' => count($results)]));
    }
}
