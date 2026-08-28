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
composer install
npm install
npm run build
php artisan native:migrate    # NOT `php artisan migrate` — see below
php artisan native:run
```

### Known setup issues

These are rough edges in the toolchain, not in this app. Each one cost real debugging time, so they are written down.

**1. `php artisan migrate` migrates the wrong database.** NativePHP uses its own `nativephp` connection. Use `php artisan native:migrate`, or you will migrate `database/database.sqlite` and the app will report `no such table: clips`.

**2. Electron's binary may not download.** This project ships an `.npmrc` with `ignore-scripts=true`, so npm will not run package install scripts — including the one that downloads the Electron binary. If `native:run` cannot find Electron:

```bash
cd vendor/nativephp/desktop/resources/electron
npm install-scripts approve electron esbuild fsevents
```

**3. The bundled PHP binary can be extracted truncated (`spawn … EACCES`).** NativePHP unzips the static PHP binary with yauzl, and **yauzl 3.2.0 stalls partway through a large deflated entry** — it stops emitting data and never fires `end`, `error` or `close`. Because the file mode is set inside that `close` handler, the binary is left both short and non-executable, so Electron cannot spawn it. The error names permissions, but the cause is extraction.

On the shipped `php-8.4` archive it stops at 68,299,511 of 68,318,496 bytes, every time. yauzl 3.4.0 reads it in full, and NativePHP already allows `^3.2.0` — only its committed lockfile pins the broken release, so no source changes are needed.

This repository repairs it automatically after `composer install`. If you hit it anyway, run it directly:

```bash
composer fix:nativephp
```

Reported upstream; the workaround can be deleted once NativePHP's lockfile is refreshed.

**4. `native:run` needs a TTY.** In a non-interactive shell, wrap it: `script -q /dev/null php artisan native:run`.

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

## Development

```bash
php artisan native:run     # run the app
npm run dev                # asset watching
```

The engine's own tests live in [`laravel-clipboard-core`](https://github.com/Ikromjon1998/laravel-clipboard-core) and run on Linux with no desktop required.

## License

MIT. See [LICENSE](LICENSE).
