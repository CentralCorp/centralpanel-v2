<?php

namespace App\Support;

use Closure;
use DateTimeInterface;
use DateInterval;
use Illuminate\Support\Facades\Cache;

final class LauncherCache
{
    private const VERSION_KEY = 'launcher_cache_version';
    private const FILES_VERSION_KEY = 'launcher_files_version';
    private const REGISTRY_KEY = 'launcher_cache_registry';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function filesVersion(): int
    {
        return (int) Cache::get(self::FILES_VERSION_KEY, 1);
    }

    /**
     * Build a versioned launcher cache key and register it so flush() can evict it explicitly.
     */
    public static function key(string $prefix, string $suffix = ''): string
    {
        $version = self::version();
        $key = $suffix !== ''
            ? "{$prefix}:v{$version}:{$suffix}"
            : "{$prefix}:v{$version}";

        self::registerKey($key);

        return $key;
    }

    public static function remember(string $prefix, string $suffix, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        $key = self::key($prefix, $suffix);

        return Cache::remember($key, $ttl, $callback);
    }

    public static function registerKey(string $key): void
    {
        $registry = Cache::get(self::REGISTRY_KEY, []);
        if (!is_array($registry)) {
            $registry = [];
        }

        if (!in_array($key, $registry, true)) {
            $registry[] = $key;
            // Cap registry size to prevent unbounded growth
            if (count($registry) > 500) {
                $registry = array_slice($registry, -500);
            }
            Cache::forever(self::REGISTRY_KEY, $registry);
        }
    }

    /**
     * Flush all launcher API caches (options, instances, optional mods, files, news).
     */
    public static function flush(): void
    {
        $registry = Cache::get(self::REGISTRY_KEY, []);
        if (is_array($registry)) {
            foreach ($registry as $key) {
                Cache::forget($key);
            }
        }

        // Also forget legacy unversioned keys
        Cache::forget('launcher_optional_mods');
        Cache::forget('launcher_news');
        Cache::forget('launcher_instances');
        Cache::forget(self::REGISTRY_KEY);

        Cache::forever(self::VERSION_KEY, self::version() + 1);
        Cache::forever(self::FILES_VERSION_KEY, self::filesVersion() + 1);
    }
}
