<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Model;

class OptionsBg extends Model
{
    use FlushesLauncherCache;

    protected $fillable = [
        'role_id',
        'image_path',
        'role_name'
    ];
} 