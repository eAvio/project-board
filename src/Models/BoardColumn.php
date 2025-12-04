<?php

namespace Eavio\ProjectBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardColumn extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Get the board that owns the column.
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the cards for the column (cards where this is their home column).
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('order_column');
    }

    /**
     * Get mirrored cards (cards that appear here but belong to another column).
     */
    public function mirroredCards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_appearances', 'board_column_id', 'card_id')
            ->withTimestamps()
            ->withPivot(['order_column', 'created_by']);
    }

    /**
     * Get all cards in this column (home + mirrored).
     */
    public function allCards()
    {
        $homeCards = $this->cards()->get()->map(function ($card) {
            $card->is_mirror = false;
            $card->mirror_order = $card->order_column;
            return $card;
        });

        $mirroredCards = $this->mirroredCards()->get()->map(function ($card) {
            $card->is_mirror = true;
            $card->mirror_order = $card->pivot->order_column;
            $card->home_board_name = $card->column?->board?->name;
            $card->home_column_name = $card->column?->name;
            return $card;
        });

        return $homeCards->merge($mirroredCards)->sortBy('mirror_order')->values();
    }
}
