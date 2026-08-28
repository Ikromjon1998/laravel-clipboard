<?php

use App\Events\HudRequested;
use App\Http\Controllers\ClipController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ClipController::class, 'popover'])->name('menubar');
Route::get('/menubar', [ClipController::class, 'popover'])->name('menubar.popover');
Route::get('/hud', [ClipController::class, 'hud'])->name('hud');

/*
 * The HUD's action surface. Each is a single small JSON call, because the
 * palette updates optimistically and never waits for a round trip to redraw.
 */
Route::prefix('clips')->name('clips.')->group(function () {
    Route::get('/', [ClipController::class, 'index'])->name('index');
    Route::post('{clip}/use', [ClipController::class, 'use'])->name('use');
    Route::post('{clip}/pin', [ClipController::class, 'pin'])->name('pin');
    Route::delete('{clip}', [ClipController::class, 'destroy'])->name('destroy');
});

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'show'])->name('show');
    Route::post('/', [SettingsController::class, 'update'])->name('update');
    Route::delete('history', [SettingsController::class, 'clear'])->name('clear');
    Route::post('onboarded', [SettingsController::class, 'completeOnboarding'])->name('onboarded');
});

// Exercises the summon path without synthesising a keystroke.
Route::get('/debug/summon', function () {
    HudRequested::dispatch();

    return ['summoned' => true];
});
