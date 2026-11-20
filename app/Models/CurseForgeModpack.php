<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurseForgeModpack extends Model
{
    use HasFactory;

    protected $table = 'curseforge_modpacks';

    protected $fillable = [
        'instance_id',
        'curseforge_modpack_id',
        'curseforge_file_id',
        'name',
        'slug',
        'logo_url',
        'file_name',
        'version',
        'mc_version',
        'loader',
        'mods_count',
        'download_url',
    ];

    protected $casts = [
        'instance_id' => 'integer',
        'curseforge_modpack_id' => 'integer',
        'curseforge_file_id' => 'integer',
        'mods_count' => 'integer',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_id');
    }

    public function mods(): HasMany
    {
        return $this->hasMany(CurseForgeMod::class, 'modpack_id');
    }
}
