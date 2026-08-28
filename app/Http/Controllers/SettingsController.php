<?php

namespace App\Http\Controllers;

use App\Events\HudRequested;
use App\Support\Preferences;
use App\Support\Tray;
use Ikromjon\ClipboardCore\ClipboardHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Facades\GlobalShortcut;

class SettingsController extends Controller
{
    public function __construct(private readonly ClipboardHistory $history) {}

    public function show(): View
    {
        return view('settings', [
            'settings' => $this->current(),
            'onboarded' => Preferences::hasOnboarded(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'history_limit' => ['sometimes', 'integer', 'between:'.Preferences::MIN_LIMIT.','.Preferences::MAX_LIMIT],
            'hotkey' => ['sometimes', 'string', 'max:60'],
            'launch_at_login' => ['sometimes', 'boolean'],
            'paused' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('history_limit', $data)) {
            $this->changeHistoryLimit((int) $data['history_limit']);
        }

        if (array_key_exists('hotkey', $data)) {
            $this->changeHotkey($data['hotkey']);
        }

        if (array_key_exists('launch_at_login', $data)) {
            Preferences::setLaunchesAtLogin((bool) $data['launch_at_login']);
        }

        if (array_key_exists('paused', $data)) {
            $data['paused'] ? $this->history->pause() : $this->history->resume();
        }

        Tray::refresh();

        return response()->json($this->current());
    }

    public function clear(): JsonResponse
    {
        $removed = $this->history->clear();
        Tray::refresh();

        return response()->json(['removed' => $removed] + $this->current());
    }

    public function completeOnboarding(): JsonResponse
    {
        Preferences::markOnboarded();
        Tray::refresh();

        return response()->json(['onboarded' => true] + $this->current());
    }

    /**
     * The engine reads its limit from config at boot, and the watcher is a
     * separate long-running process — so a new limit only takes effect once
     * that process restarts.
     */
    private function changeHistoryLimit(int $limit): void
    {
        $applied = Preferences::setHistoryLimit($limit);
        config(['clipboard.limit' => $applied]);

        try {
            ChildProcess::restart('watcher');
        } catch (\Throwable) {
            // Worst case the new limit applies at next launch; pruning to the
            // old bound in the meantime is harmless.
        }
    }

    private function changeHotkey(string $hotkey): void
    {
        $previous = Preferences::hotkey();

        if ($hotkey === $previous) {
            return;
        }

        try {
            GlobalShortcut::key($previous)->unregister();
            GlobalShortcut::key($hotkey)->event(HudRequested::class)->register();
            Preferences::setHotkey($hotkey);
        } catch (\Throwable) {
            // Re-register the old combination so the app is never left with
            // no way to summon the palette.
            GlobalShortcut::key($previous)->event(HudRequested::class)->register();
        }
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        return [
            'history_limit' => Preferences::historyLimit(),
            'hotkey' => Preferences::hotkey(),
            'launch_at_login' => Preferences::launchesAtLogin(),
            'paused' => $this->history->isPaused(),
            'clip_count' => $this->history->count(),
        ];
    }
}
