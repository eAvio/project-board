<?php

namespace Eavio\ProjectBoard\Http\Controllers\Api;

use Eavio\ProjectBoard\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiTokenController extends Controller
{
    /**
     * List all tokens for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->apiTokens()
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
     * Create a new API token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null;

        $result = UserApiToken::createToken(
            $request->user(),
            $validated['name'],
            $abilities,
            $expiresAt
        );

        return response()->json([
            'token' => [
                'id' => $result['token']->id,
                'name' => $result['token']->name,
                'abilities' => $result['token']->abilities,
                'expires_at' => $result['token']->expires_at?->toIso8601String(),
                'created_at' => $result['token']->created_at->toIso8601String(),
            ],
            'plain_token' => $result['plain_token'],
            'message' => 'Token created. Copy this token now - you will not be able to see it again!',
        ], 201);
    }

    /**
     * Delete a token.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->apiTokens()->findOrFail($id);
        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token revoked',
        ]);
    }
}
