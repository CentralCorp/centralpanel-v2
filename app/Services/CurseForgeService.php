<?php

namespace App\Services;

use App\Exceptions\CurseForgeException;
use App\Models\CurseForgeMod;
use App\Models\CurseForgeModpack;
use App\Models\Instance;
use App\Models\OptionsGeneral;
use App\Models\OptionsLoader;
use App\Models\OptionsMods;
use App\Support\LauncherCache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class CurseForgeService
{
    public const API_BASE_URL = 'https://api.curseforge.com/v1';
    public const GAME_ID_MINECRAFT = 432;
    public const CLASS_ID_MODS = 6;
    public const CLASS_ID_MODPACKS = 4471;
    public const SORT_POPULARITY = 2;

    /**
     * Official CurseForge modLoaderType mapping.
     *
     * @var array<string, int>
     */
    public const LOADER_MAP = [
        'forge' => 1,
        'fabric' => 4,
        'quilt' => 5,
        'neoforge' => 6,
    ];

    /**
     * Expected gameVersions tag names for each supported loader.
     *
     * @var array<string, string>
     */
    public const LOADER_TAGS = [
        'forge' => 'Forge',
        'fabric' => 'Fabric',
        'quilt' => 'Quilt',
        'neoforge' => 'NeoForge',
    ];

    /**
     * Resolve the configured CurseForge API key (DB first, then config/env fallback).
     */
    public function getApiKey(): ?string
    {
        try {
            if (Schema::hasTable('options_general') && Schema::hasColumn('options_general', 'curseforge_api_key')) {
                $dbKey = trim((string) (OptionsGeneral::query()->value('curseforge_api_key') ?? ''));
                if ($dbKey !== '') {
                    return $dbKey;
                }
            }
        } catch (\Throwable) {
            // Fallback to config/env when DB is unavailable
        }

        $configKey = trim((string) (config('services.curseforge.api_key') ?: env('CURSEFORGE_API_KEY', '')));

        return $configKey !== '' ? $configKey : null;
    }

    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== null;
    }

    public function saveApiKey(?string $apiKey): void
    {
        $clean = trim((string) $apiKey);
        $options = OptionsGeneral::query()->first();

        if (!$options) {
            $options = new OptionsGeneral();
        }

        $options->curseforge_api_key = $clean !== '' ? $clean : null;
        $options->save();
    }

    public function normalizeLoader(?string $loader): ?string
    {
        $normalized = strtolower(trim((string) $loader));

        return array_key_exists($normalized, self::LOADER_MAP) ? $normalized : null;
    }

    public function getModLoaderType(?string $loader): ?int
    {
        $normalized = $this->normalizeLoader($loader);

        return $normalized !== null ? self::LOADER_MAP[$normalized] : null;
    }

    public function isLoaderSupported(?string $loader): bool
    {
        return $this->getModLoaderType($loader) !== null;
    }

    /**
     * Resolve the active loader for an instance (returns null if loader is disabled or unsupported).
     */
    public function resolveInstanceLoader(Instance $instance): ?string
    {
        $rawLoader = strtolower(trim((string) ($instance->loader_type ?? '')));
        if ($rawLoader === '' && $instance->is_default) {
            try {
                if (Schema::hasTable('options_loader') && Schema::hasColumn('options_loader', 'loader_type')) {
                    $rawLoader = strtolower(trim((string) (OptionsLoader::query()->value('loader_type') ?? '')));
                }
            } catch (\Throwable) {
                // Ignore fallback errors
            }
        }

        if ($rawLoader === '' || $rawLoader === 'vanilla') {
            return null;
        }

        return $this->normalizeLoader($rawLoader);
    }

    public function resolveInstanceMcVersion(Instance $instance): ?string
    {
        $version = trim((string) ($instance->minecraft_version ?? ''));
        if ($version === '' && $instance->is_default) {
            try {
                if (Schema::hasTable('options_loader') && Schema::hasColumn('options_loader', 'minecraft_version')) {
                    $version = trim((string) (OptionsLoader::query()->value('minecraft_version') ?? ''));
                }
            } catch (\Throwable) {
                // Ignore fallback errors
            }
        }

        return $version !== '' ? $version : null;
    }

    /**
     * Search CurseForge Modpacks (classId = 4471), sorted by popularity by default.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function searchModpacks(
        string $query = '',
        ?string $mcVersion = null,
        ?string $loader = null,
        int $page = 0,
        int $pageSize = 20
    ): array {
        return $this->searchCatalog(self::CLASS_ID_MODPACKS, $query, $mcVersion, $loader, $page, $pageSize);
    }

    /**
     * Search CurseForge Mods (classId = 6), trending/popular by default when query is empty.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function searchMods(
        string $query = '',
        ?string $mcVersion = null,
        ?string $loader = null,
        int $page = 0,
        int $pageSize = 20
    ): array {
        return $this->searchCatalog(self::CLASS_ID_MODS, $query, $mcVersion, $loader, $page, $pageSize);
    }

    /**
     * Internal catalog search shared by mods and modpacks.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    private function searchCatalog(
        int $classId,
        string $query,
        ?string $mcVersion,
        ?string $loader,
        int $page,
        int $pageSize
    ): array {
        $page = max(0, $page);
        $pageSize = max(1, min(50, $pageSize));
        $normalizedLoader = $this->normalizeLoader($loader);
        $loaderTypeId = $normalizedLoader !== null ? self::LOADER_MAP[$normalizedLoader] : null;
        $cleanVersion = trim((string) $mcVersion);

        $params = [
            'gameId' => self::GAME_ID_MINECRAFT,
            'classId' => $classId,
            'sortField' => self::SORT_POPULARITY,
            'sortOrder' => 'desc',
            'index' => $page * $pageSize,
            'pageSize' => $pageSize,
        ];

        $cleanQuery = trim($query);
        if ($cleanQuery !== '') {
            $params['searchFilter'] = $cleanQuery;
        }
        if ($cleanVersion !== '') {
            $params['gameVersion'] = $cleanVersion;
        }
        if ($loaderTypeId !== null) {
            $params['modLoaderType'] = $loaderTypeId;
        }

        $response = $this->client()->get(self::API_BASE_URL . '/mods/search', $params);
        $payload = $this->decodeResponse($response, 'Impossible de rechercher sur CurseForge.');

        $rawItems = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $items = [];

        foreach ($rawItems as $rawMod) {
            if (!is_array($rawMod)) {
                continue;
            }
            $items[] = $this->formatCatalogItem($rawMod, $cleanVersion !== '' ? $cleanVersion : null, $normalizedLoader);
        }

        $pagination = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];

        return [
            'items' => $items,
            'pagination' => [
                'index' => (int) ($pagination['index'] ?? ($page * $pageSize)),
                'page_size' => (int) ($pagination['pageSize'] ?? $pageSize),
                'result_count' => (int) ($pagination['resultCount'] ?? count($items)),
                'total_count' => (int) ($pagination['totalCount'] ?? count($items)),
            ],
        ];
    }

    /**
     * Format a CurseForge mod/modpack entry with strict compatibility calculation.
     *
     * @param  array<string, mixed>  $rawMod
     * @return array<string, mixed>
     */
    public function formatCatalogItem(array $rawMod, ?string $mcVersion, ?string $loader): array
    {
        $compatibleFile = $this->findCompatibleFile($rawMod, $mcVersion, $loader);
        $authors = is_array($rawMod['authors'] ?? null) ? $rawMod['authors'] : [];
        $primaryAuthor = isset($authors[0]['name']) ? (string) $authors[0]['name'] : 'Inconnu';
        $logo = is_array($rawMod['logo'] ?? null) ? $rawMod['logo'] : [];
        $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');

        return [
            'id' => (int) ($rawMod['id'] ?? 0),
            'name' => (string) ($rawMod['name'] ?? ''),
            'slug' => (string) ($rawMod['slug'] ?? ''),
            'summary' => (string) ($rawMod['summary'] ?? ''),
            'author' => $primaryAuthor,
            'downloads' => (int) ($rawMod['downloadCount'] ?? 0),
            'logo' => $logoUrl !== '' ? $logoUrl : null,
            'website_url' => isset($rawMod['links']['websiteUrl']) ? (string) $rawMod['links']['websiteUrl'] : null,
            'date_modified' => isset($rawMod['dateModified']) ? (string) $rawMod['dateModified'] : null,
            'is_compatible' => $compatibleFile !== null,
            'compatible_file' => $compatibleFile !== null ? $this->formatFileSummary($compatibleFile) : null,
        ];
    }

    /**
     * Strictly verify that a CurseForge file matches BOTH the exact Minecraft version AND active loader.
     *
     * @param  array<string, mixed>  $file
     */
    public function isFileCompatible(array $file, ?string $mcVersion, ?string $loader): bool
    {
        $cleanVersion = trim((string) $mcVersion);
        $normalizedLoader = $this->normalizeLoader($loader);

        if ($cleanVersion === '' || $normalizedLoader === null) {
            return false;
        }

        $gameVersions = $file['gameVersions'] ?? [];
        if (!is_array($gameVersions) || $gameVersions === []) {
            return false;
        }

        $hasExactMcVersion = false;
        $presentLoaders = [];

        foreach ($gameVersions as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $trimmed = trim($tag);
            if ($trimmed === $cleanVersion) {
                $hasExactMcVersion = true;
            }
            $lower = strtolower($trimmed);
            if (array_key_exists($lower, self::LOADER_MAP)) {
                $presentLoaders[$lower] = true;
            }
        }

        if (!$hasExactMcVersion) {
            return false;
        }

        // Explicit matching loader tag
        if (isset($presentLoaders[$normalizedLoader])) {
            return true;
        }

        // Another loader tag is explicitly present without the requested loader
        if ($presentLoaders !== []) {
            return false;
        }

        // No loader tag present in gameVersions: check modLoader field and historical Forge fallback
        $modLoaderId = isset($file['modLoader']) && is_numeric($file['modLoader'])
            ? (int) $file['modLoader']
            : null;

        if ($normalizedLoader === 'forge') {
            if ($modLoaderId !== null && $modLoaderId !== 0 && $modLoaderId !== self::LOADER_MAP['forge']) {
                return false;
            }

            $fileName = (string) ($file['fileName'] ?? $file['displayName'] ?? '');

            return !$this->filenameMentionsNonForgeLoader($fileName);
        }

        return $modLoaderId === self::LOADER_MAP[$normalizedLoader];
    }

    /**
     * Find the latest strictly compatible file from a CurseForge mod payload (`latestFiles` then `latestFilesIndexes`).
     *
     * @param  array<string, mixed>  $rawMod
     * @return array<string, mixed>|null
     */
    public function findCompatibleFile(array $rawMod, ?string $mcVersion, ?string $loader): ?array
    {
        $cleanVersion = trim((string) $mcVersion);
        $normalizedLoader = $this->normalizeLoader($loader);
        if ($cleanVersion === '' || $normalizedLoader === null) {
            return null;
        }

        $latestFiles = is_array($rawMod['latestFiles'] ?? null) ? $rawMod['latestFiles'] : [];
        $compatible = [];

        foreach ($latestFiles as $file) {
            if (is_array($file) && $this->isFileCompatible($file, $cleanVersion, $normalizedLoader)) {
                $compatible[] = $file;
            }
        }

        if ($compatible !== []) {
            usort($compatible, static function (array $a, array $b): int {
                $dateA = strtotime((string) ($a['fileDate'] ?? '')) ?: 0;
                $dateB = strtotime((string) ($b['fileDate'] ?? '')) ?: 0;
                if ($dateA !== $dateB) {
                    return $dateB <=> $dateA;
                }

                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            });

            return $compatible[0];
        }

        // Fallback to latestFilesIndexes (CurseForge index per gameVersion + modLoader)
        $indexes = is_array($rawMod['latestFilesIndexes'] ?? null) ? $rawMod['latestFilesIndexes'] : [];
        $expectedLoaderId = self::LOADER_MAP[$normalizedLoader];
        $matchingIndexes = [];

        foreach ($indexes as $idx) {
            if (!is_array($idx)) {
                continue;
            }

            $idxVersion = trim((string) ($idx['gameVersion'] ?? ''));
            $idxFileId = (int) ($idx['fileId'] ?? 0);
            if ($idxVersion !== $cleanVersion || $idxFileId <= 0) {
                continue;
            }

            $idxLoader = isset($idx['modLoader']) && is_numeric($idx['modLoader'])
                ? (int) $idx['modLoader']
                : 0;
            $idxFileName = (string) ($idx['filename'] ?? '');

            $loaderMatches = $idxLoader === $expectedLoaderId
                || ($normalizedLoader === 'forge' && $idxLoader === 0 && !$this->filenameMentionsNonForgeLoader($idxFileName));

            if ($loaderMatches) {
                $matchingIndexes[] = $idx;
            }
        }

        if ($matchingIndexes === []) {
            return null;
        }

        usort($matchingIndexes, static function (array $a, array $b): int {
            $priority = static function (array $item): int {
                $rt = (int) ($item['releaseType'] ?? 3);

                return match ($rt) {
                    1 => 0, // Release
                    2 => 1, // Beta
                    default => 2, // Alpha / other
                };
            };

            $pA = $priority($a);
            $pB = $priority($b);
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }

            return ((int) ($b['fileId'] ?? 0)) <=> ((int) ($a['fileId'] ?? 0));
        });

        $best = $matchingIndexes[0];
        $fileName = (string) ($best['filename'] ?? '');

        return [
            'id' => (int) $best['fileId'],
            'fileName' => $fileName,
            'displayName' => $fileName,
            'gameVersions' => [$cleanVersion, self::LOADER_TAGS[$normalizedLoader]],
            'modLoader' => isset($best['modLoader']) && is_numeric($best['modLoader'])
                ? (int) $best['modLoader']
                : $expectedLoaderId,
            'releaseType' => (int) ($best['releaseType'] ?? 1),
            '_from_index' => true,
        ];
    }

    private function filenameMentionsNonForgeLoader(string $fileName): bool
    {
        $lower = strtolower($fileName);

        return str_contains($lower, 'fabric')
            || str_contains($lower, 'quilt')
            || str_contains($lower, 'neoforge');
    }

    /**
     * Fetch the latest strictly compatible file for a mod via the CurseForge /files endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function fetchLatestCompatibleFile(int $modId, ?string $mcVersion, ?string $loader): ?array
    {
        $cleanVersion = trim((string) $mcVersion);
        $normalizedLoader = $this->normalizeLoader($loader);
        if ($cleanVersion === '' || $normalizedLoader === null) {
            return null;
        }

        $response = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}/files", [
            'gameVersion' => $cleanVersion,
            'modLoaderType' => self::LOADER_MAP[$normalizedLoader],
            'pageSize' => 20,
        ]);

        $payload = $this->decodeResponse($response, "Impossible de récupérer les fichiers du mod #{$modId}.");
        $files = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $compatible = [];
        foreach ($files as $file) {
            if (is_array($file) && $this->isFileCompatible($file, $cleanVersion, $normalizedLoader)) {
                $compatible[] = $file;
            }
        }

        if ($compatible === []) {
            return null;
        }

        usort($compatible, static function (array $a, array $b): int {
            $dateA = strtotime((string) ($a['fileDate'] ?? '')) ?: 0;
            $dateB = strtotime((string) ($b['fileDate'] ?? '')) ?: 0;
            if ($dateA !== $dateB) {
                return $dateB <=> $dateA;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return $compatible[0];
    }

    /**
     * Resolve download URL for a CurseForge file, with official Edge CDN fallback when downloadUrl is null.
     *
     * @param  array<string, mixed>  $file
     */
    public function resolveDownloadUrl(array $file): ?string
    {
        $url = trim((string) ($file['downloadUrl'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $fileId = (int) ($file['id'] ?? 0);
        $fileName = basename((string) ($file['fileName'] ?? ''));
        if ($fileId <= 0 || $fileName === '') {
            return null;
        }

        $idStr = (string) $fileId;
        $id1 = substr($idStr, 0, 4);
        $id2 = ltrim(substr($idStr, 4), '0');
        if ($id2 === '') {
            $id2 = '0';
        }

        return sprintf('https://edge.forgecdn.net/files/%s/%s/%s', $id1, $id2, rawurlencode($fileName));
    }

    /**
     * Install a single CurseForge mod into the active instance's mods/ folder with strict MC + Loader validation.
     *
     * @return array<string, mixed>
     */
    public function installMod(Instance $instance, int $modId, ?int $fileId = null): array
    {
        [$mcVersion, $loader] = $this->assertInstanceSupportsCurseForge($instance);

        $modResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}");
        $modPayload = $this->decodeResponse($modResponse, "Impossible de récupérer le mod CurseForge #{$modId}.");
        $rawMod = is_array($modPayload['data'] ?? null) ? $modPayload['data'] : [];

        if ($rawMod === []) {
            throw new CurseForgeException("Mod CurseForge #{$modId} introuvable.", 404);
        }

        $targetFile = $this->resolveStrictTargetFile($modId, $rawMod, $fileId, $mcVersion, $loader);
        $downloadUrl = $this->resolveDownloadUrl($targetFile);
        if ($downloadUrl === null) {
            throw new CurseForgeException("Aucune URL de téléchargement disponible pour ce fichier CurseForge.", 422);
        }

        $fileName = $this->sanitizeJarFilename((string) ($targetFile['fileName'] ?? ''));
        $instance->ensureDirectoriesExist();
        $modsDir = $this->modsDirectory($instance);
        $this->cleanupResidualTmpFiles($modsDir);
        $targetPath = $modsDir . DIRECTORY_SEPARATOR . $fileName;

        $this->downloadBinaryFile($downloadUrl, $targetPath, $targetFile);

        $existing = CurseForgeMod::query()
            ->where('instance_id', $instance->id)
            ->where('curseforge_mod_id', $modId)
            ->first();

        $oldFileName = $existing ? basename((string) $existing->file_name) : null;
        $oldLogoUrl = $existing?->logo_url;

        if ($existing && $existing->file_name !== $fileName) {
            $oldPath = $modsDir . DIRECTORY_SEPARATOR . basename($existing->file_name);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $fingerprint = isset($targetFile['fileFingerprint']) && is_numeric($targetFile['fileFingerprint'])
            ? (int) $targetFile['fileFingerprint']
            : self::computeFileFingerprint($targetPath);

        $logo = is_array($rawMod['logo'] ?? null) ? $rawMod['logo'] : [];
        $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');

        $record = CurseForgeMod::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'curseforge_mod_id' => $modId,
            ],
            [
                'modpack_id' => null,
                'curseforge_file_id' => (int) $targetFile['id'],
                'name' => (string) ($rawMod['name'] ?? pathinfo($fileName, PATHINFO_FILENAME)),
                'slug' => isset($rawMod['slug']) ? (string) $rawMod['slug'] : null,
                'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                'file_name' => $fileName,
                'version' => (string) ($targetFile['displayName'] ?? $fileName),
                'mc_version' => $mcVersion,
                'loader' => $loader,
                'fingerprint' => $fingerprint,
                'download_url' => $downloadUrl,
            ]
        );

        $this->syncOptionalModsOnModUpdate($instance, $record, $oldFileName, $oldLogoUrl);

        $this->invalidateInstancePackageCache($instance);

        return [
            'mod' => $record->toArray(),
            'file_name' => $fileName,
        ];
    }

    /**
     * Download and install a CurseForge Modpack into the active instance's mods/ directory.
     *
     * @return array<string, mixed>
     */
    public function installModpack(Instance $instance, int $modpackId, ?int $fileId = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new CurseForgeException("L'extension PHP ZipArchive est requise pour installer un modpack.", 500);
        }

        [$mcVersion, $loader] = $this->assertInstanceSupportsCurseForge($instance);

        $packResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modpackId}");
        $packPayload = $this->decodeResponse($packResponse, "Impossible de récupérer le modpack CurseForge #{$modpackId}.");
        $rawPack = is_array($packPayload['data'] ?? null) ? $packPayload['data'] : [];

        if ($rawPack === []) {
            throw new CurseForgeException("Modpack CurseForge #{$modpackId} introuvable.", 404);
        }

        $targetFile = $this->resolveStrictTargetFile($modpackId, $rawPack, $fileId, $mcVersion, $loader);
        $downloadUrl = $this->resolveDownloadUrl($targetFile);
        if ($downloadUrl === null) {
            throw new CurseForgeException("Aucune URL de téléchargement disponible pour ce modpack.", 422);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'cf_modpack_');
        if ($tmpZip === false) {
            throw new CurseForgeException("Impossible de créer un fichier temporaire pour le modpack.", 500);
        }

        $logo = is_array($rawPack['logo'] ?? null) ? $rawPack['logo'] : [];
        $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');
        $packName = (string) ($rawPack['name'] ?? 'Modpack');
        $packFileName = (string) ($targetFile['fileName'] ?? '');
        $packVersion = (string) ($targetFile['displayName'] ?? $packFileName);

        try {
            $this->downloadBinaryFile($downloadUrl, $tmpZip, $targetFile);

            $modpackRecord = CurseForgeModpack::updateOrCreate(
                [
                    'instance_id' => $instance->id,
                    'curseforge_modpack_id' => $modpackId,
                ],
                [
                    'curseforge_file_id' => (int) ($targetFile['id'] ?? 0),
                    'name' => $packName,
                    'slug' => isset($rawPack['slug']) ? (string) $rawPack['slug'] : null,
                    'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                    'file_name' => $packFileName !== '' ? $packFileName : null,
                    'version' => $packVersion !== '' ? $packVersion : null,
                    'mc_version' => $mcVersion,
                    'loader' => $loader,
                    'download_url' => $downloadUrl,
                ]
            );

            $summary = $this->installModpackFromZip(
                $instance,
                $tmpZip,
                $packName,
                $mcVersion,
                $loader,
                $modpackRecord
            );

            $modpackRecord->mods_count = $modpackRecord->mods()->count();
            if ($modpackRecord->version === null || $modpackRecord->version === '') {
                $modpackRecord->version = $summary['modpack_version'] !== '' ? $summary['modpack_version'] : null;
            }
            $modpackRecord->save();

            $summary['modpack'] = $modpackRecord->fresh()->toArray();

            return $summary;
        } finally {
            if (is_file($tmpZip)) {
                @unlink($tmpZip);
            }
        }
    }

    /**
     * Parse a CurseForge modpack ZIP (manifest.json + overrides/mods/*) and install all mods into the instance.
     *
     * @return array<string, mixed>
     */
    public function installModpackFromZip(
        Instance $instance,
        string $zipPath,
        string $fallbackName,
        string $mcVersion,
        string $loader,
        ?CurseForgeModpack $modpack = null
    ): array {
        @set_time_limit(0);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new CurseForgeException("Archive de modpack invalide ou corrompue.", 422);
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                throw new CurseForgeException("Le fichier manifest.json est absent de l'archive du modpack.", 422);
            }

            $manifest = $this->parseModpackManifest($manifestRaw);
            $manifestFiles = $manifest['files'];

            $instance->ensureDirectoriesExist();
            $modsDir = $this->modsDirectory($instance);
            $this->cleanupResidualTmpFiles($modsDir);

            $fileIds = [];
            $modIds = [];
            foreach ($manifestFiles as $entry) {
                $fileIds[] = (int) $entry['fileID'];
                $modIds[] = (int) $entry['projectID'];
            }
            $fileIds = array_values(array_unique(array_filter($fileIds, static fn (int $id): bool => $id > 0)));
            $modIds = array_values(array_unique(array_filter($modIds, static fn (int $id): bool => $id > 0)));

            $resolvedFiles = $this->fetchFilesBatch($fileIds);
            $resolvedMods = $this->fetchModsBatch($modIds);

            $installedCount = 0;
            $skippedCount = 0;
            $overridesCount = 0;
            $errors = [];
            $installedMods = [];
            $installedModIds = [];
            $downloadQueue = [];

            foreach ($manifestFiles as $entry) {
                $projectId = (int) $entry['projectID'];
                $entryFileId = (int) $entry['fileID'];

                $fileData = $resolvedFiles[$entryFileId] ?? null;
                if ($fileData === null) {
                    $skippedCount++;
                    $errors[] = "Fichier #{$entryFileId} (projet #{$projectId}) introuvable via l'API CurseForge.";
                    continue;
                }

                $fileUrl = $this->resolveDownloadUrl($fileData);
                if ($fileUrl === null) {
                    $skippedCount++;
                    $errors[] = "Aucune URL de téléchargement pour le fichier #{$entryFileId}.";
                    continue;
                }

                $rawFileName = (string) ($fileData['fileName'] ?? '');
                if ($rawFileName === '' || !str_ends_with(strtolower($rawFileName), '.jar')) {
                    // Non-jar entries (e.g. resourcepacks/shaderpacks referenced in manifest) are skipped for mods/
                    $skippedCount++;
                    continue;
                }

                $fileName = $this->sanitizeJarFilename($rawFileName);
                $downloadQueue[] = [
                    'project_id' => $projectId,
                    'file_id' => $entryFileId,
                    'file_data' => $fileData,
                    'url' => $fileUrl,
                    'file_name' => $fileName,
                    'target_path' => $modsDir . DIRECTORY_SEPARATOR . $fileName,
                ];
            }

            // Stream each JAR directly to disk via sink() (zero-RAM) with PK signature check & atomic move
            foreach ($downloadQueue as $item) {
                try {
                    $this->downloadBinaryFile($item['url'], $item['target_path'], $item['file_data']);
                } catch (\Throwable $e) {
                    $skippedCount++;
                    $errors[] = "Échec du téléchargement de {$item['file_name']} : " . $e->getMessage();
                    continue;
                }

                $projectId = $item['project_id'];
                $entryFileId = $item['file_id'];
                $fileData = $item['file_data'];
                $fileName = $item['file_name'];
                $targetPath = $item['target_path'];
                $fileUrl = $item['url'];

                $modMeta = $resolvedMods[$projectId] ?? [];
                $logo = is_array($modMeta['logo'] ?? null) ? $modMeta['logo'] : [];
                $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');
                $fingerprint = isset($fileData['fileFingerprint']) && is_numeric($fileData['fileFingerprint'])
                    ? (int) $fileData['fileFingerprint']
                    : self::computeFileFingerprint($targetPath);

                $existing = CurseForgeMod::query()
                    ->where('instance_id', $instance->id)
                    ->where('curseforge_mod_id', $projectId)
                    ->first();

                if ($existing && $existing->file_name !== $fileName) {
                    $oldPath = $modsDir . DIRECTORY_SEPARATOR . basename($existing->file_name);
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $updateValues = [
                    'curseforge_file_id' => $entryFileId,
                    'name' => (string) ($modMeta['name'] ?? pathinfo($fileName, PATHINFO_FILENAME)),
                    'slug' => isset($modMeta['slug']) ? (string) $modMeta['slug'] : null,
                    'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                    'file_name' => $fileName,
                    'version' => (string) ($fileData['displayName'] ?? $fileName),
                    'mc_version' => $mcVersion,
                    'loader' => $loader,
                    'fingerprint' => $fingerprint,
                    'download_url' => $fileUrl,
                ];

                if ($modpack !== null) {
                    $updateValues['modpack_id'] = $modpack->id;
                }

                CurseForgeMod::updateOrCreate(
                    [
                        'instance_id' => $instance->id,
                        'curseforge_mod_id' => $projectId,
                    ],
                    $updateValues
                );

                $installedCount++;
                $installedMods[] = $fileName;
                $installedModIds[] = $projectId;
            }

            // Copy any overrides/mods/* files directly into instance mods/
            $overridesCount = $this->extractModpackOverrides($zip, $manifest, $modsDir);

            $this->invalidateInstancePackageCache($instance);

            return [
                'modpack_name' => (string) ($manifest['name'] ?? $fallbackName),
                'modpack_version' => (string) ($manifest['version'] ?? ''),
                'installed_count' => $installedCount,
                'overrides_count' => $overridesCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
                'installed_mods' => $installedMods,
                'installed_mod_ids' => array_values(array_unique($installedModIds)),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Step 1 of chunked modpack install/update:
     * Download the small modpack ZIP via sink(), parse manifest.json, resolve CurseForge metadata,
     * extract overrides/mods/*, upsert CurseForgeModpack, and persist a JSON session file.
     *
     * @return array<string, mixed>
     */
    public function prepareModpackInstall(
        Instance $instance,
        int $modpackId,
        ?int $fileId = null,
        ?int $existingModpackId = null
    ): array {
        if (!class_exists(ZipArchive::class)) {
            throw new CurseForgeException("L'extension PHP ZipArchive est requise pour installer un modpack.", 500);
        }

        [$mcVersion, $loader] = $this->assertInstanceSupportsCurseForge($instance);

        $isUpdate = false;
        $previousMods = [];

        if ($existingModpackId !== null) {
            $existingPack = CurseForgeModpack::findOrFail($existingModpackId);
            if ((int) $existingPack->instance_id !== (int) $instance->id) {
                throw new CurseForgeException("Ce modpack n'appartient pas à l'instance active.", 403);
            }
            $isUpdate = true;
            $modpackId = (int) $existingPack->curseforge_modpack_id;
            $previousMods = CurseForgeMod::query()
                ->where('instance_id', $instance->id)
                ->where('modpack_id', $existingPack->id)
                ->get(['id', 'curseforge_mod_id', 'file_name'])
                ->map(static fn (CurseForgeMod $m): array => [
                    'id' => (int) $m->id,
                    'curseforge_mod_id' => (int) $m->curseforge_mod_id,
                    'file_name' => basename((string) $m->file_name),
                ])
                ->all();
        }

        $packResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modpackId}");
        $packPayload = $this->decodeResponse($packResponse, "Impossible de récupérer le modpack CurseForge #{$modpackId}.");
        $rawPack = is_array($packPayload['data'] ?? null) ? $packPayload['data'] : [];

        if ($rawPack === []) {
            throw new CurseForgeException("Modpack CurseForge #{$modpackId} introuvable.", 404);
        }

        $targetFile = $this->resolveStrictTargetFile($modpackId, $rawPack, $fileId, $mcVersion, $loader);
        $downloadUrl = $this->resolveDownloadUrl($targetFile);
        if ($downloadUrl === null) {
            throw new CurseForgeException("Aucune URL de téléchargement disponible pour ce modpack.", 422);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'cf_modpack_');
        if ($tmpZip === false) {
            throw new CurseForgeException("Impossible de créer un fichier temporaire pour le modpack.", 500);
        }

        $logo = is_array($rawPack['logo'] ?? null) ? $rawPack['logo'] : [];
        $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');
        $packName = (string) ($rawPack['name'] ?? 'Modpack');
        $packFileName = (string) ($targetFile['fileName'] ?? '');
        $packVersion = (string) ($targetFile['displayName'] ?? $packFileName);

        try {
            $this->downloadBinaryFile($downloadUrl, $tmpZip, $targetFile);

            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                throw new CurseForgeException("Archive de modpack invalide ou corrompue.", 422);
            }

            try {
                $manifestRaw = $zip->getFromName('manifest.json');
                if ($manifestRaw === false) {
                    throw new CurseForgeException("Le fichier manifest.json est absent de l'archive du modpack.", 422);
                }

                $manifest = $this->parseModpackManifest($manifestRaw);
                $manifestFiles = $manifest['files'];

                $instance->ensureDirectoriesExist();
                $modsDir = $this->modsDirectory($instance);
                $this->cleanupResidualTmpFiles($modsDir);

                $fileIds = [];
                $modIds = [];
                foreach ($manifestFiles as $entry) {
                    $fileIds[] = (int) $entry['fileID'];
                    $modIds[] = (int) $entry['projectID'];
                }
                $fileIds = array_values(array_unique(array_filter($fileIds, static fn (int $id): bool => $id > 0)));
                $modIds = array_values(array_unique(array_filter($modIds, static fn (int $id): bool => $id > 0)));

                $resolvedFiles = $this->fetchFilesBatch($fileIds);
                $resolvedMods = $this->fetchModsBatch($modIds);

                $overridesCount = $this->extractModpackOverrides($zip, $manifest, $modsDir);
            } finally {
                $zip->close();
            }

            $modpackRecord = CurseForgeModpack::updateOrCreate(
                [
                    'instance_id' => $instance->id,
                    'curseforge_modpack_id' => $modpackId,
                ],
                [
                    'curseforge_file_id' => (int) ($targetFile['id'] ?? 0),
                    'name' => $packName,
                    'slug' => isset($rawPack['slug']) ? (string) $rawPack['slug'] : null,
                    'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                    'file_name' => $packFileName !== '' ? $packFileName : null,
                    'version' => $packVersion !== '' ? $packVersion : ((string) ($manifest['version'] ?? '') ?: null),
                    'mc_version' => $mcVersion,
                    'loader' => $loader,
                    'download_url' => $downloadUrl,
                ]
            );

            $queue = [];
            $skippedCount = 0;
            $errors = [];

            foreach ($manifestFiles as $entry) {
                $projectId = (int) $entry['projectID'];
                $entryFileId = (int) $entry['fileID'];

                $fileData = $resolvedFiles[$entryFileId] ?? null;
                if ($fileData === null) {
                    $skippedCount++;
                    $errors[] = "Fichier #{$entryFileId} (projet #{$projectId}) introuvable via l'API CurseForge.";
                    continue;
                }

                $fileUrl = $this->resolveDownloadUrl($fileData);
                if ($fileUrl === null) {
                    $skippedCount++;
                    $errors[] = "Aucune URL de téléchargement pour le fichier #{$entryFileId}.";
                    continue;
                }

                $rawFileName = (string) ($fileData['fileName'] ?? '');
                if ($rawFileName === '' || !str_ends_with(strtolower($rawFileName), '.jar')) {
                    $skippedCount++;
                    continue;
                }

                $fileName = $this->sanitizeJarFilename($rawFileName);
                $modMeta = $resolvedMods[$projectId] ?? [];
                $modLogo = is_array($modMeta['logo'] ?? null) ? $modMeta['logo'] : [];
                $modLogoUrl = (string) ($modLogo['thumbnailUrl'] ?? $modLogo['url'] ?? '');

                $queue[] = [
                    'project_id' => $projectId,
                    'file_id' => $entryFileId,
                    'file_name' => $fileName,
                    'name' => (string) ($modMeta['name'] ?? pathinfo($fileName, PATHINFO_FILENAME)),
                    'slug' => isset($modMeta['slug']) ? (string) $modMeta['slug'] : null,
                    'logo_url' => $modLogoUrl !== '' ? $modLogoUrl : null,
                    'version' => (string) ($fileData['displayName'] ?? $fileName),
                    'fingerprint' => isset($fileData['fileFingerprint']) && is_numeric($fileData['fileFingerprint'])
                        ? (int) $fileData['fileFingerprint']
                        : null,
                    'url' => $fileUrl,
                    'file_data' => [
                        'id' => $entryFileId,
                        'fileName' => $fileName,
                    ],
                ];
            }

            $sessionId = Str::uuid()->toString();
            $sessionData = [
                'session_id' => $sessionId,
                'instance_id' => (int) $instance->id,
                'modpack_id' => (int) $modpackRecord->id,
                'curseforge_modpack_id' => $modpackId,
                'modpack_name' => (string) ($manifest['name'] ?? $packName),
                'modpack_version' => (string) ($manifest['version'] ?? $packVersion),
                'mc_version' => $mcVersion,
                'loader' => $loader,
                'is_update' => $isUpdate,
                'previous_mods' => $previousMods,
                'overrides_count' => $overridesCount,
                'installed_count' => 0,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
                'installed_mod_ids' => [],
                'files' => $queue,
                'created_at' => now()->toIso8601String(),
            ];

            $this->writeModpackSession($sessionId, $sessionData);

            return [
                'session_id' => $sessionId,
                'modpack' => $modpackRecord->toArray(),
                'modpack_name' => $sessionData['modpack_name'],
                'total' => count($queue),
                'overrides_count' => $overridesCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
                'files' => array_map(
                    static fn (array $item): array => [
                        'file_name' => $item['file_name'],
                        'name' => $item['name'],
                    ],
                    $queue
                ),
            ];
        } finally {
            if (is_file($tmpZip)) {
                @unlink($tmpZip);
            }
        }
    }

    /**
     * Step 2 of chunked modpack install/update:
     * Stream a small batch (1-2 files) directly to disk via sink() so each HTTP request stays well under 60s.
     *
     * @return array<string, mixed>
     */
    public function installModpackBatch(Instance $instance, string $sessionId, int $offset = 0, int $limit = 1): array
    {
        $session = $this->readModpackSession($sessionId, $instance);
        $files = is_array($session['files'] ?? null) ? $session['files'] : [];
        $total = count($files);

        $offset = max(0, $offset);
        $limit = max(1, min(5, $limit));
        $slice = array_slice($files, $offset, $limit);

        $instance->ensureDirectoriesExist();
        $modsDir = $this->modsDirectory($instance);
        $this->cleanupResidualTmpFiles($modsDir);

        $installedInBatch = 0;
        $skippedInBatch = 0;
        $batchErrors = [];
        $currentFiles = [];

        foreach ($slice as $item) {
            $fileName = (string) $item['file_name'];
            $currentFiles[] = $fileName;
            $targetPath = $modsDir . DIRECTORY_SEPARATOR . $fileName;

            try {
                $this->downloadBinaryFile(
                    (string) $item['url'],
                    $targetPath,
                    is_array($item['file_data'] ?? null) ? $item['file_data'] : null
                );
            } catch (\Throwable $e) {
                $skippedInBatch++;
                $err = "Échec du téléchargement de {$fileName} : " . $e->getMessage();
                $batchErrors[] = $err;
                $session['errors'][] = $err;
                continue;
            }

            $projectId = (int) $item['project_id'];
            $existing = CurseForgeMod::query()
                ->where('instance_id', $instance->id)
                ->where('curseforge_mod_id', $projectId)
                ->first();

            if ($existing && $existing->file_name !== $fileName) {
                $oldPath = $modsDir . DIRECTORY_SEPARATOR . basename($existing->file_name);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $fingerprint = isset($item['fingerprint']) && is_numeric($item['fingerprint'])
                ? (int) $item['fingerprint']
                : self::computeFileFingerprint($targetPath);

            CurseForgeMod::updateOrCreate(
                [
                    'instance_id' => $instance->id,
                    'curseforge_mod_id' => $projectId,
                ],
                [
                    'modpack_id' => (int) $session['modpack_id'],
                    'curseforge_file_id' => (int) $item['file_id'],
                    'name' => (string) $item['name'],
                    'slug' => $item['slug'] ?? null,
                    'logo_url' => $item['logo_url'] ?? null,
                    'file_name' => $fileName,
                    'version' => (string) $item['version'],
                    'mc_version' => (string) $session['mc_version'],
                    'loader' => (string) $session['loader'],
                    'fingerprint' => $fingerprint,
                    'download_url' => (string) $item['url'],
                ]
            );

            $installedInBatch++;
            $session['installed_mod_ids'][] = $projectId;
        }

        $session['installed_count'] = (int) ($session['installed_count'] ?? 0) + $installedInBatch;
        $session['skipped_count'] = (int) ($session['skipped_count'] ?? 0) + $skippedInBatch;
        $session['installed_mod_ids'] = array_values(array_unique($session['installed_mod_ids'] ?? []));

        $this->writeModpackSession($sessionId, $session);

        $nextOffset = min($total, $offset + count($slice));
        $done = $nextOffset >= $total;

        return [
            'session_id' => $sessionId,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $done,
            'installed_in_batch' => $installedInBatch,
            'skipped_in_batch' => $skippedInBatch,
            'errors' => $batchErrors,
            'current_files' => $currentFiles,
        ];
    }

    /**
     * Step 3 of chunked modpack install/update:
     * Remove stale .jar files on update, refresh mods_count, purge instance zip cache, and delete session file.
     *
     * @return array<string, mixed>
     */
    public function finalizeModpackInstall(Instance $instance, string $sessionId): array
    {
        $session = $this->readModpackSession($sessionId, $instance);
        $modsDir = $this->modsDirectory($instance);
        $this->cleanupResidualTmpFiles($modsDir);

        $modpack = CurseForgeModpack::findOrFail((int) $session['modpack_id']);
        $removedCount = 0;

        if (!empty($session['is_update']) && !empty($session['previous_mods']) && is_array($session['previous_mods'])) {
            $newModIds = array_flip(array_map('intval', $session['installed_mod_ids'] ?? []));

            foreach ($session['previous_mods'] as $oldEntry) {
                $oldProjectId = (int) ($oldEntry['curseforge_mod_id'] ?? 0);
                if (isset($newModIds[$oldProjectId])) {
                    continue;
                }

                $oldRecordId = (int) ($oldEntry['id'] ?? 0);
                $oldFileName = basename((string) ($oldEntry['file_name'] ?? ''));

                if ($oldRecordId > 0) {
                    CurseForgeMod::query()
                        ->where('id', $oldRecordId)
                        ->where('instance_id', $instance->id)
                        ->delete();
                }
                $removedCount++;

                if ($oldFileName !== '') {
                    $stillReferenced = CurseForgeMod::query()
                        ->where('instance_id', $instance->id)
                        ->where('file_name', $oldFileName)
                        ->exists();

                    if (!$stillReferenced) {
                        $oldPath = $modsDir . DIRECTORY_SEPARATOR . $oldFileName;
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }
            }
        }

        $modpack->mods_count = $modpack->mods()->count();
        $modpack->save();

        $this->invalidateInstancePackageCache($instance);

        $sessionPath = $this->modpackSessionPath($sessionId);
        if (is_file($sessionPath)) {
            @unlink($sessionPath);
        }

        return [
            'modpack_name' => (string) ($session['modpack_name'] ?? $modpack->name),
            'modpack_version' => (string) ($session['modpack_version'] ?? $modpack->version ?? ''),
            'installed_count' => (int) ($session['installed_count'] ?? 0),
            'overrides_count' => (int) ($session['overrides_count'] ?? 0),
            'skipped_count' => (int) ($session['skipped_count'] ?? 0),
            'removed_count' => $removedCount,
            'errors' => is_array($session['errors'] ?? null) ? $session['errors'] : [],
            'is_update' => (bool) ($session['is_update'] ?? false),
            'modpack' => $modpack->fresh()->toArray(),
        ];
    }

    /**
     * Extract overrides/mods/*.jar from a modpack ZIP into the instance mods/ directory.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function extractModpackOverrides(ZipArchive $zip, array $manifest, string $modsDir): int
    {
        $overridesCount = 0;
        $overridesDir = trim((string) ($manifest['overrides'] ?? 'overrides'), '/\\');
        if ($overridesDir === '') {
            return 0;
        }

        $prefix = $overridesDir . '/mods/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (!str_starts_with($entryName, $prefix) || str_ends_with($entryName, '/')) {
                continue;
            }
            $relName = substr($entryName, strlen($prefix));
            if ($relName === '' || str_contains($relName, '/') || str_contains($relName, '..')) {
                continue;
            }
            if (!str_ends_with(strtolower($relName), '.jar')) {
                continue;
            }
            $stream = $zip->getFromIndex($i);
            if ($stream !== false) {
                $safeName = $this->sanitizeJarFilename($relName);
                $this->writeFileAtomically($modsDir . DIRECTORY_SEPARATOR . $safeName, $stream);
                $overridesCount++;
            }
        }

        return $overridesCount;
    }

    private function modpackSessionPath(string $sessionId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
        if ($safeId === null || $safeId === '') {
            throw new CurseForgeException("Identifiant de session de modpack invalide.", 422);
        }

        return storage_path('app/cf-sessions/' . $safeId . '.json');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeModpackSession(string $sessionId, array $data): void
    {
        $path = $this->modpackSessionPath($sessionId);
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || @file_put_contents($path, $encoded) === false) {
            throw new CurseForgeException("Impossible d'enregistrer la session d'installation du modpack.", 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readModpackSession(string $sessionId, Instance $instance): array
    {
        $path = $this->modpackSessionPath($sessionId);
        if (!is_file($path)) {
            throw new CurseForgeException("Session d'installation du modpack introuvable ou expirée.", 404);
        }

        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new CurseForgeException("Fichier de session du modpack corrompu.", 500);
        }

        if ((int) ($decoded['instance_id'] ?? 0) !== (int) $instance->id) {
            throw new CurseForgeException("Cette session n'appartient pas à l'instance active.", 403);
        }

        return $decoded;
    }

    /**
     * Validate and parse a CurseForge modpack manifest.json payload.
     *
     * @return array{name: string, version: string, overrides: string, files: array<int, array{projectID: int, fileID: int, required: bool}>}
     */
    public function parseModpackManifest(string $jsonContent): array
    {
        $decoded = json_decode($jsonContent, true);
        if (!is_array($decoded)) {
            throw new CurseForgeException("Le fichier manifest.json est invalide (JSON corrompu).", 422);
        }

        if (!isset($decoded['files']) || !is_array($decoded['files'])) {
            throw new CurseForgeException("Le fichier manifest.json ne contient aucune liste 'files' valide.", 422);
        }

        $files = [];
        foreach ($decoded['files'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $projectId = isset($entry['projectID']) ? (int) $entry['projectID'] : 0;
            $fileId = isset($entry['fileID']) ? (int) $entry['fileID'] : 0;
            if ($projectId > 0 && $fileId > 0) {
                $files[] = [
                    'projectID' => $projectId,
                    'fileID' => $fileId,
                    'required' => (bool) ($entry['required'] ?? true),
                ];
            }
        }

        return [
            'name' => (string) ($decoded['name'] ?? 'Modpack'),
            'version' => (string) ($decoded['version'] ?? ''),
            'overrides' => (string) ($decoded['overrides'] ?? 'overrides'),
            'files' => $files,
        ];
    }

    /**
     * List installed modpacks, individual CurseForge mods, and external .jar files on an instance.
     *
     * @return array{
     *   modpacks: array<int, array<string, mixed>>,
     *   curseforge: array<int, array<string, mixed>>,
     *   external: array<int, array<string, mixed>>
     * }
     */
    public function getInstalledMods(Instance $instance): array
    {
        $instance->ensureDirectoriesExist();
        $modsDir = $this->modsDirectory($instance);
        $this->cleanupResidualTmpFiles($modsDir);

        $diskFiles = [];
        if (is_dir($modsDir)) {
            foreach (glob($modsDir . DIRECTORY_SEPARATOR . '*.jar') ?: [] as $fullPath) {
                if (!is_file($fullPath)) {
                    continue;
                }
                $base = basename($fullPath);
                $diskFiles[$base] = [
                    'path' => $fullPath,
                    'size' => (int) (@filesize($fullPath) ?: 0),
                    'modified_at' => (int) (@filemtime($fullPath) ?: 0),
                ];
            }
        }

        $modpackRecords = Schema::hasTable('curseforge_modpacks')
            ? CurseForgeModpack::query()
                ->where('instance_id', $instance->id)
                ->withCount('mods')
                ->orderBy('name')
                ->get()
            : collect();

        $modpacks = [];
        foreach ($modpackRecords as $pack) {
            $modpacks[] = [
                'id' => (int) $pack->id,
                'curseforge_modpack_id' => (int) $pack->curseforge_modpack_id,
                'curseforge_file_id' => (int) $pack->curseforge_file_id,
                'name' => (string) $pack->name,
                'slug' => $pack->slug,
                'logo_url' => $pack->logo_url,
                'file_name' => $pack->file_name,
                'version' => (string) ($pack->version ?: $pack->file_name ?: ''),
                'mc_version' => $pack->mc_version,
                'loader' => $pack->loader,
                'mods_count' => (int) max((int) $pack->mods_count, (int) ($pack->mods_count_computed ?? 0)),
            ];
        }

        $trackedRecords = CurseForgeMod::query()
            ->where('instance_id', $instance->id)
            ->orderBy('name')
            ->get();

        $curseforgeMods = [];
        $trackedFileNames = [];

        foreach ($trackedRecords as $record) {
            $fileName = basename($record->file_name);
            $onDisk = isset($diskFiles[$fileName]);
            $trackedFileNames[$fileName] = true;

            // Mods belonging to an installed modpack are managed under the modpack section
            if ($record->modpack_id !== null) {
                continue;
            }

            $curseforgeMods[] = [
                'id' => (int) $record->id,
                'curseforge_mod_id' => (int) $record->curseforge_mod_id,
                'curseforge_file_id' => (int) $record->curseforge_file_id,
                'name' => (string) $record->name,
                'slug' => $record->slug,
                'logo_url' => $record->logo_url,
                'file_name' => $fileName,
                'version' => (string) ($record->version ?: $fileName),
                'mc_version' => $record->mc_version,
                'loader' => $record->loader,
                'fingerprint' => $record->fingerprint,
                'exists_on_disk' => $onDisk,
                'size' => $onDisk ? $diskFiles[$fileName]['size'] : 0,
                'modified_at' => $onDisk ? $diskFiles[$fileName]['modified_at'] : null,
            ];
        }

        $externalMods = [];
        ksort($diskFiles, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($diskFiles as $fileName => $meta) {
            if (isset($trackedFileNames[$fileName])) {
                continue;
            }
            $externalMods[] = [
                'file_name' => $fileName,
                'size' => $meta['size'],
                'modified_at' => $meta['modified_at'],
            ];
        }

        return [
            'modpacks' => $modpacks,
            'curseforge' => $curseforgeMods,
            'external' => $externalMods,
        ];
    }

    /**
     * Check for compatible updates for all individual CurseForge-tracked mods and installed modpacks on an instance.
     *
     * @return array{updates: array<int, array<string, mixed>>, modpack_updates: array<int, array<string, mixed>>}
     */
    public function checkUpdates(Instance $instance): array
    {
        [$mcVersion, $loader] = $this->assertInstanceSupportsCurseForge($instance);

        $modRecords = CurseForgeMod::query()
            ->where('instance_id', $instance->id)
            ->whereNull('modpack_id')
            ->get();

        $modpackRecords = Schema::hasTable('curseforge_modpacks')
            ? CurseForgeModpack::query()
                ->where('instance_id', $instance->id)
                ->get()
            : collect();

        if ($modRecords->isEmpty() && $modpackRecords->isEmpty()) {
            return [
                'updates' => [],
                'modpack_updates' => [],
            ];
        }

        $allProjectIds = array_values(array_unique(array_merge(
            $modRecords->pluck('curseforge_mod_id')->map(static fn ($id): int => (int) $id)->all(),
            $modpackRecords->pluck('curseforge_modpack_id')->map(static fn ($id): int => (int) $id)->all()
        )));

        $projectsBatch = $this->fetchModsBatch($allProjectIds);

        $updates = [];
        foreach ($modRecords as $record) {
            $rawMod = $projectsBatch[(int) $record->curseforge_mod_id] ?? null;
            $latestCompatible = $rawMod !== null
                ? $this->findCompatibleFile($rawMod, $mcVersion, $loader)
                : null;

            if ($latestCompatible === null) {
                $latestCompatible = $this->fetchLatestCompatibleFile((int) $record->curseforge_mod_id, $mcVersion, $loader);
            }

            $updates[(int) $record->id] = $this->formatUpdateStatus($latestCompatible, (int) $record->curseforge_file_id);
        }

        $modpackUpdates = [];
        foreach ($modpackRecords as $pack) {
            $rawPack = $projectsBatch[(int) $pack->curseforge_modpack_id] ?? null;
            $latestCompatible = $rawPack !== null
                ? $this->findCompatibleFile($rawPack, $mcVersion, $loader)
                : null;

            if ($latestCompatible === null) {
                $latestCompatible = $this->fetchLatestCompatibleFile((int) $pack->curseforge_modpack_id, $mcVersion, $loader);
            }

            $modpackUpdates[(int) $pack->id] = $this->formatUpdateStatus($latestCompatible, (int) $pack->curseforge_file_id);
        }

        return [
            'updates' => $updates,
            'modpack_updates' => $modpackUpdates,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $latestCompatible
     * @return array<string, mixed>
     */
    private function formatUpdateStatus(?array $latestCompatible, int $currentFileId): array
    {
        if ($latestCompatible === null) {
            return [
                'has_update' => false,
                'latest_file_id' => null,
                'latest_file_name' => null,
                'latest_version' => null,
            ];
        }

        $latestFileId = (int) ($latestCompatible['id'] ?? 0);
        $hasUpdate = $latestFileId > 0 && $latestFileId !== $currentFileId;

        return [
            'has_update' => $hasUpdate,
            'latest_file_id' => $latestFileId,
            'latest_file_name' => (string) ($latestCompatible['fileName'] ?? ''),
            'latest_version' => (string) ($latestCompatible['displayName'] ?? $latestCompatible['fileName'] ?? ''),
            'latest_file_date' => isset($latestCompatible['fileDate']) ? (string) $latestCompatible['fileDate'] : null,
        ];
    }

    /**
     * Update an installed CurseForge modpack in 1 click, removing stale .jar files from the previous version.
     *
     * @return array<string, mixed>
     */
    public function updateModpack(Instance $instance, CurseForgeModpack $modpack, ?int $targetFileId = null): array
    {
        if ((int) $modpack->instance_id !== (int) $instance->id) {
            throw new CurseForgeException("Ce modpack n'appartient pas à l'instance active.", 403);
        }

        $previousMods = CurseForgeMod::query()
            ->where('instance_id', $instance->id)
            ->where('modpack_id', $modpack->id)
            ->get();

        $summary = $this->installModpack($instance, (int) $modpack->curseforge_modpack_id, $targetFileId);

        $newModIds = array_flip($summary['installed_mod_ids'] ?? []);
        $modsDir = $this->modsDirectory($instance);
        $removedCount = 0;

        foreach ($previousMods as $oldMod) {
            if (isset($newModIds[(int) $oldMod->curseforge_mod_id])) {
                continue;
            }

            $oldFileName = basename((string) $oldMod->file_name);
            $oldMod->delete();
            $removedCount++;

            if ($oldFileName !== '') {
                $stillReferenced = CurseForgeMod::query()
                    ->where('instance_id', $instance->id)
                    ->where('file_name', $oldFileName)
                    ->exists();

                if (!$stillReferenced) {
                    $oldPath = $modsDir . DIRECTORY_SEPARATOR . $oldFileName;
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }
        }

        $modpack->refresh();
        $modpack->mods_count = $modpack->mods()->count();
        $modpack->save();

        $this->invalidateInstancePackageCache($instance);

        $summary['removed_count'] = $removedCount;
        $summary['modpack'] = $modpack->fresh()->toArray();

        return $summary;
    }

    /**
     * Delete an installed CurseForge modpack, all its linked .jar mods, DB records, and purge zip cache.
     */
    public function deleteModpack(Instance $instance, CurseForgeModpack $modpack): void
    {
        if ((int) $modpack->instance_id !== (int) $instance->id) {
            throw new CurseForgeException("Ce modpack n'appartient pas à l'instance active.", 403);
        }

        $modsDir = $this->modsDirectory($instance);
        $linkedMods = CurseForgeMod::query()
            ->where('instance_id', $instance->id)
            ->where('modpack_id', $modpack->id)
            ->get();

        foreach ($linkedMods as $mod) {
            $baseFile = basename((string) $mod->file_name);
            $filePath = $modsDir . DIRECTORY_SEPARATOR . $baseFile;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            $this->deleteLinkedOptionalMods($instance, (int) $mod->id, $baseFile);
            $mod->delete();
        }

        $modpack->delete();
        $this->invalidateInstancePackageCache($instance);
    }

    /**
     * Update a CurseForge-tracked mod in 1 click to the latest compatible file for the instance.
     *
     * @return array<string, mixed>
     */
    public function updateMod(Instance $instance, CurseForgeMod $mod, ?int $targetFileId = null): array
    {
        if ((int) $mod->instance_id !== (int) $instance->id) {
            throw new CurseForgeException("Ce mod n'appartient pas à l'instance active.", 403);
        }

        return $this->installMod($instance, (int) $mod->curseforge_mod_id, $targetFileId);
    }

    /**
     * Delete a CurseForge-tracked mod (file + DB record) and invalidate zip package cache.
     */
    public function deleteCurseForgeMod(Instance $instance, CurseForgeMod $mod): void
    {
        if ((int) $mod->instance_id !== (int) $instance->id) {
            throw new CurseForgeException("Ce mod n'appartient pas à l'instance active.", 403);
        }

        $modsDir = $this->modsDirectory($instance);
        $baseFile = basename($mod->file_name);
        $filePath = $modsDir . DIRECTORY_SEPARATOR . $baseFile;

        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $this->deleteLinkedOptionalMods($instance, (int) $mod->id, $baseFile);
        $mod->delete();
        $this->invalidateInstancePackageCache($instance);
    }

    /**
     * Delete an external .jar mod from the instance's mods/ directory and invalidate zip package cache.
     */
    public function deleteExternalMod(Instance $instance, string $fileName): void
    {
        $cleanName = $this->sanitizeJarFilename($fileName);
        $modsDir = $this->modsDirectory($instance);
        $realModsDir = realpath($modsDir);
        $candidate = $modsDir . DIRECTORY_SEPARATOR . $cleanName;
        $realCandidate = realpath($candidate);

        if (
            $realModsDir === false
            || $realCandidate === false
            || !is_file($realCandidate)
            || !str_starts_with($realCandidate, rtrim($realModsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            throw new CurseForgeException("Fichier mod externe introuvable.", 404);
        }

        @unlink($realCandidate);
        $this->deleteLinkedOptionalMods($instance, null, $cleanName);
        $this->invalidateInstancePackageCache($instance);
    }

    /**
     * Scan all external .jar mods of an instance using official CurseForge Murmur2 fingerprints.
     *
     * @return array<string, array<string, mixed>> Keyed by file_name
     */
    public function scanExternalMods(Instance $instance): array
    {
        $installed = $this->getInstalledMods($instance);
        $externalMods = $installed['external'];
        if ($externalMods === []) {
            return [];
        }

        $modsDir = $this->modsDirectory($instance);
        $fingerprintToFiles = [];
        $fingerprints = [];

        foreach ($externalMods as $ext) {
            $fileName = (string) $ext['file_name'];
            $fullPath = $modsDir . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($fullPath)) {
                continue;
            }
            $fp = self::computeFileFingerprint($fullPath);
            $fingerprints[] = $fp;
            $fingerprintToFiles[$fp][] = $fileName;
        }

        if ($fingerprints === []) {
            return [];
        }

        $response = $this->client()->post(
            self::API_BASE_URL . '/fingerprints/' . self::GAME_ID_MINECRAFT,
            ['fingerprints' => array_values(array_unique($fingerprints))]
        );
        $payload = $this->decodeResponse($response, "Impossible d'analyser les empreintes CurseForge.");

        $exactMatches = is_array($payload['data']['exactMatches'] ?? null)
            ? $payload['data']['exactMatches']
            : [];

        $matchedModIds = [];
        foreach ($exactMatches as $match) {
            $mId = (int) ($match['id'] ?? $match['file']['modId'] ?? 0);
            if ($mId > 0) {
                $matchedModIds[] = $mId;
            }
        }
        $modsMeta = $this->fetchModsBatch(array_values(array_unique($matchedModIds)));

        $mcVersion = $this->resolveInstanceMcVersion($instance);
        $loader = $this->resolveInstanceLoader($instance);

        $results = [];
        foreach ($exactMatches as $match) {
            if (!is_array($match) || !is_array($match['file'] ?? null)) {
                continue;
            }
            $file = $match['file'];
            $fp = (int) ($file['fileFingerprint'] ?? 0);
            $modId = (int) ($match['id'] ?? $file['modId'] ?? 0);
            $fileId = (int) ($file['id'] ?? 0);

            if ($fp <= 0 || $modId <= 0 || $fileId <= 0 || empty($fingerprintToFiles[$fp])) {
                continue;
            }

            $meta = $modsMeta[$modId] ?? [];
            $logo = is_array($meta['logo'] ?? null) ? $meta['logo'] : [];
            $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');

            foreach ($fingerprintToFiles[$fp] as $localFileName) {
                $results[$localFileName] = [
                    'matched' => true,
                    'local_file_name' => $localFileName,
                    'curseforge_mod_id' => $modId,
                    'curseforge_file_id' => $fileId,
                    'name' => (string) ($meta['name'] ?? pathinfo($localFileName, PATHINFO_FILENAME)),
                    'slug' => isset($meta['slug']) ? (string) $meta['slug'] : null,
                    'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                    'version' => (string) ($file['displayName'] ?? $file['fileName'] ?? $localFileName),
                    'fingerprint' => $fp,
                    'download_url' => $this->resolveDownloadUrl($file),
                    'is_compatible' => $this->isFileCompatible($file, $mcVersion, $loader),
                ];
            }
        }

        return $results;
    }

    /**
     * Convert an external .jar mod into a tracked CurseForgeMod entry in 1 click.
     *
     * @return array<string, mixed>
     */
    public function reconcileExternalMod(Instance $instance, string $fileName, int $modId, int $fileId): array
    {
        $cleanName = $this->sanitizeJarFilename($fileName);
        $modsDir = $this->modsDirectory($instance);
        $fullPath = $modsDir . DIRECTORY_SEPARATOR . $cleanName;

        if (!is_file($fullPath)) {
            throw new CurseForgeException("Le fichier mod externe '{$cleanName}' est introuvable.", 404);
        }

        $modResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}");
        $modPayload = $this->decodeResponse($modResponse, "Impossible de récupérer le mod CurseForge #{$modId}.");
        $rawMod = is_array($modPayload['data'] ?? null) ? $modPayload['data'] : [];

        $fileResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}/files/{$fileId}");
        $filePayload = $this->decodeResponse($fileResponse, "Impossible de récupérer le fichier CurseForge #{$fileId}.");
        $rawFile = is_array($filePayload['data'] ?? null) ? $filePayload['data'] : [];

        if ($rawMod === [] || $rawFile === []) {
            throw new CurseForgeException("Métadonnées CurseForge introuvables pour la réconciliation.", 404);
        }

        $logo = is_array($rawMod['logo'] ?? null) ? $rawMod['logo'] : [];
        $logoUrl = (string) ($logo['thumbnailUrl'] ?? $logo['url'] ?? '');
        $fingerprint = self::computeFileFingerprint($fullPath);

        $record = CurseForgeMod::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'curseforge_mod_id' => $modId,
            ],
            [
                'curseforge_file_id' => $fileId,
                'name' => (string) ($rawMod['name'] ?? pathinfo($cleanName, PATHINFO_FILENAME)),
                'slug' => isset($rawMod['slug']) ? (string) $rawMod['slug'] : null,
                'logo_url' => $logoUrl !== '' ? $logoUrl : null,
                'file_name' => $cleanName,
                'version' => (string) ($rawFile['displayName'] ?? $rawFile['fileName'] ?? $cleanName),
                'mc_version' => $this->resolveInstanceMcVersion($instance),
                'loader' => $this->resolveInstanceLoader($instance),
                'fingerprint' => $fingerprint,
                'download_url' => $this->resolveDownloadUrl($rawFile),
            ]
        );

        $this->invalidateInstancePackageCache($instance);

        return $record->toArray();
    }

    /**
     * Compute the official CurseForge Murmur2 32-bit unsigned fingerprint for raw bytes.
     * Seed = 1, whitespace bytes (0x09, 0x0A, 0x0D, 0x20) are stripped before hashing.
     */
    public static function computeBytesFingerprint(string $bytes): int
    {
        $filtered = str_replace(["\x09", "\x0A", "\x0D", "\x20"], '', $bytes);
        $length = strlen($filtered);

        $seed = 1;
        $m = 0x5bd1e995;
        $h = ($seed ^ $length) & 0xFFFFFFFF;

        $fullChunks = intdiv($length, 4);
        $offset = 0;

        for ($i = 0; $i < $fullChunks; $i++) {
            $k = ord($filtered[$offset])
                | (ord($filtered[$offset + 1]) << 8)
                | (ord($filtered[$offset + 2]) << 16)
                | (ord($filtered[$offset + 3]) << 24);
            $offset += 4;

            $k = self::mul32($k, $m);
            $k = ($k ^ (($k >> 24) & 0xFF)) & 0xFFFFFFFF;
            $k = self::mul32($k, $m);

            $h = self::mul32($h, $m);
            $h = ($h ^ $k) & 0xFFFFFFFF;
        }

        $remaining = $length - $offset;
        if ($remaining === 3) {
            $h = ($h ^ (ord($filtered[$offset + 2]) << 16)) & 0xFFFFFFFF;
        }
        if ($remaining >= 2) {
            $h = ($h ^ (ord($filtered[$offset + 1]) << 8)) & 0xFFFFFFFF;
        }
        if ($remaining >= 1) {
            $h = ($h ^ ord($filtered[$offset])) & 0xFFFFFFFF;
            $h = self::mul32($h, $m);
        }

        $h = ($h ^ (($h >> 13) & 0x7FFFF)) & 0xFFFFFFFF;
        $h = self::mul32($h, $m);
        $h = ($h ^ (($h >> 15) & 0x1FFFF)) & 0xFFFFFFFF;

        return $h;
    }

    /**
     * Compute the official CurseForge Murmur2 32-bit unsigned fingerprint for a file on disk.
     */
    public static function computeFileFingerprint(string $filePath): int
    {
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            throw new CurseForgeException("Impossible de lire le fichier pour calculer l'empreinte Murmur2.", 500);
        }

        return self::computeBytesFingerprint($contents);
    }

    /**
     * Overflow-safe 32-bit unsigned multiplication.
     */
    private static function mul32(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        return ((($a & 0xFFFF) * $b) + (((($a >> 16) & 0xFFFF) * $b) << 16)) & 0xFFFFFFFF;
    }

    /**
     * Invalidate launcher file manifests and purge cached ZIP packages for the instance.
     */
    public function invalidateInstancePackageCache(Instance $instance): void
    {
        $safeScope = 'instance-' . (preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $instance->slug) ?: $instance->id);
        $pkgDir = Storage::disk('local')->path('launcher-packages/' . $safeScope);

        if (is_dir($pkgDir)) {
            foreach (glob($pkgDir . DIRECTORY_SEPARATOR . 'pkg-*.zip') ?: [] as $zipFile) {
                if (is_file($zipFile)) {
                    @unlink($zipFile);
                }
            }
        }

        LauncherCache::flush();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function assertInstanceSupportsCurseForge(Instance $instance): array
    {
        $mcVersion = $this->resolveInstanceMcVersion($instance);
        if ($mcVersion === null) {
            throw new CurseForgeException("Aucune version Minecraft n'est configurée sur cette instance.", 422);
        }

        $loader = $this->resolveInstanceLoader($instance);
        if ($loader === null) {
            $raw = (string) ($instance->loader_type ?: 'vanilla');
            throw new CurseForgeException(
                "Le loader actif ('{$raw}') n'est pas supporté par CurseForge. Configurez Forge, Fabric, Quilt ou NeoForge.",
                422
            );
        }

        return [$mcVersion, $loader];
    }

    /**
     * @param  array<string, mixed>  $rawMod
     * @return array<string, mixed>
     */
    private function resolveStrictTargetFile(
        int $modId,
        array $rawMod,
        ?int $fileId,
        string $mcVersion,
        string $loader
    ): array {
        if ($fileId !== null && $fileId > 0) {
            $fileResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}/files/{$fileId}");
            $filePayload = $this->decodeResponse($fileResponse, "Impossible de récupérer le fichier #{$fileId}.");
            $file = is_array($filePayload['data'] ?? null) ? $filePayload['data'] : [];

            if ($file === [] || !$this->isFileCompatible($file, $mcVersion, $loader)) {
                throw new CurseForgeException(
                    "Ce fichier n'est pas compatible avec l'instance active (Minecraft {$mcVersion} / " . self::LOADER_TAGS[$loader] . ').',
                    422
                );
            }

            return $file;
        }

        $compatible = $this->findCompatibleFile($rawMod, $mcVersion, $loader)
            ?? $this->fetchLatestCompatibleFile($modId, $mcVersion, $loader);

        if ($compatible === null) {
            throw new CurseForgeException(
                "Aucun fichier compatible trouvé pour Minecraft {$mcVersion} (" . self::LOADER_TAGS[$loader] . ').',
                422
            );
        }

        $resolvedFileId = (int) ($compatible['id'] ?? 0);
        $isFromIndex = !empty($compatible['_from_index']);
        $hasDownloadUrl = trim((string) ($compatible['downloadUrl'] ?? '')) !== '';

        if ($resolvedFileId > 0 && ($isFromIndex || !$hasDownloadUrl)) {
            $fileResponse = $this->client()->get(self::API_BASE_URL . "/mods/{$modId}/files/{$resolvedFileId}");
            if ($isFromIndex) {
                $filePayload = $this->decodeResponse($fileResponse, "Impossible de récupérer le fichier #{$resolvedFileId}.");
                $fullFile = is_array($filePayload['data'] ?? null) ? $filePayload['data'] : [];
                if ($fullFile !== []) {
                    if (!$this->isFileCompatible($fullFile, $mcVersion, $loader)) {
                        throw new CurseForgeException(
                            "Ce fichier n'est pas compatible avec l'instance active (Minecraft {$mcVersion} / " . self::LOADER_TAGS[$loader] . ').',
                            422
                        );
                    }

                    return $fullFile;
                }
            } elseif ($fileResponse->successful()) {
                $filePayload = $fileResponse->json();
                $fullFile = is_array($filePayload['data'] ?? null) ? $filePayload['data'] : [];
                if ($fullFile !== []) {
                    return $fullFile;
                }
            }
        }

        return $compatible;
    }

    /**
     * @param  array<int, int>  $fileIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchFilesBatch(array $fileIds): array
    {
        $map = [];
        foreach (array_chunk($fileIds, 50) as $chunk) {
            $response = $this->client()->post(self::API_BASE_URL . '/mods/files', ['fileIds' => $chunk]);
            $payload = $this->decodeResponse($response, "Impossible de résoudre les fichiers CurseForge en lot.");
            foreach (is_array($payload['data'] ?? null) ? $payload['data'] : [] as $file) {
                if (is_array($file) && isset($file['id'])) {
                    $map[(int) $file['id']] = $file;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $modIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchModsBatch(array $modIds): array
    {
        $map = [];
        foreach (array_chunk($modIds, 50) as $chunk) {
            $response = $this->client()->post(self::API_BASE_URL . '/mods', ['modIds' => $chunk]);
            $payload = $this->decodeResponse($response, "Impossible de récupérer les métadonnées des mods CurseForge.");
            foreach (is_array($payload['data'] ?? null) ? $payload['data'] : [] as $mod) {
                if (is_array($mod) && isset($mod['id'])) {
                    $map[(int) $mod['id']] = $mod;
                }
            }
        }

        return $map;
    }

    /**
     * Download a binary ZIP/JAR file via zero-RAM disk streaming (Guzzle sink) into sys_get_temp_dir(),
     * with automatic CDN fallback (edge <-> mediafilez), 2-byte ZIP/JAR "PK" signature validation,
     * and atomic move (with cross-device copy+unlink fallback).
     *
     * @param  array<string, mixed>|null  $fileMeta
     */
    private function downloadBinaryFile(string $url, string $destinationPath, ?array $fileMeta = null): void
    {
        $candidates = $this->buildDownloadCandidates($url, $fileMeta);
        $lastError = 'Téléchargement échoué.';

        foreach ($candidates as $candidateUrl) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'cf_dl_');
            if ($tmpPath === false) {
                throw new CurseForgeException("Impossible de créer un fichier temporaire dans le dossier temporaire système.", 500);
            }

            try {
                $response = Http::timeout(180)->sink($tmpPath)->get($candidateUrl);
            } catch (\Throwable $e) {
                @unlink($tmpPath);
                $lastError = "Téléchargement échoué : " . $e->getMessage();
                continue;
            }

            if (!$response->successful()) {
                @unlink($tmpPath);
                $lastError = "Téléchargement échoué (HTTP {$response->status()}).";
                continue;
            }

            clearstatcache(true, $tmpPath);
            $size = is_file($tmpPath) ? (int) (@filesize($tmpPath) ?: 0) : 0;

            // Compatibility safeguard for test fakes where sink may not populate the temp file
            if ($size === 0) {
                $fallbackBody = $response->body();
                if ($fallbackBody !== '') {
                    @file_put_contents($tmpPath, $fallbackBody);
                    clearstatcache(true, $tmpPath);
                    $size = (int) (@filesize($tmpPath) ?: 0);
                }
            }

            if ($size <= 0) {
                @unlink($tmpPath);
                $lastError = "Le fichier téléchargé depuis CurseForge est vide.";
                continue;
            }

            $fp = @fopen($tmpPath, 'rb');
            $magic = $fp !== false ? (string) @fread($fp, 2) : '';
            if ($fp !== false) {
                @fclose($fp);
            }

            if ($magic !== 'PK') {
                @unlink($tmpPath);
                $lastError = "Fichier binaire invalide ou corrompu (signature ZIP/JAR PK manquante).";
                continue;
            }

            $this->moveTempFileToDestination($tmpPath, $destinationPath);

            return;
        }

        throw new CurseForgeException($lastError, 502);
    }

    /**
     * Build ordered download URL candidates including edge.forgecdn.net <-> mediafilez.forgecdn.net fallbacks.
     *
     * @param  array<string, mixed>|null  $fileMeta
     * @return array<int, string>
     */
    private function buildDownloadCandidates(string $primaryUrl, ?array $fileMeta = null): array
    {
        $candidates = [];
        $push = static function (string $u) use (&$candidates): void {
            $clean = trim($u);
            if ($clean !== '' && !in_array($clean, $candidates, true)) {
                $candidates[] = $clean;
            }
        };

        $push($primaryUrl);

        if (str_contains($primaryUrl, 'edge.forgecdn.net')) {
            $push(str_replace('edge.forgecdn.net', 'mediafilez.forgecdn.net', $primaryUrl));
        } elseif (str_contains($primaryUrl, 'mediafilez.forgecdn.net')) {
            $push(str_replace('mediafilez.forgecdn.net', 'edge.forgecdn.net', $primaryUrl));
        }

        if ($fileMeta !== null) {
            $fileId = (int) ($fileMeta['id'] ?? 0);
            $fileName = basename((string) ($fileMeta['fileName'] ?? ''));
            if ($fileId > 0 && $fileName !== '') {
                $idStr = (string) $fileId;
                $id1 = substr($idStr, 0, 4);
                $id2 = ltrim(substr($idStr, 4), '0');
                if ($id2 === '') {
                    $id2 = '0';
                }
                $encoded = rawurlencode($fileName);
                $push(sprintf('https://mediafilez.forgecdn.net/files/%s/%s/%s', $id1, $id2, $encoded));
                $push(sprintf('https://edge.forgecdn.net/files/%s/%s/%s', $id1, $id2, $encoded));
            }
        }

        return $candidates;
    }

    private function writeFileAtomically(string $destinationPath, string $contents): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'cf_tmp_');
        if ($tmpPath === false || @file_put_contents($tmpPath, $contents) === false) {
            if ($tmpPath !== false) {
                @unlink($tmpPath);
            }
            throw new CurseForgeException("Impossible d'écrire le fichier temporaire sur le disque.", 500);
        }

        $this->moveTempFileToDestination($tmpPath, $destinationPath);
    }

    private function moveTempFileToDestination(string $tmpPath, string $destinationPath): void
    {
        $dir = dirname($destinationPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (!@rename($tmpPath, $destinationPath)) {
            if (!@copy($tmpPath, $destinationPath)) {
                @unlink($tmpPath);
                throw new CurseForgeException("Impossible de finaliser l'écriture atomique du fichier sur le disque.", 500);
            }
            @unlink($tmpPath);
        }
    }

    /**
     * Remove any residual *.jar.tmp.* files left in mods/ from previous interrupted downloads.
     */
    public function cleanupResidualTmpFiles(string $modsDir): void
    {
        if (!is_dir($modsDir)) {
            return;
        }

        foreach (glob($modsDir . DIRECTORY_SEPARATOR . '*.jar.tmp.*') ?: [] as $tmpFile) {
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    private function syncOptionalModsOnModUpdate(
        Instance $instance,
        CurseForgeMod $record,
        ?string $oldFileName,
        ?string $oldLogoUrl
    ): void {
        if (!Schema::hasTable('mods')) {
            return;
        }

        $newFileName = basename((string) $record->file_name);
        $query = OptionsMods::query()->where('instance_id', $instance->id);
        $query->where(static function ($q) use ($record, $oldFileName, $newFileName): void {
            if (Schema::hasColumn('mods', 'curseforge_mod_id')) {
                $q->where('curseforge_mod_id', $record->id);
                $q->orWhere('file', $newFileName);
            } else {
                $q->where('file', $newFileName);
            }
            if ($oldFileName !== null && $oldFileName !== '' && $oldFileName !== $newFileName) {
                $q->orWhere('file', $oldFileName);
            }
        });

        $matching = $query->get();
        foreach ($matching as $optionalMod) {
            $optionalMod->file = $newFileName;
            if (Schema::hasColumn('mods', 'curseforge_mod_id')) {
                $optionalMod->curseforge_mod_id = $record->id;
            }
            if (!empty($record->logo_url) && (empty($optionalMod->icon) || $optionalMod->icon === $oldLogoUrl)) {
                $optionalMod->icon = $record->logo_url;
            }
            $optionalMod->save();
        }
    }

    private function deleteLinkedOptionalMods(Instance $instance, ?int $curseforgeModRecordId, ?string $fileName): void
    {
        if (!Schema::hasTable('mods')) {
            return;
        }

        $cleanFile = $fileName !== null ? basename($fileName) : '';
        if ($curseforgeModRecordId === null && $cleanFile === '') {
            return;
        }

        $hasFkCol = Schema::hasColumn('mods', 'curseforge_mod_id');

        OptionsMods::query()
            ->where('instance_id', $instance->id)
            ->where(static function ($q) use ($curseforgeModRecordId, $cleanFile, $hasFkCol): void {
                $added = false;
                if ($hasFkCol && $curseforgeModRecordId !== null) {
                    $q->where('curseforge_mod_id', $curseforgeModRecordId);
                    $added = true;
                }
                if ($cleanFile !== '') {
                    if ($added) {
                        $q->orWhere('file', $cleanFile);
                    } else {
                        $q->where('file', $cleanFile);
                    }
                }
            })
            ->delete();
    }

    private function sanitizeJarFilename(string $fileName): string
    {
        $base = basename(str_replace('\\', '/', trim($fileName)));
        if ($base === '' || $base === '.' || $base === '..' || !str_ends_with(strtolower($base), '.jar')) {
            throw new CurseForgeException("Nom de fichier .jar invalide : '{$fileName}'.", 422);
        }

        return $base;
    }

    private function modsDirectory(Instance $instance): string
    {
        return rtrim($instance->resolvedStoragePath(), '/\\') . DIRECTORY_SEPARATOR . 'mods';
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function formatFileSummary(array $file): array
    {
        return [
            'id' => (int) ($file['id'] ?? 0),
            'file_name' => (string) ($file['fileName'] ?? ''),
            'display_name' => (string) ($file['displayName'] ?? $file['fileName'] ?? ''),
            'file_date' => isset($file['fileDate']) ? (string) $file['fileDate'] : null,
            'file_length' => (int) ($file['fileLength'] ?? 0),
            'game_versions' => is_array($file['gameVersions'] ?? null) ? array_values($file['gameVersions']) : [],
        ];
    }

    private function client(): PendingRequest
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            throw new CurseForgeException(
                "Clé API CurseForge non configurée. Veuillez renseigner votre clé API CurseForge dans la configuration.",
                400
            );
        }

        return Http::timeout(25)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(\Illuminate\Http\Client\Response $response, string $defaultMessage): array
    {
        if ($response->status() === 403 || $response->status() === 401) {
            $detail = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response->body())) ?? '');
            $snippet = $detail !== '' ? ' — ' . mb_substr($detail, 0, 160) : '';
            throw new CurseForgeException("Clé API CurseForge invalide ou refusée (HTTP {$response->status()}){$snippet}.", 401);
        }

        if (!$response->successful()) {
            throw new CurseForgeException("{$defaultMessage} (HTTP {$response->status()}).", 502);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
