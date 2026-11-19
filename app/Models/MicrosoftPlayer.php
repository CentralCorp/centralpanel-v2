<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrosoftPlayer extends Model
{
    use HasFactory;

    protected $table = 'microsoft_players';

    protected $fillable = [
        'uuid',
        'username',
        'xuid',
        'role_id',
        'money',
        'is_banned',
        'ban_reason',
        'skin_url',
        'cape_url',
        'last_seen_at',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'money' => 'integer',
        'is_banned' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(PanelRole::class, 'role_id');
    }

    public static function normalizeUuid(?string $uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $clean = strtolower(str_replace('-', '', trim($uuid)));

        return $clean !== '' ? $clean : null;
    }
}
