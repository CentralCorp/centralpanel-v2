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
        'banned_until',
        'skin_url',
        'cape_url',
        'last_seen_at',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'money' => 'integer',
        'is_banned' => 'boolean',
        'banned_until' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(PanelRole::class, 'role_id');
    }

    public function isCurrentlyBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }

        if ($this->banned_until !== null && $this->banned_until->isPast()) {
            return false;
        }

        return true;
    }

    public static function normalizeUuid(?string $uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $clean = strtolower(str_replace('-', '', trim($uuid)));

        return $clean !== '' ? $clean : null;
    }

    public static function resolveByIdentifier(string $identifier): ?self
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            return null;
        }

        $normalizedUuid = self::normalizeUuid($trimmed);
        if ($normalizedUuid !== null && preg_match('/^[0-9a-f]{32}$/', $normalizedUuid) === 1) {
            $byUuid = self::where('uuid', $normalizedUuid)->first();
            if ($byUuid) {
                return $byUuid;
            }
        }

        return self::whereRaw('LOWER(username) = ?', [strtolower($trimmed)])->first();
    }
}
