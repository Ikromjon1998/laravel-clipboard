<?php

namespace App\Providers;

use App\Events\HudRequested;
use App\Support\Hud;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Facades\GlobalShortcut;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;
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
        $this->menuBar();
        $this->hudWindow();
        $this->shortcuts();
        $this->watcher();
    }

    private function menuBar(): void
    {
        MenuBar::create()
            ->icon(resource_path('icons/menuBarIconTemplate.png'))
            ->tooltip('laravel-clipboard')
            ->width(360)
            ->height(440)
            ->vibrancy('sidebar')
            ->route('menubar.popover')
            ->withContextMenu(Menu::make(
                Menu::label('laravel-clipboard — Phase 0 spike'),
                Menu::separator(),
                Menu::quit('Quit'),
            ));
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
        GlobalShortcut::key('CmdOrCtrl+Shift+V')
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
