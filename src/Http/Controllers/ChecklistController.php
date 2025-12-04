<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Checklist;
use Eavio\ProjectBoard\Models\ChecklistItem;
use Eavio\ProjectBoard\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChecklistController extends Controller
{
    public function index(Card $card)
    {
        return $card->checklists;
    }

    public function store(Request $request, Card $card)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $checklist = $card->checklists()->create($data);

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'updated',
            'text' => "added checklist {$checklist->name}",
        ]);

        return $checklist->load('items');
    }

    public function destroy(Checklist $checklist)
    {
        $checklist->delete();
        
        Activity::create([
            'card_id' => $checklist->card_id,
            'user_id' => auth()->id(),
            'type' => 'updated',
            'text' => "removed checklist {$checklist->name}",
        ]);

        return response()->noContent();
    }

    public function storeItem(Request $request, Checklist $checklist)
    {
        $data = $request->validate([
            'content' => 'required|string|max:255',
        ]);

        $data['position'] = $checklist->items()->max('position') + 1;

        return $checklist->items()->create($data);
    }

    public function updateItem(Request $request, ChecklistItem $item)
    {
        $data = $request->validate([
            'content' => 'sometimes|string|max:255',
            'is_completed' => 'sometimes|boolean',
        ]);

        $item->update($data);

        if (isset($data['is_completed'])) {
            $card = $item->checklist->card;
            $status = $data['is_completed'] ? 'completed' : 'uncompleted';
            $itemContent = $item->content;
            
            // Optional: Log activity for item completion? Might be too spammy.
        }

        return $item;
    }

    public function destroyItem(ChecklistItem $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
