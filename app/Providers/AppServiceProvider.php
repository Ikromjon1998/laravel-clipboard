<?php

namespace App\Providers;

use App\Events\ClipboardUpdated;
use App\Events\HistoryClearRequested;
use App\Events\HudRequested;
use App\Events\PauseToggled;
use App\Events\SettingsRequested;
use App\Support\Hud;
use App\Support\Preferences;
use App\Support\SettingsWindow;
use App\Support\Tray;
use Ikromjon\ClipboardCore\ClipboardHistory;
use Ikromjon\ClipboardCore\Events\ClipCaptured;
use Ikromjon\ClipboardCore\Events\ClipsPruned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Events\ChildProcess\ErrorReceived;
use Native\Desktop\Events\ChildProcess\MessageReceived;
use Native\Desktop\Events\ChildProcess\ProcessExited;
use Native\Desktop\Events\Windows\WindowBlurred;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Native events arrive from Electron as ordinary HTTP requests, so their
     * listeners must be registered here rather than in NativeAppServiceProvider
     * (whose boot() runs once, in the app-boot process only).
     */
    public function boot(): void
    {
        // The watcher process boots through here too, which is how a changed
        // history limit reaches the engine after the watcher is restarted.
        Preferences::applyToConfig();

        $this->bridgeEngineEvents();
        $this->trayActions();
        $this->hudBehaviour();
        $this->watcherSupervision();
    }

    /**
     * The engine dispatches plain events; the desktop needs broadcast ones.
     * This is the only place the two vocabularies meet.
     */
    private function bridgeEngineEvents(): void
    {
        Event::listen(ClipCaptured::class, function (ClipCaptured $event): void {
            ClipboardUpdated::dispatch($event->clip);
            Tray::refresh();
        });

        Event::listen(ClipsPruned::class, fn () => Tray::refresh());
    }

    private function trayActions(): void
    {
        Event::listen(PauseToggled::class, function (): void {
            $paused = app(ClipboardHistory::class)->toggle();

            Log::info('watcher.pause_toggled', ['paused' => $paused]);
            Tray::refresh();
        });

        Event::listen(HistoryClearRequested::class, function (): void {
            $removed = app(ClipboardHistory::class)->clear();

            Log::info('history.cleared', ['removed' => $removed]);
            Tray::refresh();
        });

        Event::listen(SettingsRequested::class, fn () => SettingsWindow::open());
    }

    private function hudBehaviour(): void
    {
        Event::listen(HudRequested::class, fn () => Hud::summon());

        Event::listen(WindowBlurred::class, function (WindowBlurred $event): void {
            if ($event->id === Hud::ID) {
                Hud::dismiss();
            }
        });
    }

    private function watcherSupervision(): void
    {
        Event::listen(MessageReceived::class, function (MessageReceived $event): void {
            if ($event->alias === 'watcher') {
                Log::info('watcher.out', ['data' => trim((string) $event->data)]);
            }
        });

        Event::listen(ErrorReceived::class, function (ErrorReceived $event): void {
            if ($event->alias === 'watcher') {
                Log::error('watcher.err', ['data' => trim((string) $event->data)]);
            }
        });

        Event::listen(ProcessExited::class, function (ProcessExited $event): void {
            if ($event->alias === 'watcher') {
                Log::warning('watcher.exited', ['code' => $event->code]);
            }
        });
    }
}
