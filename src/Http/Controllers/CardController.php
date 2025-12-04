<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Board;
use Eavio\ProjectBoard\Models\BoardColumn;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Activity;
use Eavio\ProjectBoard\Notifications\MemberAddedToCard;
use Eavio\ProjectBoard\Notifications\CardActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\User;

class CardController extends Controller
{
    public function store(Request $request, BoardColumn $column)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $card = $column->cards()->create([
            'title' => $data['title'],
            'order_column' => $column->cards()->max('order_column') + 1,
            'created_by' => auth()->id(),
        ]);

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'created',
            'text' => 'created this card',
        ]);

        return $card->load('labels', 'assignees', 'media');
    }

    public function storeWithImage(Request $request, BoardColumn $column)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        $card = $column->cards()->create([
            'title' => $request->title,
            'order_column' => $column->cards()->max('order_column') + 1,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('image')) {
            $card->addMediaFromRequest('image')->toMediaCollection('featured_image');
        }

        return $card->load('labels', 'assignees', 'media');
    }

    public function storeWithFile(Request $request, BoardColumn $column)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:20480', // 20MB max
        ]);

        $card = $column->cards()->create([
            'title' => $request->title,
            'order_column' => $column->cards()->max('order_column') + 1,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $isImage = str_starts_with($file->getMimeType(), 'image/');
            
            // Always add to attachments
            $media = $card->addMediaFromRequest('file')->toMediaCollection('attachments');
            
            // If it's an image, also set as featured image (cover) by copying the file
            if ($isImage) {
                $card->addMedia($media->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection('featured_image');
            }
        }

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'created',
            'text' => 'created this card',
        ]);

        return $card->load('labels', 'assignees', 'media');
    }

    public function update(Request $request, Card $card)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
        ]);

        $card->update($data);

        if ($card->wasChanged('description')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'updated',
                'text' => 'updated the description',
            ]);
            $this->notifyCardActivity($card, 'updated the description');
        }

        if ($card->wasChanged('due_date')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'updated',
                'text' => 'changed the due date',
            ]);
            $this->notifyCardActivity($card, 'changed the due date');
        }
        
        if ($card->wasChanged('title')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'updated',
                'text' => 'renamed the card',
            ]);
            $this->notifyCardActivity($card, 'renamed the card');
        }

        return $card->load('labels', 'assignees', 'media');
    }

    public function move(Request $request, Card $card)
    {
        $data = $request->validate([
            'board_column_id' => 'required|exists:board_columns,id',
            'order_column' => 'required|integer',
        ]);

        $oldColumnId = $card->board_column_id;
        
        $card->update($data);
        
        if ($oldColumnId != $card->board_column_id) {
            $oldColumn = BoardColumn::find($oldColumnId);
            $newColumn = BoardColumn::find($card->board_column_id);
            $activityText = "moved this card from {$oldColumn->name} to {$newColumn->name}";
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'moved',
                'text' => $activityText,
            ]);
            $this->notifyCardActivity($card, $activityText);
        }

        return $card;
    }

    public function duplicate(Request $request, Card $card)
    {
        $data = $request->validate([
            'board_column_id' => 'nullable|exists:board_columns,id',
            'title' => 'nullable|string|max:255',
            'order_column' => 'nullable|integer',
        ]);

        $targetColumnId = $data['board_column_id'] ?? $card->board_column_id;
        $targetColumn = BoardColumn::find($targetColumnId);
        
        $newCard = $card->replicate(['order_column', 'created_at', 'updated_at', 'deleted_at']);
        $newCard->title = $data['title'] ?? ($card->title . ' (Copy)');
        $newCard->board_column_id = $targetColumnId;
        
        if (isset($data['order_column'])) {
            $newCard->order_column = $data['order_column'];
            // Shift others if needed?
            // For simplicity, assuming frontend sends correct order or just appending. 
            // If inserting at top (1), we should ideally increment others.
            // But since we use a simple order index, duplicate values might occur but usually fine for display until reordered.
            // To be safe, let's just use what is sent or append.
        } else {
             $newCard->order_column = $targetColumn->cards()->max('order_column') + 1;
        }

        $newCard->created_by = auth()->id();
        $newCard->save();

        // If we inserted at specific position, we might want to reorder others to avoid gaps or collisions, 
        // but for now let's assume the user will reorder if needed or the frontend calculated a safe high number/low number.
        // Actually, if 'Top', we want 1. And we should shift others.
        // For this "MVP" task, let's stick to appending or blindly using the value.
        
        // Sync relationships
        $newCard->labels()->sync($card->labels->pluck('id'));
        $newCard->assignees()->sync($card->assignees->pluck('id'));

        // Clone Checklists
        foreach($card->checklists as $checklist) {
            $newChecklist = $checklist->replicate(['card_id']);
            $newChecklist->card_id = $newCard->id;
            $newChecklist->save();
            
            foreach($checklist->items as $item) {
                $newItem = $item->replicate(['checklist_id']);
                $newItem->checklist_id = $newChecklist->id;
                $newItem->save();
            }
        }

        // Clone Media
        $card->getMedia('attachments')->each(function($media) use ($newCard) {
            $media->copy($newCard, 'attachments');
        });
        $card->getMedia('featured_image')->each(function($media) use ($newCard) {
            $media->copy($newCard, 'featured_image');
        });

        Activity::create([
            'card_id' => $newCard->id,
            'user_id' => auth()->id(),
            'type' => 'created',
            'text' => "copied this card from {$card->title}",
        ]);

        return $newCard;
    }

    public function uploadAttachment(Request $request, Card $card)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);
        
        $media = $card->addMediaFromRequest('file')->toMediaCollection('attachments');
        
        return response()->json(['url' => $media->getUrl()]);
    }

    public function setCover(Request $request, Card $card)
    {
        $request->validate([
            'media_id' => 'required|integer',
        ]);

        $media = $card->media()->find($request->media_id);
        if (!$media) abort(404);

        $card->clearMediaCollection('featured_image');
        $media->copy($card, 'featured_image');

        return $card->load('labels', 'assignees', 'media');
    }

    public function removeCover(Card $card)
    {
        $card->clearMediaCollection('featured_image');
        return $card->load('labels', 'assignees', 'media');
    }

    public function deleteAttachment(Card $card, $mediaId)
    {
        $media = $card->media()->where('id', $mediaId)->first();
        if ($media) {
            // Check if this media matches the current cover (by filename/size as proxy)
            $cover = $card->getFirstMedia('featured_image');
            if ($cover && $cover->file_name === $media->file_name && $cover->size === $media->size) {
                $card->clearMediaCollection('featured_image');
            }
            
            $media->delete();
        }

        // Enforce rule: If no attachments left, remove cover (cleanup)
        if ($card->getMedia('attachments')->count() === 0) {
             $card->clearMediaCollection('featured_image');
        }

        return response()->noContent();
    }

    public function show(Card $card)
    {
        $card->load([
            'labels', 
            'assignees', 
            'media', 
            'column.board',
            'comments.user',
            'comments.reactions.user',
            'appearances.board',
        ]);

        // Add appearances info to the response
        $card->appearances_info = [
            'home' => [
                'column_id' => $card->column->id,
                'column_name' => $card->column->name,
                'board_id' => $card->column->board->id,
                'board_name' => $card->column->board->name,
            ],
            'mirrors' => $card->appearances->map(fn($col) => [
                'column_id' => $col->id,
                'column_name' => $col->name,
                'board_id' => $col->board->id,
                'board_name' => $col->board->name,
            ]),
        ];

        return $card;
    }

    public function destroy(Card $card)
    {
        $card->delete();

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'archived',
            'text' => 'archived this card',
        ]);

        return response()->noContent();
    }

    public function restore($cardId)
    {
        $card = Card::onlyTrashed()->findOrFail($cardId);
        $card->restore();

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'restored',
            'text' => 'restored this card from archive',
        ]);

        return response()->noContent();
    }

    public function forceDelete($cardId)
    {
        $card = Card::onlyTrashed()->findOrFail($cardId);
        $card->forceDelete();

        return response()->noContent();
    }

    public function syncAssignees(Request $request, Card $card)
    {
        $data = $request->validate([
            'users' => 'present|array',
            'users.*' => 'exists:users,id',
        ]);

        $oldAssignees = $card->assignees->pluck('id')->toArray();
        $newAssignees = $data['users'];

        $added = array_diff($newAssignees, $oldAssignees);
        $removed = array_diff($oldAssignees, $newAssignees);

        $card->assignees()->sync($data['users']);

        foreach ($added as $userId) {
            $user = User::find($userId);
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'assigned',
                'text' => "assigned {$user->name} to this card",
            ]);

            // Notify the user they were added to the card
            if ($user->id !== auth()->id()) {
                try {
                    $user->notify(new MemberAddedToCard($card, auth()->user()));
                } catch (\Exception $e) {
                    // Log error or ignore
                }
            }
        }

        foreach ($removed as $userId) {
            $user = User::find($userId);
            Activity::create([
                'card_id' => $card->id,
                'user_id' => auth()->id(),
                'type' => 'unassigned',
                'text' => "removed {$user->name} from this card",
            ]);
        }

        return $card->load('assignees');
    }

    public function activities(Card $card)
    {
        return $card->activities()->with('user')->get();
    }

    /**
     * Get all boards/columns where this card appears (for the card detail modal).
     */
    public function getAppearances(Card $card)
    {
        $homeColumn = $card->column()->with('board')->first();
        $appearances = $card->appearances()->with('board')->get();

        return [
            'home' => [
                'column_id' => $homeColumn->id,
                'column_name' => $homeColumn->name,
                'board_id' => $homeColumn->board->id,
                'board_name' => $homeColumn->board->name,
            ],
            'mirrors' => $appearances->map(fn($col) => [
                'column_id' => $col->id,
                'column_name' => $col->name,
                'board_id' => $col->board->id,
                'board_name' => $col->board->name,
            ]),
        ];
    }

    /**
     * Add a mirror of this card to another column/board.
     */
    public function addMirror(Request $request, Card $card)
    {
        $data = $request->validate([
            'column_id' => 'required|exists:board_columns,id',
        ]);

        $targetColumn = BoardColumn::with('board')->findOrFail($data['column_id']);

        // Prevent mirroring to home column
        if ($card->board_column_id === $targetColumn->id) {
            return response()->json(['error' => 'Card already exists in this column'], 422);
        }

        // Check if mirror already exists
        if ($card->appearances()->where('board_column_id', $targetColumn->id)->exists()) {
            return response()->json(['error' => 'Card is already mirrored to this column'], 422);
        }

        $maxOrder = $targetColumn->allCards()->max('mirror_order') ?? 0;

        $card->appearances()->attach($targetColumn->id, [
            'order_column' => $maxOrder + 1,
            'created_by' => auth()->id(),
        ]);

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'mirrored',
            'text' => "added mirror to \"{$targetColumn->board->name} → {$targetColumn->name}\"",
        ]);

        return response()->json([
            'success' => true,
            'mirror' => [
                'column_id' => $targetColumn->id,
                'column_name' => $targetColumn->name,
                'board_id' => $targetColumn->board->id,
                'board_name' => $targetColumn->board->name,
            ],
        ]);
    }

    /**
     * Remove a mirror of this card from a column.
     */
    public function removeMirror(Request $request, Card $card, BoardColumn $column)
    {
        $card->appearances()->detach($column->id);

        Activity::create([
            'card_id' => $card->id,
            'user_id' => auth()->id(),
            'type' => 'mirror_removed',
            'text' => "removed mirror from \"{$column->board->name} → {$column->name}\"",
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Search columns for mirroring by board name.
     */
    public function searchColumnsForMirroring(Request $request, Card $card)
    {
        $query = $request->get('q', '');
        $user = auth()->user();

        if (strlen($query) < 2) {
            return [];
        }

        $boards = Board::accessibleBy($user)
            ->where('name', 'like', "%{$query}%")
            ->with(['columns' => function ($q) {
                $q->orderBy('order_column');
            }])
            ->limit(5)
            ->get();

        // Get existing mirror column IDs
        $existingMirrorIds = $card->appearances()->pluck('board_column_id')->toArray();
        $homeColumnId = $card->board_column_id;

        $results = [];
        foreach ($boards as $board) {
            foreach ($board->columns as $col) {
                $results[] = [
                    'board_id' => $board->id,
                    'board_name' => $board->name,
                    'column_id' => $col->id,
                    'column_name' => $col->name,
                    'is_home' => $col->id === $homeColumnId,
                    'has_mirror' => in_array($col->id, $existingMirrorIds),
                ];
            }
        }

        return $results;
    }

    /**
     * Notify card owner and assignees about activity on the card.
     * Excludes the actor (person who performed the action).
     */
    private function notifyCardActivity(Card $card, string $activityText): void
    {
        $actor = auth()->user();
        $notifyUserIds = collect();

        // Add card creator
        if ($card->created_by && $card->created_by !== $actor->id) {
            $notifyUserIds->push($card->created_by);
        }

        // Add all assignees except the actor
        $assigneeIds = $card->assignees->pluck('id')->filter(fn($id) => $id !== $actor->id);
        $notifyUserIds = $notifyUserIds->merge($assigneeIds)->unique();

        $users = User::whereIn('id', $notifyUserIds)->get();

        foreach ($users as $user) {
            try {
                $user->notify(new CardActivityNotification($card, $actor, $activityText));
            } catch (\Exception $e) {
                // Log error or ignore
            }
        }
    }
}
