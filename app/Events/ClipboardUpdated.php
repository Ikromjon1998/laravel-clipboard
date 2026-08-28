<?php

namespace App\Events;

use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The desktop-facing echo of the engine's ClipCaptured event.
 *
 * Core dispatches plain PHP events because broadcasting is a host concern;
 * this is the host's concern. It carries only what a list row needs, so the
 * full clip text never crosses into the renderer until it is asked for.
 */
class ClipboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public int $id;

    public string $preview;

    public string $kind;

    public bool $pinned;

    public function __construct(Clip $clip)
    {
        $this->id = $clip->id;
        $this->preview = $clip->preview();
        $this->kind = $clip->kind->value;
        $this->pinned = $clip->pinned;
    }

    public function broadcastOn(): array
    {
        return [new Channel('nativephp')];
    }
}
