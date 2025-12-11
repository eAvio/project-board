<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Requests\NovaRequest;

/*
|--------------------------------------------------------------------------
| Tool Routes
|--------------------------------------------------------------------------
|
| Here is where you may register Inertia routes for your tool. These are
| loaded by the ServiceProvider of the tool. The routes are protected
| by your tool's "Authorize" middleware by default. Now - go build!
|
*/

// Catch-all route to handle deep links like /o/3463-TR or /board/card
Route::get('/{path?}', function (NovaRequest $request) {
    return inertia('ProjectsBoard', [
        'initialUser' => $request->user(),
    ]);
})->where('path', '.*');
