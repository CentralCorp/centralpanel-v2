<?php

namespace App\Providers;

use Alexusmai\LaravelFileManager\Events\Deleted as FileManagerDeleted;
use Alexusmai\LaravelFileManager\Events\Rename as FileManagerRename;
use App\Models\CurseForgeMod;
use App\Models\OptionsMods;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelUpdateCacheGuard;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            PanelUpdateCacheGuard::ensureFreshForCurrentVersion();
        }

        Event::listen(FileManagerDeleted::class, function (FileManagerDeleted $event): void {
            $this->handleFileManagerDeleted($event);
        });

        Event::listen(FileManagerRename::class, function (FileManagerRename $event): void {
            $this->handleFileManagerRename($event);
        });
    }

    private function handleFileManagerDeleted(FileManagerDeleted $event): void
    {
        if (!Schema::hasTable('instances') || !Schema::hasTable('mods')) {
            return;
        }

        $items = $event->items();
        if (!is_array($items) || $items === []) {
            return;
        }

        $instance = ActiveInstance::current();
        $deletedModFiles = [];
        $deletedEntireModsDir = false;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = trim(str_replace('\\', '/', (string) ($item['path'] ?? '')), '/');
            $type = (string) ($item['type'] ?? 'file');

            if ($path === 'mods' && $type === 'dir') {
                $deletedEntireModsDir = true;
                break;
            }

            if (str_starts_with($path, 'mods/')) {
                $rel = substr($path, 5);
                if ($rel !== '' && !str_contains($rel, '/')) {
                    $normalized = preg_replace('/\.jar-disable$/', '.jar', $rel);
                    $deletedModFiles[] = $rel;
                    if ($normalized !== $rel) {
                        $deletedModFiles[] = $normalized;
                    }
                }
            }
        }

        if ($deletedEntireModsDir) {
            OptionsMods::query()->where('instance_id', $instance->id)->delete();
            if (Schema::hasTable('curseforge_mods')) {
                CurseForgeMod::query()->where('instance_id', $instance->id)->delete();
            }
            LauncherCache::flush();

            return;
        }

        $deletedModFiles = array_values(array_unique($deletedModFiles));
        if ($deletedModFiles === []) {
            return;
        }

        OptionsMods::query()
            ->where('instance_id', $instance->id)
            ->whereIn('file', $deletedModFiles)
            ->delete();

        if (Schema::hasTable('curseforge_mods')) {
            CurseForgeMod::query()
                ->where('instance_id', $instance->id)
                ->whereIn('file_name', $deletedModFiles)
                ->delete();
        }

        LauncherCache::flush();
    }

    private function handleFileManagerRename(FileManagerRename $event): void
    {
        if (!Schema::hasTable('instances') || !Schema::hasTable('mods')) {
            return;
        }

        $oldPath = trim(str_replace('\\', '/', (string) $event->oldName()), '/');
        $newPath = trim(str_replace('\\', '/', (string) $event->newName()), '/');

        if (!str_starts_with($oldPath, 'mods/')) {
            return;
        }

        $oldFile = basename($oldPath);
        $oldNormalized = preg_replace('/\.jar-disable$/', '.jar', $oldFile);
        $instance = ActiveInstance::current();

        $isStillInMods = str_starts_with($newPath, 'mods/') && !str_contains(substr($newPath, 5), '/');
        $newFile = $isStillInMods ? basename($newPath) : '';
        $newNormalized = $newFile !== '' ? preg_replace('/\.jar-disable$/', '.jar', $newFile) : '';

        if ($isStillInMods && str_ends_with(strtolower($newNormalized), '.jar')) {
            OptionsMods::query()
                ->where('instance_id', $instance->id)
                ->whereIn('file', array_values(array_unique([$oldFile, $oldNormalized])))
                ->update(['file' => $newNormalized]);

            if (Schema::hasTable('curseforge_mods')) {
                CurseForgeMod::query()
                    ->where('instance_id', $instance->id)
                    ->whereIn('file_name', array_values(array_unique([$oldFile, $oldNormalized])))
                    ->update(['file_name' => $newNormalized]);
            }
        } else {
            OptionsMods::query()
                ->where('instance_id', $instance->id)
                ->whereIn('file', array_values(array_unique([$oldFile, $oldNormalized])))
                ->delete();

            if (Schema::hasTable('curseforge_mods')) {
                CurseForgeMod::query()
                    ->where('instance_id', $instance->id)
                    ->whereIn('file_name', array_values(array_unique([$oldFile, $oldNormalized])))
                    ->delete();
            }
        }

        LauncherCache::flush();
    }
}
