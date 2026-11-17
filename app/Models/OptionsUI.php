<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsUI extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'options_ui';
    protected $fillable = [
        'alert_activation',
        'alert_scroll',
        'alert_msg',
        'video_activation',
        'video_url',
        'splash',
        'splash_author',
        'accent_color',
    ];

    protected $casts = [
        'alert_activation' => 'boolean',
        'alert_scroll' => 'boolean',
        'video_activation' => 'boolean',
    ];
}
