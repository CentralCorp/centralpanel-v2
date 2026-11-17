<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'news';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'author',
        'image_path',
        'image_url',
        'badge',
        'external_url',
        'is_pinned',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return asset('storage/' . ltrim($this->image_path, '/'));
        }

        return $this->image_url ?: null;
    }
}
