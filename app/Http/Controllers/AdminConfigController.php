<?php

namespace App\Http\Controllers;

use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AdminConfigController extends Controller
{
    public function show()
    {
        $options = PanelOptions::general();

        return view('admin.config', compact('options'));
    }

    public function update(Request $request)
    {
        $options = PanelOptions::general();
        $authMode = $request->input('auth_mode', $options->auth_mode ?: 'azuriom');
        $requiresAzuriom = in_array($authMode, ['azuriom', 'both'], true);

        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'auth_mode' => 'nullable|in:azuriom,microsoft,both',
            'microsoft_client_id' => 'nullable|string|max:255',
            'news_source' => 'nullable|in:panel,azuriom,both',
            'azuriom_url' => ($requiresAzuriom ? 'required' : 'nullable') . '|url|max:255',
            'azuriom_api_key' => ($requiresAzuriom ? 'required' : 'nullable') . '|string|max:255',
        ]);

        $options->fill([
            'auth_mode' => $validated['auth_mode'] ?? $authMode,
            'microsoft_client_id' => !empty($validated['microsoft_client_id']) ? trim($validated['microsoft_client_id']) : null,
            'news_source' => $validated['news_source'] ?? ($options->news_source ?: 'azuriom'),
            'azuriom_url' => $validated['azuriom_url'] ?? $options->azuriom_url,
            'azuriom_api_key' => $validated['azuriom_api_key'] ?? $options->azuriom_api_key,
        ])->save();

        if ($validated['app_name'] !== config('app.name')) {
            $this->updateEnvValue('APP_NAME', '"' . str_replace('"', '\"', $validated['app_name']) . '"');
            Artisan::call('config:clear');
        }

        LauncherCache::flushMetadata();

        return redirect()->route('admin.config')->with('success', __('messages.flash.config_updated'));
    }

    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return;
        }

        $envContent = File::get($envPath);
        $line = "{$key}={$value}";

        $envContent = preg_match("/^{$key}=.*/m", $envContent)
            ? preg_replace("/^{$key}=.*/m", $line, $envContent)
            : rtrim($envContent) . PHP_EOL . $line . PHP_EOL;

        File::put($envPath, $envContent);
    }
}
