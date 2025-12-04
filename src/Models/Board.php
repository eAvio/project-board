<?php

namespace Eavio\ProjectBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Board extends Model
{
    protected $guarded = [];

    /**
     * Get the parent boardable model (e.g. Company, Project).
     */
    public function boardable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the columns for the board.
     */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('order_column');
    }

    /**
     * Get users with access to this board.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('project-board.user_model'), 'board_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all cards across all columns.
     */
    public function cards(): HasMany
    {
        return $this->hasManyThrough(
            Card::class,
            BoardColumn::class,
            'board_id',
            'board_column_id'
        );
    }

    /**
     * Scope to boards accessible by a user.
     */
    public function scopeAccessibleBy($query, $user)
    {
        // Safe check for super-admin/full access capability
        $hasAccess = method_exists($user, 'hasFullBoardAccess') 
            ? $user->hasFullBoardAccess() 
            : ($user->can('access all boards') ?? false);

        if ($hasAccess) {
            return $query;
        }
        
        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }
}
