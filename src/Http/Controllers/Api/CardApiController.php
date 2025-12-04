<?php

namespace Eavio\ProjectBoard\Http\Controllers\Api;

use Eavio\ProjectBoard\Models\Board;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\BoardColumn;
use Eavio\ProjectBoard\Models\Activity;
use Eavio\ProjectBoard\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CardApiController extends Controller
{
    /**
     * Get a single card with full details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with(['column.board', 'labels', 'assignees', 'comments.user'])
            ->findOrFail($id);

        $board = $card->column->board;
        if (!$this->canAccessBoard($user, $board)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($this->formatCardFull($card));
    }

    /**
     * Create a new card.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'column_id' => 'required|exists:board_columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
        ]);

        $column = BoardColumn::with('board')->findOrFail($validated['column_id']);
        $board = $column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!in_array($boardRole, ['member', 'admin'])) {
            return response()->json(['error' => 'Forbidden', 'message' => 'Viewers cannot create cards'], 403);
        }

        $maxOrder = $column->cards()->max('order_column') ?? 0;

        $card = Card::create([
            'board_column_id' => $column->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_hours' => $validated['estimated_hours'] ?? null,
            'estimated_cost' => $validated['estimated_cost'] ?? null,
            'actual_hours' => $validated['actual_hours'] ?? null,
            'actual_cost' => $validated['actual_cost'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'order_column' => $maxOrder + 1,
            'created_by' => $user->id,
        ]);

        Activity::create([
            'card_id' => $card->id,
            'user_id' => $user->id,
            'type' => 'created',
            'text' => 'created this card',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card created',
            'card' => $this->formatCardFull($card->fresh(['column.board', 'labels', 'assignees'])),
        ], 201);
    }

    /**
     * Update a card.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with('column.board')->findOrFail($id);
        $board = $card->column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!in_array($boardRole, ['member', 'admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'labels' => 'sometimes|array',
            'labels.*' => 'integer|exists:labels,id',
        ]);

        // Extract labels before mass assignment
        $labels = $validated['labels'] ?? null;
        unset($validated['labels']);

        $card->update($validated);

        // Log activities
        if ($card->wasChanged('description')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => $user->id,
                'type' => 'updated',
                'text' => 'updated the description',
            ]);
        }

        if ($card->wasChanged('due_date')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => $user->id,
                'type' => 'updated',
                'text' => 'changed the due date',
            ]);
        }
        
        if ($card->wasChanged('title')) {
            Activity::create([
                'card_id' => $card->id,
                'user_id' => $user->id,
                'type' => 'updated',
                'text' => 'renamed the card',
            ]);
        }

        // Sync labels if provided
        if ($labels !== null) {
            $oldLabels = $card->labels()->pluck('labels.id')->toArray();
            $newLabels = $labels;

            $added = array_diff($newLabels, $oldLabels);
            $removed = array_diff($oldLabels, $newLabels);

            $card->labels()->sync($newLabels);

            foreach ($added as $labelId) {
                $label = Label::find($labelId);
                if ($label) {
                    Activity::create([
                        'card_id' => $card->id,
                        'user_id' => $user->id,
                        'type' => 'labeled',
                        'text' => "added label {$label->name}",
                    ]);
                }
            }

            foreach ($removed as $labelId) {
                $label = Label::find($labelId);
                if ($label) {
                    Activity::create([
                        'card_id' => $card->id,
                        'user_id' => $user->id,
                        'type' => 'unlabeled',
                        'text' => "removed label {$label->name}",
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Card updated',
            'card' => $this->formatCardFull($card->fresh(['column.board', 'labels', 'assignees'])),
        ]);
    }

    /**
     * Move a card to a different column.
     */
    public function move(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with('column.board')->findOrFail($id);
        $board = $card->column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!in_array($boardRole, ['member', 'admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'column_id' => 'required|exists:board_columns,id',
            'position' => 'nullable|integer|min:0',
        ]);

        $targetColumn = BoardColumn::with('board')->findOrFail($validated['column_id']);
        
        // Ensure target column is in the same board
        if ($targetColumn->board_id !== $board->id) {
            return response()->json(['error' => 'Cannot move card to a different board'], 422);
        }

        $position = $validated['position'] ?? ($targetColumn->cards()->max('order_column') ?? 0) + 1;

        $oldColumnId = $card->board_column_id;

        $card->update([
            'board_column_id' => $targetColumn->id,
            'order_column' => $position,
        ]);

        if ($oldColumnId != $card->board_column_id) {
            $oldColumn = BoardColumn::find($oldColumnId);
            $newColumn = BoardColumn::find($card->board_column_id);
            if ($oldColumn && $newColumn) {
                $activityText = "moved this card from {$oldColumn->name} to {$newColumn->name}";
                Activity::create([
                    'card_id' => $card->id,
                    'user_id' => $user->id,
                    'type' => 'moved',
                    'text' => $activityText,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Card moved',
            'card' => $this->formatCardFull($card->fresh(['column.board', 'labels', 'assignees'])),
        ]);
    }

    /**
     * Add a comment to a card.
     */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with('column.board')->findOrFail($id);
        $board = $card->column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!$boardRole) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $comment = $card->comments()->create([
            'commenter_id' => $user->id,
            'commenter_type' => get_class($user),
            'body' => $validated['body'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added',
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'created_at' => $comment->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Delete a card.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with('column.board')->findOrFail($id);
        $board = $card->column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!in_array($boardRole, ['member', 'admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $card->delete();

        Activity::create([
            'card_id' => $card->id,
            'user_id' => $user->id,
            'type' => 'archived',
            'text' => 'archived this card',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Card deleted',
        ]);
    }

    /**
     * Add an attachment to a card (via URL).
     */
    public function addAttachment(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $card = Card::with('column.board')->findOrFail($id);
        $board = $card->column->board;

        $boardRole = $this->getBoardRole($user, $board);
        if (!in_array($boardRole, ['member', 'admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'url' => 'required|url',
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $card->addMediaFromUrl($validated['url'])
                ->usingName($validated['name'] ?? basename($validated['url']))
                ->toMediaCollection('attachments');

            return response()->json([
                'success' => true,
                'message' => 'Attachment added',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add attachment',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Search cards across accessible boards.
     */
    public function search(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = $request->get('q', '');
        $boardId = $request->get('board_id');

        $cardsQuery = Card::with(['column.board', 'labels', 'assignees'])
            ->whereHas('column.board', function ($q) use ($user) {
                $q->accessibleBy($user);
            });

        if ($boardId) {
            $cardsQuery->whereHas('column', fn($q) => $q->where('board_id', $boardId));
        }

        if ($query) {
            $cardsQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhereHas('labels', function ($l) use ($query) {
                      $l->where('name', 'like', "%{$query}%");
                  });
            });
        }

        $cards = $cardsQuery->limit(50)->get();

        return response()->json([
            'cards' => $cards->map(fn($card) => [
                'id' => $card->id,
                'title' => $card->title,
                'description' => $card->description,
                'board' => [
                    'id' => $card->column->board->id,
                    'name' => $card->column->board->name,
                ],
                'column' => [
                    'id' => $card->column->id,
                    'name' => $card->column->name,
                ],
                'labels' => $card->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
                'assignees' => $card->assignees->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
            ]),
            'total' => $cards->count(),
        ]);
    }

    /**
     * Format a card with full details.
     */
    protected function formatCardFull($card): array
    {
        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'board' => [
                'id' => $card->column->board->id,
                'name' => $card->column->board->name,
            ],
            'column' => [
                'id' => $card->column->id,
                'name' => $card->column->name,
            ],
            'order' => $card->order_column,
            'estimated_hours' => $card->estimated_hours,
            'actual_hours' => $card->actual_hours,
            'due_date' => $card->due_date?->toIso8601String(),
            'labels' => $card->labels->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ]),
            'assignees' => $card->assignees->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
            ]),
            'comments' => $card->comments?->map(fn($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => [
                    'id' => $c->commenter->id ?? null,
                    'name' => $c->commenter->name ?? 'Unknown',
                ],
                'created_at' => $c->created_at->toIso8601String(),
            ]) ?? [],
            'created_at' => $card->created_at->toIso8601String(),
            'updated_at' => $card->updated_at->toIso8601String(),
        ];
    }

    private function hasFullAccess($user)
    {
        // Check for super-admin or admin role first
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
                return true;
            }
        }
        
        return (method_exists($user, 'hasFullBoardAccess') && $user->hasFullBoardAccess())
            || ($user->can('access all boards') ?? false);
    }

    private function canAccessBoard($user, $board)
    {
        if ($this->hasFullAccess($user)) {
            return true;
        }
        return $board->users()->where('user_id', $user->id)->exists();
    }

    private function getBoardRole($user, $board)
    {
        if ($this->hasFullAccess($user)) {
            return 'admin';
        }
        
        $member = $board->users()->where('user_id', $user->id)->first();
        return $member ? $member->pivot->role : null;
    }
}
