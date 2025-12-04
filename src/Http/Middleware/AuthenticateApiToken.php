<?php

namespace Eavio\ProjectBoard\Http\Middleware;

use Eavio\ProjectBoard\Models\UserApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API token required',
            ], 401);
        }

        $apiToken = UserApiToken::findByToken($token);

        if (!$apiToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token',
            ], 401);
        }

        if (!$apiToken->isValid()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API token has expired',
            ], 401);
        }

        // Check ability if specified
        if ($ability && !$apiToken->hasAbility($ability)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "Token does not have the '{$ability}' ability",
            ], 403);
        }

        // Mark token as used
        $apiToken->markAsUsed();

        // Set the authenticated user
        auth()->login($apiToken->user);
        
        // Store token on request for later use
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
