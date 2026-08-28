<?php

use App\Events\HudRequested;
use App\Http\Controllers\ClipController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'menubar')->name('menubar');
Route::view('/menubar', 'menubar')->name('menubar.popover');

Route::get('/hud', [ClipController::class, 'hud'])->name('hud');

/*
 * The HUD's action surface. Each one is a single small JSON call, because the
 * palette updates optimistically and never waits for a round trip to redraw.
 */
Route::prefix('clips')->name('clips.')->group(function () {
    Route::get('/', [ClipController::class, 'index'])->name('index');
    Route::post('{clip}/use', [ClipController::class, 'use'])->name('use');
    Route::post('{clip}/pin', [ClipController::class, 'pin'])->name('pin');
    Route::delete('{clip}', [ClipController::class, 'destroy'])->name('destroy');
});

// Phase 0 instrumentation: exercises the summon path without a keystroke.
Route::get('/debug/summon', function () {
    HudRequested::dispatch();

    return ['summoned' => true];
});
