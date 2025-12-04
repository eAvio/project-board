<?php

namespace Eavio\ProjectBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('project-board.user_model', \App\Models\User::class));
    }

    /**
     * Generate a new token for a user.
     */
    public static function createToken($user, string $name, array $abilities = ['*'], ?\DateTime $expiresAt = null): array
    {
        $plainToken = Str::random(64);
        
        $token = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Find token by plain text token.
     */
    public static function findByToken(string $plainToken): ?self
    {
        return static::where('token_hash', hash('sha256', $plainToken))->first();
    }

    /**
     * Check if token is valid (not expired).
     */
    public function isValid(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Check if token has a specific ability.
     */
    public function hasAbility(string $ability): bool
    {
        if (in_array('*', $this->abilities ?? [])) {
            return true;
        }
        return in_array($ability, $this->abilities ?? []);
    }

    /**
     * Update last used timestamp.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
