<?php

namespace Eavio\ProjectBoard\Traits;

use Eavio\ProjectBoard\Models\UserApiToken;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasApiTokens
{
    /**
     * Get all API tokens for the user.
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(UserApiToken::class);
    }

    /**
     * Create a new API token for the user.
     */
    public function createApiToken(string $name, array $abilities = ['*'], ?\DateTime $expiresAt = null): array
    {
        return UserApiToken::createToken($this, $name, $abilities, $expiresAt);
    }
}
