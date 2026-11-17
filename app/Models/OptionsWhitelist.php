<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsWhitelist extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'whitelist';
    protected $fillable = ['instance_id', 'users'];

    protected $casts = [
        'instance_id' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}

