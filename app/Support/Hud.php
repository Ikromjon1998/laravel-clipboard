<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Screen;
use Native\Desktop\Facades\Window;

/**
 * Placement and visibility for the floating search palette.
 *
 * The window is created once at app boot and only ever hidden/shown, so a
 * summon costs a reposition plus a show rather than a cold window build.
 */
class Hud
{
    public const ID = 'hud';

    public const WIDTH = 420;

    public const HEIGHT = 380;

    public static function summon(): void
    {
        $t0 = microtime(true);

        $cursor = Screen::cursorPosition();
        $tCursor = microtime(true);

        [$x, $y] = self::place((int) $cursor->x, (int) $cursor->y);
        Window::position($x, $y, false, self::ID);
        $tPosition = microtime(true);

        Window::show(self::ID);
        $tShow = microtime(true);

        Log::info('hud.summon', [
            'cursor' => [$cursor->x, $cursor->y],
            'placed' => [$x, $y],
            'ms_cursor' => round(($tCursor - $t0) * 1000, 1),
            'ms_position' => round(($tPosition - $tCursor) * 1000, 1),
            'ms_show' => round(($tShow - $tPosition) * 1000, 1),
            'ms_total' => round(($tShow - $t0) * 1000, 1),
        ]);
    }

    public static function dismiss(): void
    {
        Window::hide(self::ID);
    }

    /**
     * Offset slightly below-right of the cursor, then pull back inside the
     * display's work area so the palette never lands off-screen or under the Dock.
     */
    private static function place(int $cursorX, int $cursorY): array
    {
        $x = $cursorX + 8;
        $y = $cursorY + 12;

        $area = self::workArea($cursorX, $cursorY);

        if (! $area) {
            return [$x, $y];
        }

        $left = (int) ($area['x'] ?? 0);
        $top = (int) ($area['y'] ?? 0);
        $right = $left + (int) ($area['width'] ?? 0);
        $bottom = $top + (int) ($area['height'] ?? 0);

        return [
            min(max($x, $left), max($left, $right - self::WIDTH)),
            min(max($y, $top), max($top, $bottom - self::HEIGHT)),
        ];
    }

    /** The work area of whichever display currently contains the cursor. */
    private static function workArea(int $x, int $y): ?array
    {
        try {
            foreach (Screen::displays() as $display) {
                $bounds = is_array($display) ? ($display['bounds'] ?? null) : ($display->bounds ?? null);
                $bounds = is_object($bounds) ? (array) $bounds : $bounds;

                if (! is_array($bounds)) {
                    continue;
                }

                $withinX = $x >= $bounds['x'] && $x < $bounds['x'] + $bounds['width'];
                $withinY = $y >= $bounds['y'] && $y < $bounds['y'] + $bounds['height'];

                if ($withinX && $withinY) {
                    $area = is_array($display) ? ($display['workArea'] ?? null) : ($display->workArea ?? null);

                    return is_object($area) ? (array) $area : $area;
                }
            }

            $primary = Screen::primary();
            $area = is_array($primary) ? ($primary['workArea'] ?? null) : ($primary->workArea ?? null);

            return is_object($area) ? (array) $area : $area;
        } catch (\Throwable $e) {
            Log::warning('hud.workarea_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
