<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Board;
use Eavio\ProjectBoard\Models\BoardColumn;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ColumnController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $column = $board->columns()->create([
            'name' => $data['name'],
            'slug' => str()->slug($data['name']),
            'order_column' => $board->columns()->max('order_column') + 1,
        ]);

        return $column;
    }

    public function update(Request $request, BoardColumn $column)
    {
         $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        
        $column->update($data);
        
        return $column;
    }

    public function reorder(Request $request, BoardColumn $column)
    {
        $request->validate([
            'order' => 'required|integer',
        ]);
        
        // Simplified reorder logic - in production you'd use a package like spatie/eloquent-sortable
        // or manually shift other columns. For now, we'll just update the index.
        
        $column->update(['order_column' => $request->order]);
        
        return $column;
    }

    public function archiveCards(BoardColumn $column)
    {
        foreach ($column->cards as $card) {
            if ($card->trashed()) {
                continue;
            }

            $card->delete();

            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'archived',
                'text' => 'archived this card (bulk column archive)',
            ]);
        }

        return response()->noContent();
    }

    public function destroy(BoardColumn $column)
    {
        $column->delete();

        return response()->noContent();
    }

    public function restore($columnId)
    {
        $column = BoardColumn::onlyTrashed()->findOrFail($columnId);
        $column->restore();

        return response()->noContent();
    }

    public function forceDelete($columnId)
    {
        $column = BoardColumn::onlyTrashed()->findOrFail($columnId);
        $column->forceDelete();

        return response()->noContent();
    }
}
