<?php

namespace App\Providers;

use App\Events\HudRequested;
use App\Support\Hud;
use App\Support\Preferences;
use App\Support\Tray;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Facades\GlobalShortcut;
use Native\Desktop\Facades\Window;

/**
 * One-time native setup. This boots in the app-boot process only, so it
 * creates windows, the tray item, shortcuts and long-lived processes —
 * event listeners belong in AppServiceProvider, which boots per request.
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Preferences::applyToConfig();

        Tray::create();
        $this->hudWindow();
        $this->shortcuts();
        $this->watcher();
    }

    /** Created once and hidden, so summoning is a reposition plus a show. */
    private function hudWindow(): void
    {
        Window::open(Hud::ID)
            ->route('hud')
            ->width(Hud::WIDTH)
            ->height(Hud::HEIGHT)
            ->resizable(false)
            ->frameless()
            ->alwaysOnTop()
            ->skipTaskbar()
            ->hiddenInMissionControl()
            ->hasShadow()
            ->transparent();

        Window::hide(Hud::ID);
    }

    private function shortcuts(): void
    {
        GlobalShortcut::key(Preferences::hotkey())
            ->event(HudRequested::class)
            ->register();
    }

    /** The watcher runs as its own supervised process (architecture §3). */
    private function watcher(): void
    {
        ChildProcess::artisan('clipboard:watch', alias: 'watcher', persistent: true);
    }

    public function phpIni(): array
    {
        return [
            'memory_limit' => '128M',
            'display_errors' => '1',
        ];
    }
}
