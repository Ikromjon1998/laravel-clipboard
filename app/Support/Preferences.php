<?php

namespace App\Support;

use Native\Desktop\Facades\App;
use Native\Desktop\Facades\Settings;

/**
 * User preferences, stored by NativePHP in the OS application-data
 * directory so they survive reinstalls of the app bundle.
 *
 * Reads are memoised per process: the Settings facade crosses into
 * Electron over HTTP, and the HUD asks for these on a path where every
 * millisecond is visible to the user.
 */
class Preferences
{
    public const HISTORY_LIMIT = 'history_limit';

    public const HOTKEY = 'hotkey';

    public const ONBOARDED = 'onboarded';

    public const DEFAULT_HOTKEY = 'CmdOrCtrl+Shift+V';

    public const DEFAULT_LIMIT = 100;

    /** Bounds chosen so the history stays scannable and the table stays small. */
    public const MIN_LIMIT = 10;

    public const MAX_LIMIT = 500;

    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function historyLimit(): int
    {
        $value = self::read(self::HISTORY_LIMIT);

        return is_numeric($value)
            ? self::clampLimit((int) $value)
            : self::DEFAULT_LIMIT;
    }

    public static function setHistoryLimit(int $limit): int
    {
        $limit = self::clampLimit($limit);
        self::write(self::HISTORY_LIMIT, $limit);

        return $limit;
    }

    public static function hotkey(): string
    {
        $value = self::read(self::HOTKEY);

        return is_string($value) && trim($value) !== '' ? $value : self::DEFAULT_HOTKEY;
    }

    public static function setHotkey(string $hotkey): void
    {
        self::write(self::HOTKEY, trim($hotkey));
    }

    public static function hasOnboarded(): bool
    {
        return (bool) self::read(self::ONBOARDED);
    }

    public static function markOnboarded(): void
    {
        self::write(self::ONBOARDED, true);
    }

    /**
     * Launch at login is owned by the OS rather than by our settings store,
     * so it is read back from the system instead of being mirrored.
     */
    public static function launchesAtLogin(): bool
    {
        try {
            return App::openAtLogin();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setLaunchesAtLogin(bool $enabled): bool
    {
        try {
            App::openAtLogin($enabled);

            return $enabled;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function clampLimit(int $limit): int
    {
        return max(self::MIN_LIMIT, min(self::MAX_LIMIT, $limit));
    }

    /** Apply stored preferences over the package defaults. */
    public static function applyToConfig(): void
    {
        config(['clipboard.limit' => self::historyLimit()]);
    }

    private static function read(string $key): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            return self::$cache[$key] = Settings::get($key);
        } catch (\Throwable) {
            // Settings live in Electron; a failed read simply means defaults.
            return self::$cache[$key] = null;
        }
    }

    private static function write(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;

        try {
            Settings::set($key, $value);
        } catch (\Throwable) {
            // Nothing useful to do here: the value is applied in-process and
            // will fall back to the default on next launch.
        }
    }
}
