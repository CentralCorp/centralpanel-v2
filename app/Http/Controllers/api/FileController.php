<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\OptionsIgnore;
use App\Models\OptionsMods;
use App\Support\InstancePackageBuilder;
use App\Support\LauncherCache;
use App\Support\LauncherResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function getFiles(Request $request): Response
    {
        $slug = $request->query('instance');
        if (is_string($slug) && $slug !== '') {
            return $this->getInstanceFiles($request, $slug);
        }

        $defaultInstance = Instance::where('is_default', true)->first()
            ?? Instance::orderBy('position')->orderBy('id')->first();

        if ($defaultInstance) {
            return $this->serveInstanceDirectory($request, $defaultInstance);
        }

        $dir = Storage::disk('public')->path('data');
        $urlPrefix = url('storage/data');
        $ignoredFolders = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();
        $optionalMods = OptionsMods::whereNull('instance_id')->where('optional', true)->pluck('file')->filter()->values()->all();

        return $this->serveManifest($request, $dir, $urlPrefix, $ignoredFolders, $optionalMods, 'legacy-data');
    }

    public function getInstanceFiles(Request $request, string $slug): Response
    {
        $instance = Instance::where('slug', $slug)->where('is_enabled', true)->first();

        if (!$instance) {
            if ($slug === 'default' && Instance::count() === 0) {
                $dir = Storage::disk('public')->path('data');
                $urlPrefix = url('storage/data');
                $ignoredFolders = OptionsIgnore::whereNull('instance_id')->pluck('folder_name')->filter()->values()->all();
                $optionalMods = OptionsMods::whereNull('instance_id')->where('optional', true)->pluck('file')->filter()->values()->all();

                return $this->serveManifest($request, $dir, $urlPrefix, $ignoredFolders, $optionalMods, 'default-fallback');
            }

            return LauncherResponse::json($request, [
                'status' => 'error',
                'message' => 'Instance not found',
            ], 404);
        }

        return $this->serveInstanceDirectory($request, $instance);
    }

    public function downloadPackage(string $scope, string $filename): BinaryFileResponse|JsonResponse
    {
        if (
            !preg_match('/^[a-zA-Z0-9_-]+$/', $scope)
            || !preg_match('/^pkg-[a-zA-Z0-9_-]+\.zip$/', $filename)
        ) {
            return response()->json(['status' => 'error', 'message' => 'Invalid package identifier'], 400, [], LauncherResponse::JSON_FLAGS);
        }

        $pkgDir = Storage::disk('local')->path('launcher-packages/' . $scope);
        $realDir = realpath($pkgDir);
        $candidate = $pkgDir . DIRECTORY_SEPARATOR . $filename;
        $realCandidate = realpath($candidate);

        if (
            $realDir === false
            || $realCandidate === false
            || !is_file($realCandidate)
            || !str_starts_with($realCandidate, rtrim($realDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            return response()->json(['status' => 'error', 'message' => 'Package not found'], 404, [], LauncherResponse::JSON_FLAGS);
        }

        return response()->file($realCandidate, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function serveInstanceDirectory(Request $request, Instance $instance): Response
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

        $optionalMods = OptionsMods::query()
            ->where('optional', true)
            ->where(function ($q) use ($instance) {
                $q->where('instance_id', $instance->id)
                    ->orWhereNull('instance_id');
            })
            ->pluck('file')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $safeScope = 'instance-' . (preg_replace('/[^a-zA-Z0-9_-]+/', '-', $instance->slug) ?: $instance->id);

        return $this->serveManifest($request, $dir, $urlPrefix, $ignoredFolders, $optionalMods, $safeScope);
    }

    private function serveManifest(
        Request $request,
        string $dir,
        string $urlPrefix,
        array $ignoredFolders,
        array $optionalMods,
        string $scope
    ): Response {
        $wantsPackages = $request->boolean('packages')
            || $request->header('X-Accept-Packages') === '1';

        if (!is_dir($dir)) {
            $empty = $wantsPackages ? ['version' => 2, 'packages' => [], 'files' => []] : [];

            return LauncherResponse::json($request, $empty);
        }

        $filesVersion = LauncherCache::filesVersion();
        $signature = $filesVersion . ':' . $urlPrefix . ':' . $this->buildDirectorySignature($dir, $ignoredFolders);

        $files = LauncherCache::rememberFiles('launcher_files', $scope . ':' . sha1($signature), now()->addMinutes(30), function () use ($dir, $urlPrefix, $ignoredFolders) {
            return $this->dirToArray($dir, $urlPrefix, '', $ignoredFolders);
        });

        if (!$wantsPackages) {
            return LauncherResponse::json($request, $files);
        }

        sort($optionalMods);
        $baseUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim(url('/'), '/');
        }
        $pkgSig = $signature . ':opt=' . implode('|', $optionalMods) . ':base=' . $baseUrl;
        $pkgStorageDir = Storage::disk('local')->path('launcher-packages/' . $scope);

        $packaged = LauncherCache::rememberFiles(
            'launcher_packages',
            $scope . ':' . sha1($pkgSig),
            now()->addMinutes(30),
            static function () use ($dir, $files, $pkgStorageDir, $scope, $baseUrl, $optionalMods): array {
                return InstancePackageBuilder::build(
                    $dir,
                    $files,
                    $pkgStorageDir,
                    static fn (string $zipName): string => $baseUrl . '/utils/packages/' . rawurlencode($scope) . '/' . rawurlencode($zipName),
                    $optionalMods
                );
            }
        );

        return LauncherResponse::json($request, $packaged);
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
