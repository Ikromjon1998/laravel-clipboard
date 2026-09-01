<?php

declare(strict_types=1);

/**
 * Works around a yauzl bug that truncates two different archives during a
 * NativePHP install.
 *
 * yauzl stalls partway through inflating a large deflated entry: it stops
 * emitting data and never fires `end`, `error` or `close`. Two things in the
 * install path are extracted with it, and each fails in a way that names
 * something other than the extraction:
 *
 * 1. The bundled PHP binary, unzipped with the top-level yauzl. The file mode
 *    is set inside the `close` handler that never runs, so the binary ends up
 *    short and still mode 644, and the app dies at boot with `spawn … EACCES`
 *    — an error about permissions, not about the truncation behind it.
 *    Measured on the shipped php-8.4 archive: 3.2.0 stops at 68,299,511 of
 *    68,318,496 bytes every time. 3.4.0 reads it in full.
 *
 * 2. The Electron runtime itself, unzipped by `extract-zip`. That package
 *    carries its own nested yauzl 2.10.0, which Node resolves in preference to
 *    the fixed top-level copy, so upgrading the top-level one alone does not
 *    reach it. It writes the first entry of the archive and stops — leaving a
 *    `dist/` holding LICENSES.chromium.html and nothing else, while
 *    `install.js` still exits 0. The app then fails with a missing
 *    `Electron.app/Contents/Info.plist`. Deleting the nested copy lets
 *    extract-zip resolve the fixed top-level yauzl, and all 585 entries land.
 *
 * NativePHP's package.json already allows ^3.2.0, so nothing but its committed
 * lockfile pins the broken release. Installing the fixed version satisfies the
 * existing range and needs no source changes.
 *
 * Ordering matters: this must run *after* `php artisan native:install`, which
 * is what creates the Electron node_modules. Run before that, it has nothing
 * to inspect and correctly does nothing.
 *
 * Delete this script once the upstream lockfile is refreshed.
 */
final class EnsureNativePhpYauzl
{
    private const ELECTRON_DIR = 'vendor/nativephp/desktop/resources/electron';

    private const MINIMUM = '3.4.0';

    private string $electron;

    public function __construct()
    {
        $this->electron = dirname(__DIR__).'/'.self::ELECTRON_DIR;
    }

    public static function run(): int
    {
        return (new self)->handle();
    }

    public function handle(): int
    {
        if (! is_dir($this->electron)) {
            return $this->note('NativePHP Electron scaffolding not present — nothing to check.');
        }

        if (! is_file($this->path('node_modules/yauzl/package.json'))) {
            // native:install is what creates these, so this is the normal
            // state right after a bare composer install.
            return $this->note('Electron dependencies not installed yet; re-run this after `php artisan native:install`.');
        }

        $this->repairTopLevelYauzl();
        $this->repairNestedYauzl();
        $this->repairElectronRuntime();

        return 0;
    }

    /** The copy that extracts the bundled PHP binary. */
    private function repairTopLevelYauzl(): void
    {
        $manifest = $this->path('node_modules/yauzl/package.json');
        $installed = $this->versionFrom($manifest);

        if ($installed === null) {
            $this->note('Could not read the installed yauzl version — skipping.');

            return;
        }

        if (version_compare($installed, self::MINIMUM, '>=')) {
            return;
        }

        fwrite(STDOUT, "  yauzl {$installed} truncates NativePHP's PHP binary; installing ".self::MINIMUM."…\n");

        exec(sprintf(
            'cd %s && npm install yauzl@^%s --no-audit --no-fund --silent 2>&1',
            escapeshellarg($this->electron),
            self::MINIMUM
        ), $output, $status);

        if ($status !== 0) {
            $this->note(
                'Automatic upgrade failed. Run this by hand, then start the app again:'."\n".
                '    cd '.self::ELECTRON_DIR.' && npm install yauzl@^'.self::MINIMUM
            );

            return;
        }

        fwrite(STDOUT, '  yauzl is now '.($this->versionFrom($manifest) ?? 'unknown').".\n");
    }

    /**
     * extract-zip ships its own yauzl, and Node prefers a nested copy over the
     * one we just fixed. Removing it is what makes the upgrade above apply to
     * the Electron download too; extract-zip then resolves the top-level copy.
     */
    private function repairNestedYauzl(): void
    {
        $nested = $this->path('node_modules/extract-zip/node_modules/yauzl');
        $installed = $this->versionFrom($nested.'/package.json');

        if ($installed === null || version_compare($installed, self::MINIMUM, '>=')) {
            return;
        }

        fwrite(STDOUT, "  extract-zip carries yauzl {$installed}, which truncates the Electron download; removing it so the fixed copy is used…\n");

        exec(sprintf('rm -rf %s 2>&1', escapeshellarg($nested)), $output, $status);

        if ($status !== 0) {
            $this->note(
                'Could not remove it. Run this by hand, then start the app again:'."\n".
                '    rm -rf '.self::ELECTRON_DIR.'/node_modules/extract-zip/node_modules/yauzl'
            );
        }
    }

    /**
     * A truncated extraction leaves a dist/ that exists but holds no runtime,
     * and Electron's installer exits 0 either way — so the presence of the
     * directory proves nothing. Re-running the installer once yauzl is fixed
     * is what actually lands the binary.
     */
    private function repairElectronRuntime(): void
    {
        if (! is_file($this->path('node_modules/electron/install.js'))) {
            return;
        }

        if ($this->electronRuntimeIsComplete()) {
            return;
        }

        fwrite(STDOUT, "  The Electron runtime was extracted incompletely; unpacking it again…\n");

        // The archive is already in the local Electron cache, so this is an
        // extraction rather than a second 111 MB download.
        exec(sprintf(
            'cd %s && node node_modules/electron/install.js 2>&1',
            escapeshellarg($this->electron)
        ), $output, $status);

        if ($status === 0 && $this->electronRuntimeIsComplete()) {
            fwrite(STDOUT, "  Electron runtime restored.\n");

            return;
        }

        $this->note(
            'Could not restore it. Run this by hand, then start the app again:'."\n".
            '    cd '.self::ELECTRON_DIR.' && rm -rf node_modules/electron/dist && node node_modules/electron/install.js'
        );
    }

    /**
     * Electron records the runtime's location in path.txt only once it has
     * unpacked it, so a present-and-resolvable path.txt is the honest check.
     */
    private function electronRuntimeIsComplete(): bool
    {
        $pointer = $this->path('node_modules/electron/path.txt');

        if (! is_file($pointer)) {
            return false;
        }

        $relative = trim((string) file_get_contents($pointer));

        return $relative !== ''
            && is_file($this->path('node_modules/electron/dist/'.$relative));
    }

    private function path(string $relative): string
    {
        return $this->electron.'/'.$relative;
    }

    private function versionFrom(string $manifest): ?string
    {
        if (! is_file($manifest)) {
            return null;
        }

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
    private function note(string $message): int
    {
        fwrite(STDOUT, '  '.$message."\n");

        return 0;
    }
}

exit(EnsureNativePhpYauzl::run());
