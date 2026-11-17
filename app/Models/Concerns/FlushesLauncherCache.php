<?php

namespace App\Models\Concerns;

use App\Support\LauncherCache;

trait FlushesLauncherCache
{
    public static function bootFlushesLauncherCache(): void
    {
        static::saved(function (): void {
            LauncherCache::flush();
        });

        static::deleted(function (): void {
            LauncherCache::flush();
        });
    }
}
