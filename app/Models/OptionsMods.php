<?php

namespace App\Models;

use App\Support\LauncherCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsMods extends Model
{
    protected static function booted(): void
    {
        static::saved(static fn () => LauncherCache::flush());
        static::deleted(static fn () => LauncherCache::flush());
    }

    protected $table = 'mods';

    protected $fillable = ['instance_id', 'curseforge_mod_id', 'file', 'name', 'description', 'icon', 'optional', 'recommended'];

    protected $casts = [
        'instance_id' => 'integer',
        'curseforge_mod_id' => 'integer',
        'optional' => 'boolean',
        'recommended' => 'boolean',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }

    public function curseforgeMod(): BelongsTo
    {
        return $this->belongsTo(CurseForgeMod::class, 'curseforge_mod_id');
    }
}
