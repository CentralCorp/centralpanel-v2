<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionsServer extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'options_server';
    protected $fillable = [
        'server_id',
        'server_name',
        'server_ip',
        'server_port',
        'icon',
        'icon_local',
        'type',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(Instance::class, 'server_id');
    }

    /**
     * Retourne l'URL de l'icône (locale prioritaire sur distante)
     */
    public function getIconUrlAttribute(): ?string
    {
        return $this->iconUrlFor();
    }

    public function iconUrlFor(?string $baseUrl = null, ?string $azuriomUrl = null, bool $azuriomUrlResolved = false): ?string
    {
        if ($this->icon_local) {
            $rel = 'storage/' . ltrim($this->icon_local, '/');

            return $baseUrl ? rtrim($baseUrl, '/') . '/' . $rel : asset($rel);
        }

        if ($this->icon) {
            $resolvedAzuriomUrl = $azuriomUrlResolved
                ? $azuriomUrl
                : ($azuriomUrl ?? OptionsGeneral::value('azuriom_url'));

            if ($resolvedAzuriomUrl) {
                return rtrim($resolvedAzuriomUrl, '/') . '/storage/' . ltrim(str_replace('storage/', '', $this->icon), '/');
            }
        }

        return null;
    }
}
