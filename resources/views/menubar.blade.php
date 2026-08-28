<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite('resources/js/app.js')
    @include('partials.chrome')
    <style>
        .search {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-bottom: 0.5px solid var(--hairline);
            flex-shrink: 0;
        }
        .search input {
            flex: 1;
            border: 0;
            outline: 0;
            background: transparent;
            font: inherit;
            font-size: 13.5px;
            user-select: text;
        }
        .search input::placeholder { color: var(--muted); }
        .list { padding: 5px; }
        .row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 7px;
            line-height: 1.35;
        }
        .row:hover { background: var(--row-hover); }
        .row .txt { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row .pin { color: var(--accent); font-size: 10px; flex-shrink: 0; }
        .row .kind {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            flex-shrink: 0;
        }
        .row .tools { display: none; gap: 2px; flex-shrink: 0; }
        .row:hover .tools { display: flex; }
        .tool {
            border: 0;
            background: transparent;
            color: var(--muted);
            font-size: 11px;
            padding: 2px 5px;
            border-radius: 4px;
            cursor: default;
        }
        .tool:hover { background: var(--row-active); color: var(--ink); }
        .copied { color: var(--accent); font-size: 10.5px; flex-shrink: 0; }
        .empty { padding: 30px 18px; text-align: center; color: var(--muted); font-size: 12.5px; line-height: 1.6; }
        .foot {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 13px;
            border-top: 0.5px solid var(--hairline);
            font-size: 11px;
            color: var(--muted);
            flex-shrink: 0;
        }
        .paused-pill {
            color: var(--accent);
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .onboard {
            margin: 10px;
            padding: 12px 14px;
            border-radius: 9px;
            background: var(--control);
            font-size: 12px;
            line-height: 1.55;
            color: var(--muted);
        }
        .onboard strong { color: var(--ink); font-weight: 600; }
    </style>
</head>
<body>
<div class="panel" x-data="popover(@js($clips), @js($paused), @js($onboarded))" x-init="boot()">

    <div class="search">
        <span class="muted" style="font-size:11px">{{ config('app.name') }}</span>
        <input x-model="query" placeholder="Filter…" spellcheck="false" autocomplete="off">
        <span class="muted" style="font-size:11px" x-text="results.length"></span>
    </div>

    @unless ($onboarded)
        <div class="onboard">
            <strong>macOS will ask permission the first time a clip is read.</strong>
            Choose Allow — nothing is captured until you do.
        </div>
    @endunless

    <div class="scroll">
        <div class="list">
            <template x-for="(clip, i) in results" :key="clip.id">
                <div class="row" @click="copy(i)">
                    <span class="pin" x-show="clip.pinned">●</span>
                    <span class="txt" x-text="clip.preview"></span>
                    <span class="copied" x-show="copiedId === clip.id">Copied</span>
                    <span class="kind" x-show="clip.kind === 'url' && copiedId !== clip.id">link</span>
                    <span class="tools" x-show="copiedId !== clip.id">
                        <button class="tool" @click.stop="pinAt(i)"
                                :title="clip.pinned ? 'Unpin' : 'Pin'"
                                x-text="clip.pinned ? 'Unpin' : 'Pin'"></button>
                        <button class="tool" @click.stop="forgetAt(i)" title="Delete">✕</button>
                    </span>
                </div>
            </template>
        </div>

        <div class="empty" x-show="results.length === 0">
            <template x-if="clips.length === 0">
                <span>Nothing captured yet.<br>Copy some text and it will appear here.</span>
            </template>
            <template x-if="clips.length > 0">
                <span>No clip matches that.</span>
            </template>
        </div>
    </div>

    <div class="foot">
        <span>Press <kbd>⌘⇧V</kbd> anywhere</span>
        <span style="margin-left:auto" class="paused-pill" x-show="paused">Paused</span>
    </div>
</div>
</body>
</html>
