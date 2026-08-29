/**
 * Run a callback once NativePHP's bridge is available.
 *
 * `native:init` is dispatched by a preload script that usually runs before
 * the bundle does, so a plain addEventListener registers too late and never
 * fires. Every window that only listened for the event was therefore never
 * wired up: no live updates, no reaction to being shown.
 *
 * Checking for the object first covers the common case; the listener covers
 * the race where the bundle happens to win.
 */
export function onNativeReady(callback) {
    if (typeof window.Native !== 'undefined') {
        callback(window.Native)

        return
    }

    window.addEventListener('native:init', () => callback(window.Native), { once: true })
}
