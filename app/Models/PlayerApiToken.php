<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerApiToken extends Model
{
    use HasFactory;

    public const ABILITY_BAN = 'players:ban';
    public const ABILITY_UNBAN = 'players:unban';
    public const ABILITY_ROLE = 'players:role';
    public const ABILITY_POINTS = 'players:points';

    public const ALL_ABILITIES = [
        self::ABILITY_BAN,
        self::ABILITY_UNBAN,
        self::ABILITY_ROLE,
        self::ABILITY_POINTS,
    ];

    protected $table = 'player_api_tokens';

    protected $fillable = [
        'name',
        'token_hash',
        'token_prefix',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_by',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities;
        if (!is_array($abilities)) {
            return false;
        }

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Generate and persist a new Bearer API token.
     *
     * @param  array<int, string>  $abilities
     * @return array{token: self, plain_text: string}
     */
    public static function generateToken(
        string $name,
        array $abilities = self::ALL_ABILITIES,
        ?DateTimeInterface $expiresAt = null,
        ?int $createdBy = null
    ): array {
        $plainText = 'cc_pat_' . bin2hex(random_bytes(24));
        $normalizedAbilities = array_values(array_unique(array_filter(
            $abilities,
            fn ($ability) => is_string($ability) && ($ability === '*' || in_array($ability, self::ALL_ABILITIES, true))
        )));

        $token = self::create([
            'name' => trim($name),
            'token_hash' => hash('sha256', $plainText),
            'token_prefix' => substr($plainText, 0, 12),
            'abilities' => $normalizedAbilities,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        return [
            'token' => $token,
            'plain_text' => $plainText,
        ];
    }

    public static function findByPlainText(string $plainText): ?self
    {
        $trimmed = trim($plainText);
        if ($trimmed === '') {
            return null;
        }

        return self::where('token_hash', hash('sha256', $trimmed))->first();
    }
}
