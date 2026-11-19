<?php

namespace App\Http\Controllers;

use App\Support\LauncherCache;
use App\Support\PanelOptions;
use App\Support\YouTubeUrl;
use Closure;
use Illuminate\Http\Request;

class AdminUIController extends Controller
{
    public function show()
    {
        $uiOptions = PanelOptions::ui();

        return view('admin.ui', compact('uiOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'alert_activation' => 'boolean',
            'alert_scroll' => 'boolean',
            'alert_msg' => 'nullable|string|max:255',
            'video_activation' => 'boolean',
            'video_url' => [
                'nullable',
                'string',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && trim((string) $value) !== '' && !YouTubeUrl::isValid((string) $value)) {
                        $fail(__('validation.url', ['attribute' => $attribute]));
                    }
                },
            ],
            'splash' => 'nullable|string|max:255',
            'splash_author' => 'nullable|string|max:255',
            'accent_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $validated['alert_activation'] = $request->boolean('alert_activation');
        $validated['alert_scroll'] = $request->boolean('alert_scroll');
        $validated['video_activation'] = $request->boolean('video_activation');

        PanelOptions::ui()->update($validated);
        LauncherCache::flushMetadata();

        return redirect()->route('admin.ui')->with('success', __('messages.flash.ui_updated'));
    }
}
