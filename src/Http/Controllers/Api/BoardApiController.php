<?php

namespace Eavio\ProjectBoard\Http\Controllers\Api;

use Eavio\ProjectBoard\Models\Board;
use Eavio\ProjectBoard\Models\BoardColumn;
use Eavio\ProjectBoard\Models\Card;
use Eavio\ProjectBoard\Models\Activity;
use Eavio\ProjectBoard\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class BoardApiController extends Controller
{
    /**
     * List all boards with full details (columns, cards, users, totals).
     * Returns the most recently updated board first as the "default" board.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $boards = Board::accessibleBy($user)
            ->with([
                'columns.cards.labels',
                'columns.cards.assignees',
                'columns.cards.media',
                'users',
                'boardable'
            ])
            ->orderByDesc('updated_at')
            ->get();

        $formattedBoards = $boards->map(fn($board) => $this->formatBoardFull($board, $user));

        // Global labels (same for all boards)
        $labels = Label::orderBy('name')->get()->map(fn($label) => [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
        ]);

        return response()->json([
            'boards' => $formattedBoards,
            'total' => $formattedBoards->count(),
            'default_board_id' => $boards->first()?->id,
            'hint' => 'The default_board_id is the most recently updated board. Use this if user does not specify a board.',
            'labels' => $labels,
        ]);
    }

    /**
     * Get a single board with full details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::with([
                'columns.cards.labels',
                'columns.cards.assignees',
                'columns.cards.media',
                'columns.cards.comments.user',
                'users',
                'boardable'
            ])
            ->findOrFail($id);

        if (!$this->canAccessBoard($user, $board)) {
            return response()->json(['error' => 'Forbidden', 'message' => 'You do not have access to this board'], 403);
        }

        return response()->json($this->formatBoardFull($board, $user));
    }

    /**
     * Create a new board.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$this->hasFullAccess($user)) {
            return response()->json(['error' => 'Forbidden', 'message' => 'Only admins can create boards'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'columns' => 'nullable|array',
            'columns.*.name' => 'required|string|max:255',
            'columns.*.cards' => 'nullable|array',
            'columns.*.cards.*.title' => 'required|string|max:255',
            'columns.*.cards.*.description' => 'nullable|string',
        ]);

        $board = Board::create([
            'name' => $validated['name'],
        ]);

        // Create columns with cards if provided
        if (!empty($validated['columns'])) {
            foreach ($validated['columns'] as $order => $columnData) {
                $column = $board->columns()->create([
                    'name' => $columnData['name'],
                    'slug' => Str::slug($columnData['name']),
                    'order_column' => $order,
                ]);

                if (!empty($columnData['cards'])) {
                    foreach ($columnData['cards'] as $cardOrder => $cardData) {
                        $card = $column->cards()->create([
                            'title' => $cardData['title'],
                            'description' => $cardData['description'] ?? null,
                            'order_column' => $cardOrder,
                            'created_by' => $user->id,
                        ]);

                        Activity::create([
                            'card_id' => $card->id,
                            'user_id' => $user->id,
                            'type' => 'created',
                            'text' => 'created this card',
                        ]);
                    }
                }
            }
        }

        $board->load(['columns.cards', 'users', 'boardable']);

        return response()->json([
            'success' => true,
            'message' => 'Board created',
            'board' => $this->formatBoardFull($board, $user),
        ], 201);
    }

    /**
     * Update a board.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        if ($this->getBoardRole($user, $board) !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $board->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Board updated',
            'board' => $this->formatBoardFull($board->fresh(['columns.cards', 'users', 'boardable']), $user),
        ]);
    }

    /**
     * Create a new column.
     */
    public function createColumn(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        if (!in_array($this->getBoardRole($user, $board), ['admin', 'member'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $maxOrder = $board->columns()->max('order_column') ?? -1;
        
        $column = $board->columns()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'order_column' => $maxOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Column created',
            'column' => [
                'id' => $column->id,
                'name' => $column->name,
                'order' => $column->order_column,
                'cards' => [],
            ],
        ], 201);
    }

    /**
     * Create a column with cards in one request.
     */
    public function createColumnWithCards(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        if (!in_array($this->getBoardRole($user, $board), ['admin', 'member'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cards' => 'required|array|min:1',
            'cards.*.title' => 'required|string|max:255',
            'cards.*.description' => 'nullable|string',
            'cards.*.estimated_hours' => 'nullable|numeric|min:0',
            'cards.*.due_date' => 'nullable|date',
        ]);

        $maxOrder = $board->columns()->max('order_column') ?? -1;
        
        $column = $board->columns()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'order_column' => $maxOrder + 1,
        ]);

        $createdCards = [];
        foreach ($validated['cards'] as $cardOrder => $cardData) {
            $card = $column->cards()->create([
                'title' => $cardData['title'],
                'description' => $cardData['description'] ?? null,
                'estimated_hours' => $cardData['estimated_hours'] ?? null,
                'due_date' => $cardData['due_date'] ?? null,
                'order_column' => $cardOrder,
                'created_by' => $user->id,
            ]);

            Activity::create([
                'card_id' => $card->id,
                'user_id' => $user->id,
                'type' => 'created',
                'text' => 'created this card',
            ]);
            $createdCards[] = $this->formatCard($card);
        }

        return response()->json([
            'success' => true,
            'message' => 'Column created with ' . count($createdCards) . ' cards',
            'column' => [
                'id' => $column->id,
                'name' => $column->name,
                'order' => $column->order_column,
                'cards' => $createdCards,
            ],
        ], 201);
    }

    /**
     * Update a column.
     */
    public function updateColumn(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $column = BoardColumn::findOrFail($id);
        $board = $column->board;

        if (!in_array($this->getBoardRole($user, $board), ['admin', 'member'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'order' => 'sometimes|integer|min:0',
        ]);

        if (isset($validated['order'])) {
            $validated['order_column'] = $validated['order'];
            unset($validated['order']);
        }

        $column->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Column updated',
            'column' => [
                'id' => $column->id,
                'name' => $column->name,
                'order' => $column->order_column,
            ],
        ]);
    }

    /**
     * Delete a column.
     */
    public function deleteColumn(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $column = BoardColumn::findOrFail($id);
        $board = $column->board;

        if ($this->getBoardRole($user, $board) !== 'admin') {
            return response()->json(['error' => 'Forbidden', 'message' => 'Only admins can delete columns'], 403);
        }

        $column->delete();

        return response()->json([
            'success' => true,
            'message' => 'Column deleted',
        ]);
    }

    /**
     * Bulk create cards in a board.
     */
    public function bulkCreateCards(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::with('columns')->findOrFail($id);

        if (!in_array($this->getBoardRole($user, $board), ['admin', 'member'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'cards' => 'required|array|min:1',
            'cards.*.column_id' => 'required|integer',
            'cards.*.title' => 'required|string|max:255',
            'cards.*.description' => 'nullable|string',
            'cards.*.estimated_hours' => 'nullable|numeric|min:0',
            'cards.*.estimated_cost' => 'nullable|numeric|min:0',
            'cards.*.actual_hours' => 'nullable|numeric|min:0',
            'cards.*.actual_cost' => 'nullable|numeric|min:0',
            'cards.*.due_date' => 'nullable|date',
        ]);

        $columnIds = $board->columns->pluck('id')->toArray();
        $createdCards = [];

        foreach ($validated['cards'] as $cardData) {
            if (!in_array($cardData['column_id'], $columnIds)) {
                return response()->json([
                    'error' => 'Invalid column_id',
                    'message' => "Column {$cardData['column_id']} does not belong to this board",
                ], 422);
            }

            $column = $board->columns->firstWhere('id', $cardData['column_id']);
            $maxOrder = $column->cards()->max('order_column') ?? -1;

            $card = $column->cards()->create([
                'title' => $cardData['title'],
                'description' => $cardData['description'] ?? null,
                'estimated_hours' => $cardData['estimated_hours'] ?? null,
                'estimated_cost' => $cardData['estimated_cost'] ?? null,
                'actual_hours' => $cardData['actual_hours'] ?? null,
                'actual_cost' => $cardData['actual_cost'] ?? null,
                'due_date' => $cardData['due_date'] ?? null,
                'order_column' => $maxOrder + 1,
                'created_by' => $user->id,
            ]);

            Activity::create([
                'card_id' => $card->id,
                'user_id' => $user->id,
                'type' => 'created',
                'text' => 'created this card',
            ]);

            $createdCards[] = $this->formatCard($card);
        }

        return response()->json([
            'success' => true,
            'message' => count($createdCards) . ' cards created',
            'cards' => $createdCards,
        ], 201);
    }

    /**
     * Bulk update cards in a board.
     */
    public function bulkUpdateCards(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::with(['columns.cards'])->findOrFail($id);

        if (!in_array($this->getBoardRole($user, $board), ['admin', 'member'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'cards' => 'required|array|min:1|max:50',
            'cards.*.id' => 'required|exists:cards,id',
            'cards.*.title' => 'sometimes|string|max:255',
            'cards.*.description' => 'nullable|string',
            'cards.*.estimated_hours' => 'nullable|numeric|min:0',
            'cards.*.estimated_cost' => 'nullable|numeric|min:0',
            'cards.*.actual_hours' => 'nullable|numeric|min:0',
            'cards.*.actual_cost' => 'nullable|numeric|min:0',
            'cards.*.due_date' => 'nullable|date',
            'cards.*.column_id' => 'sometimes|exists:board_columns,id',
            'cards.*.labels' => 'sometimes|array',
            'cards.*.labels.*' => 'integer|exists:labels,id',
        ]);

        $boardColumnIds = $board->columns->pluck('id')->toArray();
        $results = [];
        $errors = [];

        foreach ($validated['cards'] as $cardData) {
            $cardId = $cardData['id'];

            $labels = $cardData['labels'] ?? null;
            unset($cardData['id'], $cardData['labels']);

            try {
                $card = $board->columns->flatMap->cards->firstWhere('id', $cardId);
                if (!$card) {
                    $errors[] = [
                        'id' => $cardId,
                        'error' => 'Card does not belong to this board',
                    ];
                    continue;
                }

                // Handle optional column move within this board
                $oldColumnId = $card->board_column_id;
                if (isset($cardData['column_id'])) {
                    $targetColumnId = $cardData['column_id'];
                    if (!in_array($targetColumnId, $boardColumnIds)) {
                        $errors[] = [
                            'id' => $cardId,
                            'error' => 'Target column does not belong to this board',
                        ];
                        unset($cardData['column_id']);
                    } else {
                        $targetColumn = $board->columns->firstWhere('id', $targetColumnId);
                        $cardData['board_column_id'] = $targetColumnId;
                        $cardData['order_column'] = ($targetColumn->cards()->max('order_column') ?? 0) + 1;
                        unset($cardData['column_id']);
                    }
                }

                $card->update($cardData);

                // Log field-specific updates
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

                // Sync labels for this card if provided
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

                $results[] = [
                    'id' => $cardId,
                    'success' => true,
                    'card' => $this->formatCard($card->fresh(['column.board', 'labels', 'assignees'])),
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $cardId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($results) . ' cards updated' . (count($errors) > 0 ? ', ' . count($errors) . ' failed' : ''),
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Get board members.
     */
    public function members(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::with('users')->findOrFail($id);

        if (!$this->canAccessBoard($user, $board)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'members' => $board->users->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ]),
        ]);
    }

    /**
     * Add a member to the board.
     */
    public function addMember(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        $boardRole = $this->getBoardRole($user, $board);
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Forbidden', 'message' => 'Only board admins can add members'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:viewer,member,admin',
        ]);

        // Check if already a member
        if ($board->users()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json(['error' => 'User is already a member of this board'], 422);
        }

        $board->users()->attach($validated['user_id'], ['role' => $validated['role']]);

        return response()->json(['success' => true, 'message' => 'Member added']);
    }

    /**
     * Update a member's role.
     */
    public function updateMember(Request $request, int $id, int $userId): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        $boardRole = $this->getBoardRole($user, $board);
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:viewer,member,admin',
        ]);

        $board->users()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return response()->json(['success' => true, 'message' => 'Member role updated']);
    }

    /**
     * Remove a member from the board.
     */
    public function removeMember(Request $request, int $id, int $userId): JsonResponse
    {
        $user = auth()->user();
        $board = Board::findOrFail($id);

        $boardRole = $this->getBoardRole($user, $board);
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $board->users()->detach($userId);

        return response()->json(['success' => true, 'message' => 'Member removed']);
    }

    /**
     * Format a board with full details for API response.
     */
    protected function formatBoardFull($board, $user): array
    {
        // Calculate totals
        $totals = [
            'estimated_hours' => 0,
            'actual_hours' => 0,
            'estimated_cost' => 0,
            'actual_cost' => 0,
            'total_cards' => 0,
        ];

        $columns = $board->columns->map(function ($column) use (&$totals) {
            $columnTotals = [
                'estimated_hours' => $column->cards->sum('estimated_hours') ?? 0,
                'estimated_cost' => $column->cards->sum('estimated_cost') ?? 0,
                'actual_hours' => $column->cards->sum('actual_hours') ?? 0,
                'actual_cost' => $column->cards->sum('actual_cost') ?? 0,
                'cards_count' => $column->cards->count(),
            ];

            $totals['estimated_hours'] += $columnTotals['estimated_hours'];
            $totals['estimated_cost'] += $columnTotals['estimated_cost'];
            $totals['actual_hours'] += $columnTotals['actual_hours'];
            $totals['actual_cost'] += $columnTotals['actual_cost'];
            $totals['total_cards'] += $columnTotals['cards_count'];

            return [
                'id' => $column->id,
                'name' => $column->name,
                'order' => $column->order_column,
                'cards_count' => $columnTotals['cards_count'],
                'totals' => $columnTotals,
                'cards' => $column->cards->map(fn($card) => $this->formatCard($card)),
            ];
        });

        // Get relation info (boardable)
        $relation = null;
        if ($board->boardable) {
            $relation = [
                'type' => class_basename($board->boardable),
                'id' => $board->boardable->id,
                'name' => $board->boardable->name ?? $board->boardable->title ?? null,
            ];
        }

        // Get board members
        $boardMembers = $board->users->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
        ]);

        return [
            'id' => $board->id,
            'name' => $board->name,
            'relation' => $relation,
            'background_url' => $board->background_url,
            'background_color' => $board->background_color,
            'user_role' => $this->getBoardRole($user, $board),
            'totals' => $totals,
            'columns' => $columns,
            'members' => $boardMembers,
            'created_at' => $board->created_at->toIso8601String(),
            'updated_at' => $board->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format a card for API response.
     */
    protected function formatCard($card): array
    {
        $attachments = [];
        if ($card->relationLoaded('media')) {
            $attachments = $card->media->map(fn($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ])->toArray();
        }

        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'column_id' => $card->board_column_id,
            'order' => $card->order_column,
            'estimated_hours' => $card->estimated_hours,
            'actual_hours' => $card->actual_hours,
            'due_date' => $card->due_date?->toIso8601String(),
            'labels' => $card->relationLoaded('labels') ? $card->labels->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->toArray() : [],
            'assignees' => $card->relationLoaded('assignees') ? $card->assignees->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
            ])->toArray() : [],
            'attachments' => $attachments,
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
