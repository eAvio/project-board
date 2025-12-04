<?php

namespace Eavio\ProjectBoard\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReaction extends Model
{
    protected $guarded = [];

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function user()
    {
        return $this->belongsTo(config('project-board.user_model'));
    }
}
