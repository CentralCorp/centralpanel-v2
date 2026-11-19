<?php

namespace App\Http\Middleware;

use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SyncActiveInstanceDisk
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                if (Schema::hasTable('instances')) {
                    $instance = ActiveInstance::current();
                    $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

                    config([
                        'filesystems.disks.data.root' => $instance->resolvedStoragePath(),
                        'filesystems.disks.data.url' => $instance->resolvedPublicUrlPrefix($baseUrl),
                    ]);

                    Storage::forgetDisk('data');
                }
            } catch (\Throwable) {
                // Ignore if database is not yet initialized during install
            }
        }

        $response = $next($request);

        if (!$request->isMethod('GET') && $request->is('file-manager/*')) {
            LauncherCache::flushFiles();
        }

        return $response;
    }
}
