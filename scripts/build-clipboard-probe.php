<?php

declare(strict_types=1);

/**
 * Compiles the native pasteboard probe.
 *
 * The probe reads pasteboard types that Electron cannot see, which is what
 * stops this app recording copies from a password manager. Without it the
 * app still runs, but that protection is unavailable — so a failure here is
 * reported loudly and is never fatal.
 */
$root = dirname(__DIR__);
$source = $root.'/native/ClipboardProbe.swift';
$binary = $root.'/bin/clipboard-probe';

if (PHP_OS_FAMILY !== 'Darwin') {
    exit(0);
}

if (! is_file($source)) {
    fwrite(STDOUT, "  Probe source missing; skipping.\n");
    exit(0);
}

if (is_file($binary) && filemtime($binary) >= filemtime($source)) {
    exit(0);
}

exec('command -v swiftc', $found, $status);

if ($status !== 0) {
    fwrite(STDOUT, "  swiftc not found — skipping the pasteboard probe.\n");
    fwrite(STDOUT, "  Without it, copies from password managers are recorded like any other clip.\n");
    fwrite(STDOUT, "  Install Xcode Command Line Tools (xcode-select --install), then: composer build:probe\n");
    exit(0);
}

@mkdir($root.'/bin', 0o755, recursive: true);

exec(sprintf('swiftc -O -o %s %s 2>&1', escapeshellarg($binary), escapeshellarg($source)), $output, $status);

if ($status !== 0) {
    fwrite(STDOUT, "  Could not build the pasteboard probe:\n    ".implode("\n    ", $output)."\n");
    exit(0);
}

fwrite(STDOUT, "  Built the native pasteboard probe.\n");
