<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsMods extends Model
{
    use FlushesLauncherCache;

    protected $table = 'mods';

    protected $fillable = ['instance_id', 'file', 'name', 'description', 'icon', 'optional', 'recommended'];

    protected $casts = [
        'instance_id' => 'integer',
        'optional' => 'boolean',
        'recommended' => 'boolean',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}
