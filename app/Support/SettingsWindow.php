<?php

namespace App\Support;

use Native\Desktop\Facades\Window;

/**
 * The settings window, opened on demand rather than pre-warmed.
 *
 * Unlike the HUD, nobody summons this in the middle of a thought, so the
 * cost of building it on first open is invisible and not worth the memory
 * of keeping a second window resident.
 */
class SettingsWindow
{
    public const ID = 'settings';

    public static function open(): void
    {
        // Re-opening an existing window would create a duplicate, so an
        // already-open settings window is raised instead.
        if (self::isOpen()) {
            Window::show(self::ID);
            Window::current();

            return;
        }

        Window::open(self::ID)
            ->route('settings.show')
            ->title('Settings')
            ->width(460)
            ->height(560)
            ->resizable(false)
            ->titleBarHidden()
            ->backgroundColor('#00000000');
    }

    public static function close(): void
    {
        if (self::isOpen()) {
            Window::close(self::ID);
        }
    }

    private static function isOpen(): bool
    {
        try {
            foreach (Window::all() as $window) {
                $id = is_array($window) ? ($window['id'] ?? null) : ($window->id ?? null);

                if ($id === self::ID) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
