<?php
namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Model;

class OptionsLoader extends Model
{
    use FlushesLauncherCache;

    protected $table = 'options_loader';

    protected $fillable = [
        'minecraft_version',
        'loader_activation',
        'loader_type',
        'loader_forge_version',
        'loader_build_version',
    ];

    protected $casts = [
        'loader_activation' => 'boolean',
    ];
}
