<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>laravel-clipboard</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif;
            -webkit-font-smoothing: antialiased;
            background: transparent;
            color: #1d1d1f;
            user-select: none;
            padding: 16px;
            font-size: 13px;
        }
        @media (prefers-color-scheme: dark) { body { color: #f5f5f7; } }
        h1 { font-size: 14px; font-weight: 600; margin-bottom: 3px; }
        p  { color: rgba(140,140,145,1); line-height: 1.5; }
        .ok { color: #1f7a3d; font-weight: 600; }
        @media (prefers-color-scheme: dark) { .ok { color: #4cc272; } }
        ul { margin: 12px 0 0 16px; line-height: 1.7; }
        kbd {
            font: 11px ui-monospace, Menlo, monospace;
            background: rgba(128,128,128,0.2); border-radius: 4px; padding: 1px 5px;
        }
    </style>
</head>
<body>
    <h1>laravel-clipboard <span style="font-weight:400;color:rgba(140,140,145,1)">· Phase 0 spike</span></h1>
    <p class="ok">Menu bar window is alive.</p>
    <ul>
        <li>Press <kbd>⌘⇧V</kbd> to summon the HUD at your cursor</li>
        <li>Copy any text to exercise the watcher process</li>
        <li>Right-click this icon for the context menu</li>
    </ul>
</body>
</html>
