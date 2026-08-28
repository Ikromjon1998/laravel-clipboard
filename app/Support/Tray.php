<?php

namespace App\Support;

use App\Events\HistoryClearRequested;
use App\Events\PauseToggled;
use App\Events\SettingsRequested;
use Ikromjon\ClipboardCore\ClipboardHistory;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;

/**
 * The menu bar item and its right-click menu.
 *
 * The menu is rebuilt rather than mutated whenever state changes, because
 * the framework exposes no way to update a single item — and a Pause entry
 * whose checkmark disagrees with reality is worse than no checkmark.
 */
class Tray
{
    private const ICON = 'icons/menuBarIconTemplate.png';

    private const WIDTH = 360;

    private const HEIGHT = 460;

    public static function create(): void
    {
        MenuBar::create()
            ->icon(resource_path(self::ICON))
            ->tooltip(config('app.name'))
            ->width(self::WIDTH)
            ->height(self::HEIGHT)
            ->vibrancy('sidebar')
            ->route('menubar.popover')
            ->withContextMenu(self::contextMenu());
    }

    /** Re-attach the menu so its checkmarks reflect current state. */
    public static function refresh(): void
    {
        MenuBar::contextMenu(self::contextMenu());
        self::reflectPauseState();
    }

    /**
     * The tray icon is the only always-visible surface, so paused state is
     * shown there rather than only inside a menu the user has to open.
     */
    public static function reflectPauseState(): void
    {
        MenuBar::tooltip(
            self::history()->isPaused()
                ? config('app.name').' — paused'
                : config('app.name')
        );
    }

    private static function contextMenu(): \Native\Desktop\Menu\Menu
    {
        $paused = self::history()->isPaused();
        $count = self::history()->count();

        return Menu::make(
            Menu::label($count === 1 ? '1 clip' : "{$count} clips"),
            Menu::separator(),
            Menu::checkbox('Pause capture', $paused)->event(PauseToggled::class),
            Menu::label('Clear history…')->event(HistoryClearRequested::class),
            Menu::separator(),
            Menu::label('Settings…')->event(SettingsRequested::class),
            Menu::separator(),
            Menu::quit('Quit '.config('app.name')),
        );
    }

    private static function history(): ClipboardHistory
    {
        return app(ClipboardHistory::class);
    }
}
