<?php

namespace App\Support;

use ZipArchive;

class InstancePackageBuilder
{
    public const DEFAULT_MAX_CHUNK_BYTES = 10 * 1024 * 1024; // 10 MB per ZIP chunk
    public const DEFAULT_MAX_CHUNK_FILES = 100;              // Max 100 files per ZIP chunk
    public const MAX_SINGLE_PACKAGEABLE_BYTES = 100 * 1024 * 1024; // >100 MB streams directly

    private const STORE_EXTENSIONS = [
        'jar',
        'zip',
        'gz',
        'tgz',
        'bz2',
        'xz',
        '7z',
        'png',
        'jpg',
        'jpeg',
        'webp',
        'gif',
        'ogg',
        'mp3',
        'mp4',
        'webm',
    ];

    /**
     * Partition scanned instance files by folder into small content-addressed ZIP packages,
     * reusing unchanged archives on disk and pruning obsolete ones.
     *
     * @param  array<int, array{path: string, size: int, hash: string, url: string}>  $scannedFiles
     * @param  callable(string): string  $packageUrlResolver
     * @param  array<int, string>  $optionalModFiles
     * @return array{version: int, packages: array<int, array<string, mixed>>, files: array<int, array<string, mixed>>}
     */
    public static function build(
        string $sourceRootDir,
        array $scannedFiles,
        string $packageStorageDir,
        callable $packageUrlResolver,
        array $optionalModFiles = [],
        int $maxChunkBytes = self::DEFAULT_MAX_CHUNK_BYTES,
        int $maxChunkFiles = self::DEFAULT_MAX_CHUNK_FILES
    ): array {
        if ($scannedFiles === [] || !class_exists(ZipArchive::class)) {
            return [
                'version' => 2,
                'packages' => [],
                'files' => array_values($scannedFiles),
            ];
        }

        usort($scannedFiles, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        $optionalLookup = [];
        foreach ($optionalModFiles as $modFile) {
            $clean = strtolower(trim(basename(str_replace('\\', '/', (string) $modFile))));
            if ($clean !== '') {
                $optionalLookup[$clean] = true;
            }
        }

        if (!is_dir($packageStorageDir)) {
            @mkdir($packageStorageDir, 0775, true);
        }

        // Group files by top-level folder (with dedicated bucket for optional mods)
        $buckets = [];
        $oversizedFiles = [];

        foreach ($scannedFiles as $file) {
            $size = (int) ($file['size'] ?? 0);
            if ($size > self::MAX_SINGLE_PACKAGEABLE_BYTES) {
                $oversizedFiles[] = $file;
                continue;
            }

            $bucket = self::resolveBucket((string) $file['path'], $optionalLookup);
            $buckets[$bucket][] = $file;
        }

        ksort($buckets);

        $packages = [];
        $taggedFiles = $oversizedFiles;
        $activeZipNames = [];

        foreach ($buckets as $bucket => $bucketFiles) {
            $chunks = self::splitIntoSmallChunks($bucketFiles, max(1, $maxChunkBytes), max(1, $maxChunkFiles));
            $folderSlug = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($bucket)) ?: 'files';
            $folderSlug = trim($folderSlug, '-') ?: 'files';

            foreach ($chunks as $idx => $chunk) {
                $partNumber = $idx + 1;
                $sigParts = array_map(
                    static fn (array $f): string => $f['path'] . ':' . $f['hash'] . ':' . $f['size'],
                    $chunk
                );
                $chunkHash = substr(sha1(implode(';', $sigParts)), 0, 12);
                $packageId = "{$folderSlug}-p{$partNumber}-{$chunkHash}";
                $zipFilename = "pkg-{$packageId}.zip";
                $zipPath = rtrim($packageStorageDir, '/\\') . DIRECTORY_SEPARATOR . $zipFilename;

                if (!is_file($zipPath) || (int) @filesize($zipPath) <= 0) {
                    if (!self::writeChunkArchive($sourceRootDir, $chunk, $zipPath)) {
                        // Fallback cleanly: leave files in this chunk unpackaged
                        foreach ($chunk as $f) {
                            $taggedFiles[] = $f;
                        }
                        continue;
                    }
                }

                $zipSize = @filesize($zipPath);
                $zipSha1 = @hash_file('sha1', $zipPath);
                if ($zipSize === false || $zipSha1 === false) {
                    foreach ($chunk as $f) {
                        $taggedFiles[] = $f;
                    }
                    continue;
                }

                $activeZipNames[$zipFilename] = true;

                $packages[] = [
                    'id' => $packageId,
                    'folder' => $bucket,
                    'part' => $partNumber,
                    'size' => (int) $zipSize,
                    'hash' => $zipSha1,
                    'url' => $packageUrlResolver($zipFilename),
                    'files_count' => count($chunk),
                ];

                foreach ($chunk as $f) {
                    $f['package'] = $packageId;
                    $taggedFiles[] = $f;
                }
            }
        }

        self::pruneStaleArchives($packageStorageDir, $activeZipNames);

        usort($taggedFiles, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return [
            'version' => 2,
            'packages' => $packages,
            'files' => $taggedFiles,
        ];
    }

    /**
     * @param  array<string, bool>  $optionalLookup
     */
    private static function resolveBucket(string $relativePath, array $optionalLookup): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_contains($normalized, '/')) {
            return 'root';
        }

        [$topFolder, $rest] = explode('/', $normalized, 2);
        if (strtolower($topFolder) === 'mods' && !str_contains($rest, '/')) {
            if (isset($optionalLookup[strtolower($rest)])) {
                return 'mods-optional';
            }
        }

        return $topFolder !== '' ? $topFolder : 'root';
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function splitIntoSmallChunks(array $files, int $maxBytes, int $maxFiles): array
    {
        $chunks = [];
        $current = [];
        $currentBytes = 0;

        foreach ($files as $file) {
            $size = max(0, (int) ($file['size'] ?? 0));
            $exceedsCount = count($current) >= $maxFiles;
            $exceedsBytes = $current !== [] && ($currentBytes + $size) > $maxBytes;

            if ($exceedsCount || $exceedsBytes) {
                $chunks[] = $current;
                $current = [];
                $currentBytes = 0;
            }

            $current[] = $file;
            $currentBytes += $size;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     */
    private static function writeChunkArchive(string $sourceRootDir, array $chunk, string $zipPath): bool
    {
        $tmpPath = $zipPath . '.tmp.' . bin2hex(random_bytes(4));
        $zip = new ZipArchive();

        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);

            return false;
        }

        $addedCount = 0;
        foreach ($chunk as $entry) {
            $rel = ltrim(str_replace('\\', '/', (string) $entry['path']), '/');
            $abs = rtrim($sourceRootDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) {
                continue;
            }

            if ($zip->addFile($abs, $rel)) {
                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                $method = in_array($ext, self::STORE_EXTENSIONS, true)
                    ? ZipArchive::CM_STORE
                    : ZipArchive::CM_DEFLATE;
                $zip->setCompressionName($rel, $method);
                $addedCount++;
            }
        }

        $closed = $zip->close();
        if (!$closed || $addedCount === 0 || !is_file($tmpPath)) {
            @unlink($tmpPath);

            return false;
        }

        if (!@rename($tmpPath, $zipPath)) {
            @unlink($tmpPath);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $activeZipNames
     */
    private static function pruneStaleArchives(string $packageStorageDir, array $activeZipNames): void
    {
        if (!is_dir($packageStorageDir)) {
            return;
        }

        $entries = @scandir($packageStorageDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $name) {
            if (!str_starts_with($name, 'pkg-') || !str_ends_with($name, '.zip')) {
                continue;
            }
            if (!isset($activeZipNames[$name])) {
                @unlink(rtrim($packageStorageDir, '/\\') . DIRECTORY_SEPARATOR . $name);
            }
        }
    }
}
