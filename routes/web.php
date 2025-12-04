<?php

use Illuminate\Support\Facades\Route;
use Eavio\ProjectBoard\Http\Controllers\Api\TokenDisplayController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes handle web-based functionality like token display pages.
|
*/

// One-time token display page
Route::get('/project-board/token/{key}', TokenDisplayController::class)
    ->name('project-board.show-token');

// OpenAPI specification
Route::get('/project-board/openapi.json', function () {
    $spec = file_get_contents(__DIR__ . '/../public/openapi/project-board.json');
    $spec = json_decode($spec, true);
    
    // Set the server URL dynamically (root domain - paths include full prefix)
    $spec['servers'] = [
        ['url' => url('/'), 'description' => 'API Server']
    ];
    
    return response()->json($spec);
})->name('project-board.openapi');
