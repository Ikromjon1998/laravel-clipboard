{{-- Shared window chrome: the macOS-native look every surface inherits. --}}
<style>
    :root {
        color-scheme: light dark;
        --ink: #1d1d1f;
        --muted: rgba(60, 60, 67, 0.6);
        --hairline: rgba(0, 0, 0, 0.10);
        --surface: rgba(246, 246, 248, 0.72);
        --row-hover: rgba(120, 120, 128, 0.14);
        --row-active: rgba(120, 120, 128, 0.28);
        --accent: #d93a2b;
        --control: rgba(120, 120, 128, 0.16);
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --ink: #f5f5f7;
            --muted: rgba(235, 235, 245, 0.6);
            --hairline: rgba(255, 255, 255, 0.12);
            --surface: rgba(30, 30, 32, 0.72);
            --row-hover: rgba(120, 120, 128, 0.22);
            --row-active: rgba(120, 120, 128, 0.36);
            --accent: #ff6e5e;
            --control: rgba(120, 120, 128, 0.28);
        }
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        height: 100%;
        background: transparent;
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif;
        font-size: 13px;
        -webkit-font-smoothing: antialiased;
        user-select: none;
        cursor: default;
        overflow: hidden;
    }
    .panel {
        height: 100%;
        display: flex;
        flex-direction: column;
        background: var(--surface);
        backdrop-filter: saturate(180%) blur(30px);
    }
    .panel.rounded { border-radius: 12px; overflow: hidden; border: 0.5px solid var(--hairline); }
    .scroll { flex: 1; overflow-y: auto; }
    .scroll::-webkit-scrollbar { width: 0; }
    .muted { color: var(--muted); }
    kbd {
        font: 10px ui-monospace, Menlo, monospace;
        background: var(--control);
        border-radius: 3px;
        padding: 1px 4px;
    }
    button, input[type="text"], input[type="number"], select {
        font: inherit;
        color: inherit;
    }
    .btn {
        border: 0;
        border-radius: 6px;
        background: var(--control);
        padding: 5px 11px;
        font-size: 12px;
        cursor: default;
    }
    .btn:hover { background: var(--row-hover); }
    .btn:active { background: var(--row-active); }
    .btn.danger { color: var(--accent); }
    .btn:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
    @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
</style>
