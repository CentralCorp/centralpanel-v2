<?php

namespace App\Support;

use App\Models\Instance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

final class ActiveInstance
{
    public const SESSION_KEY = 'active_instance_id';

    public static function current(): Instance
    {
        $activeId = session(self::SESSION_KEY);
        $instance = $activeId ? Instance::find($activeId) : null;

        if (!$instance) {
            $instance = PanelOptions::defaultInstance();
            session([self::SESSION_KEY => $instance->id]);
        } else {
            $instance->ensureDirectoriesExist();
        }

        return $instance;
    }

    public static function set(Instance|int $instance): void
    {
        $id = $instance instanceof Instance ? $instance->id : $instance;
        session([self::SESSION_KEY => $id]);
    }

    public static function all(): Collection
    {
        if (!Schema::hasTable('instances')) {
            return new Collection();
        }

        if (Instance::count() === 0) {
            PanelOptions::defaultInstance();
        }

        return Instance::query()
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }
}
