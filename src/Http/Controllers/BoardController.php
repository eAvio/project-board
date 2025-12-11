<?php

namespace Eavio\ProjectBoard\Http\Controllers;

use Eavio\ProjectBoard\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Nova;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Board::with([
            'columns.cards.labels', 
            'columns.cards.assignees', 
            'columns.cards.media', 
            'columns.cards.comments.user', 
            'columns.cards.comments.reactions.user',
            'columns.mirroredCards.labels',
            'columns.mirroredCards.assignees',
            'columns.mirroredCards.media',
            'columns.mirroredCards.column.board',
            'boardable',
            'users'
        ]);

        $isScopedToResource = $request->has('resourceName') && $request->has('resourceId');

        // If not scoped to a specific resource, apply user access restrictions
        if ($user && !$isScopedToResource) {
            $hasAccess = $this->userHasFullAccess($user);

            if (!$hasAccess) {
                $query->accessibleBy($user);
            }
        }

        if ($isScopedToResource) {
            // Get the model class from Nova resource key
            // Nova::resourceForKey may not work in all contexts, so we use a fallback
            $modelClass = $this->resolveModelClassFromResourceKey($request->resourceName);
            
            if ($modelClass) {
                $query->where('boardable_type', $modelClass)
                      ->where('boardable_id', $request->resourceId);
            } else {
                // If we can't resolve the model, return empty to prevent showing wrong boards
                Log::warning("ProjectBoard: Could not resolve model for resource key: {$request->resourceName}");
                return [];
            }
        } 
        // Else: Return all boards (Global + Scoped)

        $boards = $query->get();

        // Compute totals for each board and column
        foreach ($boards as $board) {
            $boardTotals = [
                'estimated_hours' => 0,
                'estimated_cost' => 0,
                'actual_hours' => 0,
                'actual_cost' => 0,
            ];

            foreach ($board->columns as $column) {
                // Mark home cards
                $homeCards = $column->cards->map(function ($card) {
                    $card->is_mirror = false;
                    return $card;
                });

                // Mark mirrored cards and add home board info
                $mirroredCards = $column->mirroredCards->map(function ($card) {
                    $card->is_mirror = true;
                    $card->home_board_name = $card->column?->board?->name;
                    $card->home_column_name = $card->column?->name;
                    // Use pivot order for mirrored cards
                    $card->order_column = $card->pivot->order_column;
                    return $card;
                });

                // Merge and sort all cards
                $allCards = $homeCards->merge($mirroredCards)->sortBy('order_column')->values();
                $column->setRelation('cards', $allCards);

                // Calculate totals (only for home cards to avoid double counting)
                $columnTotals = [
                    'estimated_hours' => $homeCards->sum('estimated_hours') ?? 0,
                    'estimated_cost' => $homeCards->sum('estimated_cost') ?? 0,
                    'actual_hours' => $homeCards->sum('actual_hours') ?? 0,
                    'actual_cost' => $homeCards->sum('actual_cost') ?? 0,
                ];
                $column->totals = $columnTotals;

                $boardTotals['estimated_hours'] += $columnTotals['estimated_hours'];
                $boardTotals['estimated_cost'] += $columnTotals['estimated_cost'];
                $boardTotals['actual_hours'] += $columnTotals['actual_hours'];
                $boardTotals['actual_cost'] += $columnTotals['actual_cost'];
            }

            $board->totals = $boardTotals;

            $boardMemberIds = $board->users->pluck('id')->toArray();
            $globalAdmins = collect();
            try {
                $userModel = config('project-board.user_model');
                if (method_exists(app($userModel), 'role')) {
                    $globalAdmins = app($userModel)->role(['super-admin', 'admin'])
                        ->whereNotIn('id', $boardMemberIds)
                        ->get()
                        ->map(function ($user) {
                            $role = (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) ? 'super-admin' : 'admin';
                            $user->pivot = (object)['role' => $role];
                            $user->is_global_admin = true;
                            return $user;
                        });
                }
            } catch (\Throwable $e) {
                $globalAdmins = collect();
            }
            $board->setRelation('users', $globalAdmins->merge($board->users));
        }

        return $boards;
    }

    public function show(Board $board)
    {
        return $board->load('columns.cards.labels', 'columns.cards.assignees', 'columns.cards.media', 'columns.cards.comments.user', 'columns.cards.comments.reactions.user');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'resourceName' => 'nullable|string',
            'resourceId' => 'nullable',
        ]);

        $boardData = ['name' => $data['name']];

        if (!empty($data['resourceName']) && !empty($data['resourceId'])) {
            $modelClass = $this->resolveModelClassFromResourceKey($data['resourceName']);
            if ($modelClass) {
                $boardData['boardable_type'] = $modelClass;
                $boardData['boardable_id'] = $data['resourceId'];
            }
        }

        $board = Board::create($boardData);

        if ($user = auth()->user()) {
            $board->users()->attach($user->id, ['role' => 'admin']);
        }

        return $board;
    }

    public function update(Request $request, Board $board)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'background_url' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|max:20',
        ]);

        $board->update($data);

        return $board;
    }

    public function destroy(Board $board)
    {
        $board->delete();

        return response()->noContent();
    }

    public function archived(Board $board)
    {
        return [
            'cards' => \Eavio\ProjectBoard\Models\Card::onlyTrashed()
                ->whereHas('column', function($q) use ($board) {
                    $q->where('board_id', $board->id);
                })
                ->with('column')
                ->orderBy('deleted_at', 'desc')
                ->get(),
            'columns' => \Eavio\ProjectBoard\Models\BoardColumn::onlyTrashed()
                ->where('board_id', $board->id)
                ->withCount('cards')
                ->orderBy('deleted_at', 'desc')
                ->get()
        ];
    }

    public function users()
    {
        // Return all users for mentions. 
        // In a real app, you might filter by those who have access to Nova or the board.
        return app(config('project-board.user_model'))->select('id', 'name', 'email')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name), // Or actual avatar logic
                'display' => $user->name
            ];
        });
    }

    /**
     * Get board members (including global admins).
     */
    public function members(Board $board)
    {
        // Get explicit board members
        $boardMembers = $board->users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'is_global_admin' => false,
                'avatar_url' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random',
            ];
        });

        $boardMemberIds = $board->users->pluck('id')->toArray();
        $globalAdmins = collect();
        try {
            $userModel = config('project-board.user_model');
            if (method_exists(app($userModel), 'role')) {
                $globalAdmins = app($userModel)->role(['super-admin', 'admin'])
                    ->whereNotIn('id', $boardMemberIds)
                    ->get()
                    ->map(function ($user) {
                        $role = (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) ? 'super-admin' : 'admin';
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $role,
                            'is_global_admin' => true,
                            'avatar_url' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random',
                        ];
                    });
            }
        } catch (\Throwable $e) {
            $globalAdmins = collect();
        }

        // Merge: global admins first, then board members
        return $globalAdmins->merge($boardMembers)->values();
    }

    /**
     * Add a member to the board.
     */
    public function addMember(Request $request, Board $board)
    {
        $user = auth()->user();
        $boardRole = $this->getBoardRole($user, $board);
        
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Only board admins can add members'], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:viewer,member,admin',
        ]);

        if ($board->users()->where('user_id', $data['user_id'])->exists()) {
            return response()->json(['error' => 'User is already a member'], 422);
        }

        $board->users()->attach($data['user_id'], ['role' => $data['role']]);

        return response()->json(['success' => true, 'message' => 'Member added']);
    }

    /**
     * Update a member's role.
     */
    public function updateMember(Request $request, Board $board, int $userId)
    {
        $user = auth()->user();
        $boardRole = $this->getBoardRole($user, $board);
        
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Only board admins can update members'], 403);
        }

        $data = $request->validate([
            'role' => 'required|in:viewer,member,admin',
        ]);

        $board->users()->updateExistingPivot($userId, ['role' => $data['role']]);

        return response()->json(['success' => true, 'message' => 'Member role updated']);
    }

    /**
     * Remove a member from the board.
     */
    public function removeMember(Board $board, int $userId)
    {
        $user = auth()->user();
        $boardRole = $this->getBoardRole($user, $board);
        
        if ($boardRole !== 'admin') {
            return response()->json(['error' => 'Only board admins can remove members'], 403);
        }

        $board->users()->detach($userId);

        return response()->json(['success' => true, 'message' => 'Member removed']);
    }

    /**
     * Search users for adding to board.
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');
        
        return app(config('project-board.user_model'))->where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
        ->limit(10)
        ->get()
        ->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => 'https://ui-avatars.com/api/?name=' . urlencode($user->name),
            ];
        });
    }

    /**
     * List API tokens for the current user.
     */
    public function listApiTokens(Request $request)
    {
        $user = auth()->user();
        
        $tokens = \Eavio\ProjectBoard\Models\UserApiToken::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    /**
     * Create a new API token for ProjectsBoard.
     */
    public function createApiToken(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = \Eavio\ProjectBoard\Models\UserApiToken::createToken(
            $user,
            $validated['name'],
            ['project-board:*'], // Only ProjectsBoard abilities
            null
        );

        return response()->json([
            'token' => [
                'id' => $result['token']->id,
                'name' => $result['token']->name,
                'abilities' => $result['token']->abilities,
                'created_at' => $result['token']->created_at->toIso8601String(),
            ],
            'plain_token' => $result['plain_token'],
        ], 201);
    }

    /**
     * Revoke an API token.
     */
    public function revokeApiToken(Request $request, int $id)
    {
        $user = auth()->user();
        
        $token = \Eavio\ProjectBoard\Models\UserApiToken::where('user_id', $user->id)
            ->findOrFail($id);
        
        $token->delete();

        return response()->json(['success' => true, 'message' => 'Token revoked']);
    }

    /**
     * Check if user has full access to all boards.
     * Super-admins and global admins always have full access.
     */
    private function userHasFullAccess($user): bool
    {
        // Check for super-admin or admin role first
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
                return true;
            }
        }
        
        // Check for custom hasFullBoardAccess method
        if (method_exists($user, 'hasFullBoardAccess') && $user->hasFullBoardAccess()) {
            return true;
        }
        
        // Check for permission-based access
        if (method_exists($user, 'can') && $user->can('access all boards')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get the user's role for a board.
     * Super-admins and global admins always have 'admin' access.
     */
    private function getBoardRole($user, $board): ?string
    {
        // Super-admins and admins always have full admin access
        if ($this->userHasFullAccess($user)) {
            return 'admin';
        }
        
        // Check explicit board membership
        $member = $board->users()->where('user_id', $user->id)->first();
        return $member ? $member->pivot->role : null;
    }

    /**
     * Resolve a Nova resource key (e.g., 'companies') to its Eloquent model class.
     * Uses multiple fallback strategies for maximum compatibility.
     */
    private function resolveModelClassFromResourceKey(string $resourceKey): ?string
    {
        // Strategy 1: Try Nova::resourceForKey (works when Nova is fully booted)
        $resourceClass = Nova::resourceForKey($resourceKey);
        if ($resourceClass && property_exists($resourceClass, 'model')) {
            return $resourceClass::$model;
        }

        // Strategy 2: Try to find the Nova resource class by convention
        // Nova resource keys are typically kebab-case plural (e.g., 'companies')
        // The resource class is typically PascalCase singular (e.g., 'Company')
        $resourceClassName = str($resourceKey)->singular()->studly()->toString();
        $possibleResourceClasses = [
            "App\\Nova\\{$resourceClassName}",
            "App\\Nova\\" . str($resourceKey)->studly()->toString(),
        ];

        foreach ($possibleResourceClasses as $className) {
            if (class_exists($className) && property_exists($className, 'model')) {
                return $className::$model;
            }
        }

        // Strategy 3: Try to guess the model class directly
        $possibleModelClasses = [
            "App\\Models\\{$resourceClassName}",
            "App\\{$resourceClassName}",
        ];

        foreach ($possibleModelClasses as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }

        return null;
    }
}
