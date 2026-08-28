<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by a tray menu item. Electron delivers these as ordinary HTTP
 * requests, so the listener lives in AppServiceProvider.
 */
class PauseToggled
{
    use Dispatchable;
}
