# laravel-clipboard

A lightweight, keyboard-first clipboard history manager for the macOS menu bar — built with [NativePHP](https://nativephp.com) and Laravel.

Press <kbd>⌘⇧V</kbd>, a palette appears under your cursor, you type a few characters, hit <kbd>↩</kbd>, and the clip is back on your clipboard. That is the whole product.

> **Status: in development.** The engine, tray, hotkey, palette, pinning, search, settings and password-manager exclusion all work. Packaging does not — there is no signed release to download yet, so for now you run it from source.

## Why it exists

Most clipboard managers are closed-source, and the good ones cost more than a utility that watches a text buffer should. This one is open source, deliberately text-only, and built to be readable — it doubles as a worked example of what a real NativePHP desktop app looks like.

The clipboard engine lives in its own package, [`laravel-clipboard-core`](https://github.com/Ikromjon1998/laravel-clipboard-core), so anyone can build different clipboard tooling on the same foundation without adopting this app's UI.

## What it does

- Captures text and links only — no images, no files
- Deduplicates: re-copying something resurfaces it instead of creating a duplicate
- Keeps a bounded history (100 clips by default) and prunes the rest automatically
- Pinned clips are exempt from pruning
- Filters as you type, entirely in memory — no request between keystroke and result
- Lives in the menu bar with no dock icon
- Skips anything a password manager marks as concealed, so those copies are never stored
- Pause capture from the tray, and choose your own summon shortcut

### Honest limitations

- **Memory.** This is Electron plus PHP. It idles at a fraction of a percent of CPU, but it will never be as small in RAM as a native Swift app like [Maccy](https://github.com/p0deje/Maccy). If RAM is your binding constraint, use a native app — that recommendation is not a joke.
- **Passwords.** Copies from password managers are not recorded. A small native helper (`native/ClipboardProbe.swift`, built automatically on install) reads the [nspasteboard](https://nspasteboard.org) concealed-type markers that Electron cannot see, and never emits the text of a concealed item — so it does not reach PHP at all. This depends on the source application marking its contents correctly, which the major password managers do. If the helper cannot be built, the app still runs but that protection is gone, and it says so during install.
- **Polling.** macOS exposes no clipboard-change notification, so something must poll. With the native helper this is a `changeCount` comparison every 150 ms — a single integer, nothing read until it moves — so a clip has to be replaced within about a sixth of a second to be missed.

## Requirements

macOS 12 or later, PHP 8.3+, Composer, Node 20+.

Xcode Command Line Tools (`xcode-select --install`) are needed to build the pasteboard helper. Without them the app still installs and runs; it just cannot exclude password managers.

The clipboard engine is a published package — [`ikromjon/laravel-clipboard-core`](https://packagist.org/packages/ikromjon/laravel-clipboard-core) — so `composer install` pulls it like any other dependency.

## Getting it running

```bash
git clone https://github.com/Ikromjon1998/laravel-clipboard.git
cd laravel-clipboard
composer setup
php artisan native:run
```

`composer setup` creates `.env`, generates an application key, installs both dependency trees, works around the toolchain bugs described below, builds the pasteboard probe, compiles assets and migrates the app database. It is safe to re-run at any time.

The first run downloads an Electron runtime of about 110 MB, so give it a few minutes.

### Known toolchain issues

These are rough edges in the toolchain, not in this app. `composer setup` already handles them — they are written down because each one cost real debugging time, and because the errors they produce name the wrong culprit.

**1. `php artisan migrate` migrates the wrong database.** NativePHP uses its own `nativephp` connection, pointed at a database in your application-data directory. Use `php artisan native:migrate`, or you will migrate `database/database.sqlite` instead and the app will start and then report `no such table: clips`.

**2. Two archives are extracted with a broken unzipper.** NativePHP unzips with yauzl, and **yauzl stalls partway through inflating a large deflated entry** — it stops emitting data and never fires `end`, `error` or `close`. Two things in the install path go through it, and neither reports the real fault:

- **The bundled PHP binary**, via the top-level yauzl 3.2.0. The file mode is set inside the `close` handler that never runs, so the binary is left both short and non-executable. On the shipped `php-8.4` archive it stops at 68,299,511 of 68,318,496 bytes, every time. The app dies at boot with `spawn … EACCES`, which points at permissions rather than at the extraction.
- **The Electron runtime**, via `extract-zip`. That package carries its own nested yauzl 2.10.0, and Node resolves a nested copy in preference to the fixed top-level one — so upgrading the top-level version alone does not reach it. It writes the first entry of the archive and stops, leaving a `dist/` that contains `LICENSES.chromium.html` and nothing else while `install.js` still exits 0. The app then fails with a missing `Electron.app/Contents/Info.plist`.

yauzl 3.4.0 reads both archives in full, and NativePHP's `package.json` already allows `^3.2.0` — only its committed lockfile pins the broken release, so no source changes are needed. `composer setup` installs the fixed version, removes the nested copy so `extract-zip` resolves it too, and re-extracts the runtime if it was truncated. To run that repair on its own:

```bash
composer fix:nativephp
```

Reported upstream; the workaround can be deleted once NativePHP's lockfile is refreshed.

**3. `native:run` needs a TTY.** In a non-interactive shell, wrap it: `script -q /dev/null php artisan native:run`.

## Architecture

Four processes, one SQLite database:

| Piece | Role |
| --- | --- |
| Electron shell | Tray, windows, and the loopback API the facades call |
| Main Laravel process | Declares the tray, hotkey and windows; supervises the watcher |
| Watcher process | `clipboard:watch` from core, run as a persistent child process so a crash cannot take the UI down |
| Windows | A pre-warmed frameless HUD and the menu-bar popover |

Two conventions matter if you are reading the code:

- **Event listeners belong in `AppServiceProvider`, not `NativeAppServiceProvider`.** Electron delivers native events as ordinary HTTP requests, so listeners registered in the boot-only provider never fire. This is not in the NativePHP docs.
- **The HUD is pre-warmed.** It is created once at boot and hidden, so summoning it is a reposition plus a show — measured at 4–13 ms rather than the cost of building a window.

## Building a release

```bash
composer build:mac
```

That builds the pasteboard probe, regenerates the icon, compiles assets, and packages the app — producing a `.dmg`, a `.zip`, and `latest-mac.yml` (the manifest the auto-updater reads) in `nativephp/electron/dist/`.

Without Apple credentials the build still succeeds, but falls back to an **ad-hoc signature**. Such a build runs only on the machine that produced it; anywhere else macOS reports *"app is damaged and can't be opened"*. To distribute it you need an [Apple Developer Program](https://developer.apple.com/programs/) membership ($99/year), then in `.env`:

```
NATIVEPHP_APPLE_ID=you@example.com
NATIVEPHP_APPLE_ID_PASS=xxxx-xxxx-xxxx-xxxx   # app-specific password
NATIVEPHP_APPLE_TEAM_ID=XXXXXXXXXX
```

With those present the same command signs with your Developer ID, submits to Apple for notarization, and staples the result.

Two things to keep straight when releasing:

- **Bump `NATIVEPHP_APP_VERSION`.** The updater compares versions, so shipping twice with the same one means nobody updates.
- **Do not change `NATIVEPHP_APP_ID`.** macOS keys preferences, permissions and update eligibility to it; changing it orphans every existing install.

## Development

```bash
php artisan native:run     # run the app
npm run dev                # asset watching
```

The engine's own tests live in [`laravel-clipboard-core`](https://github.com/Ikromjon1998/laravel-clipboard-core) and run on Linux with no desktop required.

## License

MIT. See [LICENSE](LICENSE).
