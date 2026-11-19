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
     * Build a versioned metadata launcher cache key without touching any shared registry.
     */
    public static function key(string $prefix, string $suffix = ''): string
    {
        $version = self::version();

        return $suffix !== ''
            ? "{$prefix}:v{$version}:{$suffix}"
            : "{$prefix}:v{$version}";
    }

    /**
     * Build a versioned files/packages launcher cache key isolated from metadata changes.
     */
    public static function fileKey(string $prefix, string $suffix = ''): string
    {
        $version = self::filesVersion();

        return $suffix !== ''
            ? "{$prefix}:fv{$version}:{$suffix}"
            : "{$prefix}:fv{$version}";
    }

    public static function remember(string $prefix, string $suffix, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($prefix, $suffix), $ttl, $callback);
    }

    public static function rememberFiles(string $prefix, string $suffix, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::fileKey($prefix, $suffix), $ttl, $callback);
    }

    /**
     * Flush metadata caches only (options, instances, news, optional mods) without invalidating file manifests/packages.
     */
    public static function flushMetadata(): void
    {
        Cache::forget('launcher_optional_mods');
        Cache::forget('launcher_news');
        Cache::forget('launcher_instances');

        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /**
     * Flush file manifest and ZIP package caches only without invalidating metadata caches.
     */
    public static function flushFiles(): void
    {
        Cache::forever(self::FILES_VERSION_KEY, self::filesVersion() + 1);
    }

    /**
     * Flush all launcher API caches (both metadata and files).
     */
    public static function flush(): void
    {
        Cache::forget(self::REGISTRY_KEY);

        self::flushMetadata();
        self::flushFiles();
    }
}
