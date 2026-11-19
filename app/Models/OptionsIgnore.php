<?php

namespace App\Models;

use App\Support\LauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsIgnore extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(static fn () => LauncherCache::flush());
        static::deleted(static fn () => LauncherCache::flush());
    }

    protected $table = 'ignored_folders';
    protected $fillable = ['instance_id', 'folder_name'];

    protected $casts = [
        'instance_id' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}

