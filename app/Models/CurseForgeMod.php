<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurseForgeMod extends Model
{
    use HasFactory;

    protected $table = 'curseforge_mods';

    protected $fillable = [
        'instance_id',
        'curseforge_mod_id',
        'curseforge_file_id',
        'name',
        'slug',
        'logo_url',
        'file_name',
        'version',
        'mc_version',
        'loader',
        'fingerprint',
        'download_url',
    ];

    protected $casts = [
        'instance_id' => 'integer',
        'curseforge_mod_id' => 'integer',
        'curseforge_file_id' => 'integer',
        'fingerprint' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }
}
