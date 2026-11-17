<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanelRole extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'panel_roles';

    protected $fillable = [
        'name',
        'color',
        'background_path',
        'power',
        'is_default',
        'bypass_maintenance',
    ];

    protected $casts = [
        'power' => 'integer',
        'is_default' => 'boolean',
        'bypass_maintenance' => 'boolean',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(MicrosoftPlayer::class, 'role_id');
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_path ? asset('storage/' . ltrim($this->background_path, '/')) : null;
    }
}
