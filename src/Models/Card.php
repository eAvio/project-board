<?php

namespace Eavio\ProjectBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Card extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    /**
     * Get the column that owns the card.
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    /**
     * Get the user who created the card.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('project-board.user_model'), 'created_by');
    }

    /**
     * The users assigned to the card.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(config('project-board.user_model'), 'card_user');
    }

    /**
     * The labels assigned to the card.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label');
    }

    /**
     * Get all of the card's activities.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all of the card's comments.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at', 'desc');
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class)->with('items');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('public');
        $this->addMediaCollection('featured_image')->useDisk('public')->singleFile();
    }

    /**
     * Get additional columns where this card appears (mirrors).
     * The card's "home" column is in board_column_id.
     */
    public function appearances(): BelongsToMany
    {
        return $this->belongsToMany(BoardColumn::class, 'card_appearances', 'card_id', 'board_column_id')
            ->withTimestamps()
            ->withPivot(['order_column', 'created_by']);
    }

    /**
     * Check if this card is a mirror (appearance) in a specific column.
     */
    public function isMirrorIn(BoardColumn $column): bool
    {
        return $this->appearances()->where('board_column_id', $column->id)->exists();
    }

    /**
     * Get all columns where this card appears (home + mirrors).
     */
    public function allColumns()
    {
        $columns = collect([$this->column]);
        return $columns->merge($this->appearances)->unique('id');
    }
}
