<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsSecurity extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'options_security';
    protected $fillable = [
        'maintenance',
        'maintenance_message',
        'maintenance_bypass_roles',
        'maintenance_bypass_users',
        'whitelist',
        'player_api_enabled',
    ];

    protected $casts = [
        'maintenance' => 'boolean',
        'maintenance_bypass_roles' => 'array',
        'maintenance_bypass_users' => 'array',
        'whitelist' => 'boolean',
        'player_api_enabled' => 'boolean',
    ];
}
