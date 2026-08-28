<?php

declare(strict_types=1);

/**
 * Works around a yauzl bug that leaves NativePHP's bundled PHP binary truncated.
 *
 * yauzl 3.2.0 stalls partway through inflating a large deflated entry: it stops
 * emitting data and never fires `end`, `error` or `close`. NativePHP extracts
 * the bundled PHP binary with it and sets the file mode inside that `close`
 * handler, so the binary ends up short and still mode 644. Electron then fails
 * to spawn it and the app dies at boot with `spawn … EACCES`, which points at
 * permissions rather than at the extraction that actually failed.
 *
 * Measured on the shipped php-8.4 archive: yauzl 3.2.0 stops at 68,299,511 of
 * 68,318,496 bytes every time. yauzl 3.4.0 reads it in full.
 *
 * NativePHP's package.json already allows ^3.2.0, so nothing but its committed
 * lockfile pins the broken release. Installing the fixed version satisfies the
 * existing range and needs no source changes.
 *
 * Delete this script once the upstream lockfile is refreshed.
 */
final class EnsureNativePhpYauzl
{
    private const ELECTRON_DIR = 'vendor/nativephp/desktop/resources/electron';

    private const MINIMUM = '3.4.0';

    public static function run(): int
    {
        $electron = dirname(__DIR__).'/'.self::ELECTRON_DIR;
        $manifest = $electron.'/node_modules/yauzl/package.json';

        if (! is_dir($electron)) {
            return self::note('NativePHP Electron scaffolding not present — nothing to check.');
        }

        if (! is_file($manifest)) {
            // Dependencies are installed later by native:install, so this is
            // the normal state right after a fresh composer install.
            return self::note('Electron dependencies not installed yet; re-run this after `php artisan native:install`.');
        }

        $installed = self::versionFrom($manifest);

        if ($installed === null) {
            return self::note('Could not read the installed yauzl version — skipping.');
        }

        if (version_compare($installed, self::MINIMUM, '>=')) {
            return 0;
        }

        fwrite(STDOUT, "  yauzl {$installed} truncates NativePHP's PHP binary; installing ".self::MINIMUM."…\n");

        $command = sprintf(
            'cd %s && npm install yauzl@^%s --no-audit --no-fund --silent 2>&1',
            escapeshellarg($electron),
            self::MINIMUM
        );

        exec($command, $output, $status);

        if ($status !== 0) {
            return self::note(
                'Automatic upgrade failed. Run this by hand, then start the app again:'."\n".
                '    cd '.self::ELECTRON_DIR.' && npm install yauzl@^'.self::MINIMUM
            );
        }

        $now = self::versionFrom($manifest) ?? 'unknown';
        fwrite(STDOUT, "  yauzl is now {$now}.\n");

        return 0;
    }

    private static function versionFrom(string $manifest): ?string
    {
        $raw = file_get_contents($manifest);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && isset($decoded['version']) && is_string($decoded['version'])
            ? $decoded['version']
            : null;
    }

    /** Never fatal: a missing workaround must not block installing the app. */
    private static function note(string $message): int
    {
        fwrite(STDOUT, '  '.$message."\n");

        return 0;
    }
}

exit(EnsureNativePhpYauzl::run());
