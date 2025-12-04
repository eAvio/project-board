<?php

use Illuminate\Support\Facades\Route;
use Eavio\ProjectBoard\Http\Controllers\Api\BoardApiController;
use Eavio\ProjectBoard\Http\Controllers\Api\CardApiController;
use Eavio\ProjectBoard\Http\Controllers\Api\ApiTokenController;

/*
|--------------------------------------------------------------------------
| External API Routes
|--------------------------------------------------------------------------
|
| These routes are for external integrations (ChatGPT, Zapier, etc.)
| They use Bearer token authentication via the projects-board.api-token middleware.
|
*/

// Board routes
Route::middleware('project-board.api-token')->prefix('boards')->group(function () {
    Route::get('/', [BoardApiController::class, 'index']);
    Route::post('/', [BoardApiController::class, 'store']);
    Route::get('/{id}', [BoardApiController::class, 'show']);
    Route::patch('/{id}', [BoardApiController::class, 'update']);
    
    // Columns
    Route::post('/{id}/columns', [BoardApiController::class, 'createColumn']);
    Route::post('/{id}/columns-with-cards', [BoardApiController::class, 'createColumnWithCards']);
    
    // Bulk card operations
    Route::post('/{id}/cards/bulk', [BoardApiController::class, 'bulkCreateCards']);
    Route::patch('/{id}/cards/bulk-update', [BoardApiController::class, 'bulkUpdateCards']);
    
    // Board members
    Route::get('/{id}/members', [BoardApiController::class, 'members']);
    Route::post('/{id}/members', [BoardApiController::class, 'addMember']);
    Route::patch('/{id}/members/{userId}', [BoardApiController::class, 'updateMember']);
    Route::delete('/{id}/members/{userId}', [BoardApiController::class, 'removeMember']);
});

// Column routes
Route::middleware('project-board.api-token')->prefix('columns')->group(function () {
    Route::patch('/{id}', [BoardApiController::class, 'updateColumn']);
    Route::delete('/{id}', [BoardApiController::class, 'deleteColumn']);
});

// Card routes
Route::middleware('project-board.api-token')->prefix('cards')->group(function () {
    Route::get('/search', [CardApiController::class, 'search']);
    Route::get('/{id}', [CardApiController::class, 'show']);
    Route::post('/', [CardApiController::class, 'store']);
    Route::patch('/{id}', [CardApiController::class, 'update']);
    Route::delete('/{id}', [CardApiController::class, 'destroy']);
    Route::post('/{id}/move', [CardApiController::class, 'move']);
    Route::post('/{id}/comments', [CardApiController::class, 'addComment']);
    Route::post('/{id}/attachments', [CardApiController::class, 'addAttachment']);
});

// Token management (requires Sanctum auth - for Nova users to manage their tokens)
Route::middleware('auth:sanctum')->prefix('tokens')->group(function () {
    Route::get('/', [ApiTokenController::class, 'index']);
    Route::post('/', [ApiTokenController::class, 'store']);
    Route::delete('/{id}', [ApiTokenController::class, 'destroy']);
});
