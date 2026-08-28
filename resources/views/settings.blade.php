<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Settings</title>
    @vite('resources/js/app.js')
    @include('partials.chrome')
    <style>
        .titlebar {
            -webkit-app-region: drag;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 0.5px solid var(--hairline);
            flex-shrink: 0;
        }
        .body { padding: 18px 22px 22px; overflow-y: auto; }
        .body::-webkit-scrollbar { width: 0; }
        section + section { margin-top: 22px; }
        h2 {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 9px;
        }
        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 9px 0;
            border-bottom: 0.5px solid var(--hairline);
        }
        .row:last-child { border-bottom: 0; }
        .row .label { flex: 1; }
        .row .hint { display: block; font-size: 11px; color: var(--muted); margin-top: 2px; line-height: 1.35; }
        input[type="number"], input[type="text"] {
            width: 92px;
            border: 0.5px solid var(--hairline);
            border-radius: 6px;
            background: var(--control);
            padding: 4px 8px;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        input.hotkey { width: 160px; text-align: center; font-family: ui-monospace, Menlo, monospace; font-size: 12px; }
        input.hotkey.recording { outline: 2px solid var(--accent); outline-offset: 1px; color: var(--muted); }
        .switch {
            appearance: none;
            width: 38px; height: 22px;
            border-radius: 11px;
            background: var(--control);
            position: relative;
            transition: background 0.15s ease;
            flex-shrink: 0;
        }
        .switch::after {
            content: "";
            position: absolute;
            top: 2px; left: 2px;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: transform 0.15s ease;
        }
        .switch:checked { background: var(--accent); }
        .switch:checked::after { transform: translateX(16px); }
        .saved {
            font-size: 11px;
            color: var(--muted);
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .saved.show { opacity: 1; }
        .note {
            font-size: 11.5px;
            line-height: 1.5;
            color: var(--muted);
            background: var(--control);
            border-radius: 8px;
            padding: 11px 13px;
        }
        .note strong { color: var(--ink); font-weight: 600; }
    </style>
</head>
<body>
<div class="panel" x-data="settings(@js($settings), @js($onboarded))" x-init="boot()">
    <div class="titlebar">Settings <span class="saved" :class="{ show: justSaved }" style="margin-left:8px">Saved</span></div>

    <div class="body">
        @unless ($onboarded)
            <section>
                <h2>First run</h2>
                <div class="note">
                    <strong>macOS will ask permission the first time a clip is read.</strong>
                    Choose <strong>Allow</strong>, and if you would rather not be asked again, set this app to
                    <strong>Allow&nbsp;Always</strong> under System&nbsp;Settings → Privacy&nbsp;&amp;&nbsp;Security → Pasteboard.
                    Nothing is captured until you grant it.
                    <div style="margin-top:10px">
                        <button class="btn" @click="finishOnboarding()">Got it</button>
                    </div>
                </div>
            </section>
        @endunless

        <section>
            <h2>Capture</h2>

            <div class="row">
                <span class="label">
                    Pause capture
                    <span class="hint">Nothing copied while paused is recorded, then or later.</span>
                </span>
                <input type="checkbox" class="switch" x-model="form.paused" @change="save('paused')">
            </div>

            <div class="row">
                <span class="label">
                    Clips to keep
                    <span class="hint">Oldest are dropped past this. Pinned clips never count and are never dropped.</span>
                </span>
                <input type="number" min="{{ \App\Support\Preferences::MIN_LIMIT }}"
                       max="{{ \App\Support\Preferences::MAX_LIMIT }}" step="10"
                       x-model.number="form.history_limit" @change="save('history_limit')">
            </div>
        </section>

        <section>
            <h2>Shortcut</h2>
            <div class="row">
                <span class="label">
                    Summon the palette
                    <span class="hint" x-text="recording ? 'Press the combination you want…' : 'Click to record a new shortcut.'"></span>
                </span>
                <input type="text" class="hotkey" readonly
                       :class="{ recording }"
                       :value="recording ? 'Listening…' : display(form.hotkey)"
                       @click="startRecording()"
                       @keydown.prevent.stop="capture($event)"
                       @blur="recording = false">
            </div>
        </section>

        <section>
            <h2>General</h2>
            <div class="row">
                <span class="label">
                    Launch at login
                    <span class="hint">A clipboard manager only helps if it is already running.</span>
                </span>
                <input type="checkbox" class="switch" x-model="form.launch_at_login" @change="save('launch_at_login')">
            </div>
        </section>

        <section>
            <h2>History</h2>
            <div class="row">
                <span class="label">
                    <span x-text="countLabel()"></span>
                    <span class="hint">Clearing keeps pinned clips. This cannot be undone.</span>
                </span>
                <button class="btn danger" @click="clearHistory()" x-text="confirming ? 'Really clear?' : 'Clear'"></button>
            </div>
        </section>
    </div>
</div>
</body>
</html>
