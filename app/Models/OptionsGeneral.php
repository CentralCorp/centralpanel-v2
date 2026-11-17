<?php

namespace App\Models;

use App\Models\Concerns\FlushesLauncherCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionsGeneral extends Model
{
    use HasFactory;
    use FlushesLauncherCache;

    protected $table = 'options_general';

    protected $fillable = [
        'mods_enabled',
        'file_verification',
        'embedded_java',
        'game_folder_name',
        'email_verified',
        'auth_mode',
        'role_display',
        'money_display',
        'azuriom_url',
        'azuriom_api_key',
        'microsoft_client_id',
        'news_source',
        'min_ram',
        'max_ram',
    ];

    protected $casts = [
        'mods_enabled' => 'boolean',
        'file_verification' => 'boolean',
        'embedded_java' => 'boolean',
        'email_verified' => 'boolean',
        'role_display' => 'boolean',
        'money_display' => 'boolean',
        'min_ram' => 'integer',
        'max_ram' => 'integer',
    ];
}
