<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\OptionsIgnore;
use App\Support\LauncherCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileController extends Controller
{
    public function getFiles(Request $request): JsonResponse
    {
        $slug = $request->query('instance');
        if (is_string($slug) && $slug !== '') {
            return $this->getInstanceFiles($slug);
        }

        $defaultInstance = Instance::where('is_default', true)->first()
            ?? Instance::orderBy('position')->orderBy('id')->first();

        if ($defaultInstance) {
            return $this->serveInstanceDirectory($defaultInstance);
        }

        $dir = \Illuminate\Support\Facades\Storage::disk('public')->path('data');
        $urlPrefix = url('storage/data');
        $ignoredFolders = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();

        return $this->serveManifest($dir, $urlPrefix, $ignoredFolders, 'legacy_data');
    }

    public function getInstanceFiles(string $slug): JsonResponse
    {
        $instance = Instance::where('slug', $slug)->where('is_enabled', true)->first();

        if (!$instance) {
            if ($slug === 'default' && Instance::count() === 0) {
                $dir = \Illuminate\Support\Facades\Storage::disk('public')->path('data');
                $urlPrefix = url('storage/data');
                $ignoredFolders = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();

                return $this->serveManifest($dir, $urlPrefix, $ignoredFolders, 'default_fallback');
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Instance not found',
            ], 404, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->serveInstanceDirectory($instance);
    }

    private function serveInstanceDirectory(Instance $instance): JsonResponse
    {
        $dir = $instance->resolvedStoragePath();
        $urlPrefix = $instance->resolvedPublicUrlPrefix();

        $ignoredFolders = OptionsIgnore::query()
            ->where(function ($q) use ($instance) {
                $q->where('instance_id', $instance->id)
                    ->orWhereNull('instance_id');
            })
            ->pluck('folder_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->serveManifest($dir, $urlPrefix, $ignoredFolders, 'instance:' . $instance->slug);
    }

    private function serveManifest(string $dir, string $urlPrefix, array $ignoredFolders, string $scope): JsonResponse
    {
        if (!is_dir($dir)) {
            return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $filesVersion = LauncherCache::filesVersion();
        $signature = $filesVersion . ':' . $urlPrefix . ':' . $this->buildDirectorySignature($dir, $ignoredFolders);

        $files = LauncherCache::remember('launcher_files', $scope . ':' . sha1($signature), now()->addMinutes(30), function () use ($dir, $urlPrefix, $ignoredFolders) {
            return $this->dirToArray($dir, $urlPrefix, '', $ignoredFolders);
        });

        return response()->json($files, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function buildDirectorySignature(string $dir, array $ignoredFolders): string
    {
        $parts = [implode('|', $ignoredFolders)];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($this->isIgnored($file->getPathname(), $dir, $ignoredFolders)) {
                continue;
            }

            if ($file->isFile()) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $parts[] = $relativePath . ':' . $file->getMTime() . ':' . $file->getSize();
            }
        }

        sort($parts);

        return implode(';', $parts);
    }

    private function dirToArray(string $dir, string $urlPrefix, string $basePath = '', array $ignoredFolders = []): array
    {
        $files = [];
        $items = scandir($dir) ?: [];

        foreach ($items as $value) {
            if (in_array($value, ['.', '..'], true)) {
                continue;
            }

            $relativePath = ltrim($basePath . '/' . $value, '/');
            if ($this->isRelativeIgnored($relativePath, $ignoredFolders)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $value;

            if (is_dir($path)) {
                $files = array_merge($files, $this->dirToArray($path, $urlPrefix, $relativePath, $ignoredFolders));
                continue;
            }

            $encodedRel = implode('/', array_map('rawurlencode', explode('/', $relativePath)));

            $files[] = [
                'path' => $relativePath,
                'size' => filesize($path),
                'hash' => hash_file('sha1', $path),
                'url' => rtrim($urlPrefix, '/') . '/' . $encodedRel,
            ];
        }

        return $files;
    }

    private function isIgnored(string $path, string $root, array $ignoredFolders): bool
    {
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));

        return $this->isRelativeIgnored($relative, $ignoredFolders);
    }

    private function isRelativeIgnored(string $relative, array $ignoredFolders): bool
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        $basename = basename($relative);

        foreach ($ignoredFolders as $ignoredFolder) {
            $ignoredFolder = trim(str_replace('\\', '/', $ignoredFolder), '/');
            if ($ignoredFolder === '') {
                continue;
            }

            if (
                $relative === $ignoredFolder
                || str_starts_with($relative, $ignoredFolder . '/')
                || $basename === $ignoredFolder
            ) {
                return true;
            }
        }

        return false;
    }
}
