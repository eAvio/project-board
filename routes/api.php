<?php

use Illuminate\Support\Facades\Route;
use Eavio\ProjectBoard\Http\Controllers\BoardController;
use Eavio\ProjectBoard\Http\Controllers\CardController;
use Eavio\ProjectBoard\Http\Controllers\ColumnController;
use Eavio\ProjectBoard\Http\Controllers\CommentController;
use Eavio\ProjectBoard\Http\Controllers\ChecklistController;
use Eavio\ProjectBoard\Http\Controllers\UnsplashController;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

Route::get('/boards', [BoardController::class, 'index']);
Route::get('/users', [BoardController::class, 'users']);
Route::get('/users/search', [BoardController::class, 'searchUsers']);
Route::get('/boards/{board}', [BoardController::class, 'show']);
Route::get('/boards/{board}/archived', [BoardController::class, 'archived']);
Route::post('/boards', [BoardController::class, 'store']);
Route::put('/boards/{board}', [BoardController::class, 'update']);
Route::delete('/boards/{board}', [BoardController::class, 'destroy']);

// Board members
Route::get('/boards/{board}/members', [BoardController::class, 'members']);
Route::post('/boards/{board}/members', [BoardController::class, 'addMember']);
Route::put('/boards/{board}/members/{userId}', [BoardController::class, 'updateMember']);
Route::delete('/boards/{board}/members/{userId}', [BoardController::class, 'removeMember']);

Route::post('/boards/{board}/columns', [ColumnController::class, 'store']);
Route::put('/columns/{column}', [ColumnController::class, 'update']);
Route::put('/columns/{column}/reorder', [ColumnController::class, 'reorder']);
Route::post('/columns/{column}/archive-cards', [ColumnController::class, 'archiveCards']);
Route::delete('/columns/{column}', [ColumnController::class, 'destroy']);
Route::put('/columns/{id}/restore', [ColumnController::class, 'restore']);
Route::delete('/columns/{id}/force', [ColumnController::class, 'forceDelete']);

Route::post('/columns/{column}/cards', [CardController::class, 'store']);
Route::post('/columns/{column}/cards-with-image', [CardController::class, 'storeWithImage']);
Route::post('/columns/{column}/cards-with-file', [CardController::class, 'storeWithFile']);
Route::get('/cards/{card}', [CardController::class, 'show']);
Route::put('/cards/{card}', [CardController::class, 'update']);
Route::put('/cards/{card}/move', [CardController::class, 'move']);
Route::post('/cards/{card}/duplicate', [CardController::class, 'duplicate']);
Route::delete('/cards/{card}', [CardController::class, 'destroy']);
Route::put('/cards/{id}/restore', [CardController::class, 'restore']);
Route::delete('/cards/{id}/force', [CardController::class, 'forceDelete']);
Route::put('/cards/{card}/cover', [CardController::class, 'setCover']);
Route::delete('/cards/{card}/cover', [CardController::class, 'removeCover']);

Route::post('/cards/{card}/comments', [CommentController::class, 'store']);
Route::post('/cards/{card}/attachments', [CardController::class, 'uploadAttachment']);
Route::delete('/cards/{card}/attachments/{mediaId}', [CardController::class, 'deleteAttachment']);
Route::put('/comments/{comment}', [CommentController::class, 'update']);
Route::post('/comments/{comment}/reactions', [CommentController::class, 'toggleReaction']);
Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

Route::get('/labels', [\Eavio\ProjectBoard\Http\Controllers\LabelController::class, 'index']);
Route::post('/labels', [\Eavio\ProjectBoard\Http\Controllers\LabelController::class, 'store']);
Route::put('/labels/{label}', [\Eavio\ProjectBoard\Http\Controllers\LabelController::class, 'update']);
Route::delete('/labels/{label}', [\Eavio\ProjectBoard\Http\Controllers\LabelController::class, 'destroy']);
Route::post('/cards/{card}/labels', [\Eavio\ProjectBoard\Http\Controllers\LabelController::class, 'sync']);
Route::post('/cards/{card}/assignees', [CardController::class, 'syncAssignees']);
Route::get('/cards/{card}/activities', [CardController::class, 'activities']);
Route::get('/cards/{card}/appearances', [CardController::class, 'getAppearances']);
Route::post('/cards/{card}/mirror', [CardController::class, 'addMirror']);
Route::delete('/cards/{card}/mirror/{column}', [CardController::class, 'removeMirror']);
Route::get('/cards/{card}/search-columns-for-mirroring', [CardController::class, 'searchColumnsForMirroring']);

Route::get('/search', [\Eavio\ProjectBoard\Http\Controllers\SearchController::class, 'index']);

Route::get('/cards/{card}/checklists', [ChecklistController::class, 'index']);
Route::post('/cards/{card}/checklists', [ChecklistController::class, 'store']);
Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy']);
Route::post('/checklists/{checklist}/items', [ChecklistController::class, 'storeItem']);
Route::put('/checklist-items/{item}', [ChecklistController::class, 'updateItem']);
Route::delete('/checklist-items/{item}', [ChecklistController::class, 'destroyItem']);

// Unsplash
Route::get('/unsplash/search', [UnsplashController::class, 'search']);
Route::get('/unsplash/featured', [UnsplashController::class, 'featured']);
Route::post('/unsplash/track-download', [UnsplashController::class, 'trackDownload']);

// API Tokens (for Nova UI token management)
Route::get('/api-tokens', [BoardController::class, 'listApiTokens']);
Route::post('/api-tokens', [BoardController::class, 'createApiToken']);
Route::delete('/api-tokens/{id}', [BoardController::class, 'revokeApiToken']);

// Trello Import
Route::post('/import-trello', [\Eavio\ProjectBoard\Http\Controllers\ImportTrelloController::class, 'store']);
Route::get('/import-trello/status', [\Eavio\ProjectBoard\Http\Controllers\ImportTrelloController::class, 'status']);
