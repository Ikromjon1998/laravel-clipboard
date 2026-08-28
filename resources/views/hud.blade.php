<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>laravel-clipboard</title>
    @vite('resources/js/app.js')
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            background: transparent;
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
            border-radius: 12px;
            overflow: hidden;
            border: 0.5px solid rgba(255, 255, 255, 0.14);
            background: rgba(30, 30, 32, 0.72);
            backdrop-filter: saturate(180%) blur(30px);
            color: #f5f5f7;
        }
        @media (prefers-color-scheme: light) {
            .panel {
                background: rgba(246, 246, 248, 0.72);
                border-color: rgba(0, 0, 0, 0.10);
                color: #1d1d1f;
            }
        }
        .search {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-bottom: 0.5px solid rgba(128, 128, 128, 0.28);
        }
        .search .glyph { opacity: 0.5; font-size: 11px; letter-spacing: 0.04em; }
        .search input {
            flex: 1;
            border: 0;
            outline: 0;
            background: transparent;
            font: inherit;
            font-size: 14px;
            color: inherit;
            user-select: text;
        }
        .search input::placeholder { color: rgba(140, 140, 145, 0.9); }
        .badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 5px;
            background: rgba(128, 128, 128, 0.22);
            color: rgba(160, 160, 165, 1);
            font-variant-numeric: tabular-nums;
        }
        .list { flex: 1; overflow-y: auto; padding: 5px; }
        .list::-webkit-scrollbar { width: 0; }
        .row {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 9px;
            border-radius: 7px;
            line-height: 1.35;
        }
        .row.sel { background: rgba(120, 120, 128, 0.30); }
        .row .txt { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row .pin { color: #ff6e5e; font-size: 10px; flex-shrink: 0; }
        .row .kind {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(150, 150, 155, 1);
            flex-shrink: 0;
        }
        .row .idx {
            font: 11px ui-monospace, Menlo, monospace;
            color: rgba(150, 150, 155, 0.85);
            flex-shrink: 0;
            min-width: 9px;
            text-align: right;
        }
        .empty { padding: 22px 14px; text-align: center; font-size: 12.5px; color: rgba(150, 150, 155, 1); }
        .foot {
            display: flex;
            gap: 12px;
            padding: 7px 13px;
            border-top: 0.5px solid rgba(128, 128, 128, 0.28);
            font-size: 10.5px;
            color: rgba(150, 150, 155, 1);
        }
        kbd {
            font: 10px ui-monospace, Menlo, monospace;
            background: rgba(128, 128, 128, 0.22);
            border-radius: 3px;
            padding: 1px 4px;
        }
    </style>
</head>
<body>
<div class="panel"
     x-data="hud(@js($clips), @js($paused))"
     x-init="boot()"
     @keydown.window.escape.prevent="dismiss()">

    <div class="search">
        <span class="glyph">⌘⇧V</span>
        <input x-ref="q"
               x-model="query"
               placeholder="Filter clips…"
               spellcheck="false"
               autocomplete="off"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.ctrl.n.prevent="move(1)"
               @keydown.ctrl.p.prevent="move(-1)"
               @keydown.enter.prevent="apply()"
               @keydown.alt.p.prevent="togglePin()"
               @keydown.backspace="query === '' && (forget(), $event.preventDefault())">
        <span class="badge" x-text="results.length + '/' + clips.length"></span>
    </div>

    <div class="list">
        <template x-for="(clip, i) in results" :key="clip.id">
            <div class="row"
                 :class="{ sel: i === cursor }"
                 @mouseenter="cursor = i"
                 @click="pick(i)">
                <span class="pin" x-show="clip.pinned">●</span>
                <span class="txt" x-text="clip.preview"></span>
                <span class="kind" x-show="clip.kind === 'url'">link</span>
                <span class="idx" x-text="i < 9 ? (i + 1) : ''"></span>
            </div>
        </template>

        <div class="empty" x-show="results.length === 0">
            <span x-show="clips.length === 0">Copy something — clips appear here as you go.</span>
            <span x-show="clips.length > 0">No match.</span>
        </div>
    </div>

    <div class="foot">
        <span><kbd>↑↓</kbd> move</span>
        <span><kbd>↩</kbd> copy</span>
        <span><kbd>⌥P</kbd> pin</span>
        <span><kbd>esc</kbd> close</span>
        <span style="margin-left: auto" x-text="paused ? 'paused' : status"></span>
    </div>
</div>
</body>
</html>
