<?php
namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsWhitelistRole extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'whitelist_roles';
    protected $fillable = ['instance_id', 'role'];

    protected $casts = [
        'instance_id' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}

