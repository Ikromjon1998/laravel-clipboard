# laravel-clipboard

A lightweight, keyboard-first clipboard history manager for the macOS menu bar — built with [NativePHP](https://nativephp.com) and Laravel.

Press <kbd>⌘⇧V</kbd>, a palette appears under your cursor, you type a few characters, hit <kbd>↩</kbd>, and the clip is back on your clipboard. That is the whole product.

> **Status: in development.** The engine, tray, hotkey, palette, pinning and history all work; settings, onboarding and packaging do not exist yet. There is no release to download.

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

### Honest limitations

- **Memory.** This is Electron plus PHP. It idles at a fraction of a percent of CPU, but it will never be as small in RAM as a native Swift app like [Maccy](https://github.com/p0deje/Maccy). If RAM is your binding constraint, use a native app — that recommendation is not a joke.
- **Passwords.** Guards drop clips before anything reaches disk, and the nspasteboard concealed-type convention is honoured *if the source reports it* — but Electron cannot read custom pasteboard types, so today that convention is effectively unenforced. A native helper is planned. Until then, use the pause control around anything sensitive.
- **Polling.** macOS exposes no clipboard-change notification, so the watcher polls (250 ms–1 s, adaptive). A clip replaced faster than the current interval can be missed.

## Requirements

macOS 12 or later, PHP 8.3+, Composer, Node 20+.

Until `laravel-clipboard-core` is published to Packagist, `composer.json` resolves it through a path repository, so clone both repositories as siblings:

```
your-code/
├── laravel-clipboard
└── laravel-clipboard-core
```

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

**3. The bundled PHP binary can be extracted truncated.** `vendor/nativephp/desktop/resources/electron/php.js` unzips the static PHP binary through a streaming pipe and sets its permissions in the stream's close handler. If the process exits before that flushes, you get a short, non-executable binary and Electron dies with `spawn … EACCES`. Extracting synchronously fixes it:

```js
// after ensureDirSync(binaryDestDir), before the yauzl block
execFileSync('ditto', ['-xk', binarySrcDir, binaryDestDir], { stdio: 'inherit' })
fs.chmodSync(join(binaryDestDir, platform.phpBinary), 0o755)
process.exit(0)
```

This edit lives in `vendor/`, so it does not survive `composer install`. A fix is going upstream.

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
