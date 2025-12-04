<?php

namespace Eavio\ProjectBoard\Http\Controllers\Api;

use Eavio\ProjectBoard\Models\UserApiToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TokenDisplayController extends Controller
{
    /**
     * Show the token display page (one-time view).
     */
    public function __invoke(string $key)
    {
        $data = cache()->pull($key); // Get and delete from cache
        
        if (!$data) {
            return response()->view('projects-board::token-expired');
        }

        return response()->view('projects-board::show-token', [
            'tokens' => $data['tokens'],
            'name' => $data['name'],
        ]);
    }
}
