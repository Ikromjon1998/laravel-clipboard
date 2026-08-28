<?php

namespace App\Http\Controllers;

use App\Support\Preferences;
use Ikromjon\ClipboardCore\ClipboardHistory;
use Ikromjon\ClipboardCore\Models\Clip;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ClipController extends Controller
{
    public function __construct(private readonly ClipboardHistory $history) {}

    /**
     * The palette is rendered with its full history already inlined, so
     * filtering is a pure in-memory operation with no request in the loop.
     */
    public function hud(): View
    {
        return view('hud', [
            'clips' => $this->present($this->history->recent()),
            'paused' => $this->history->isPaused(),
        ]);
    }

    /**
     * The tray popover. Same data as the HUD, but a calmer surface: this one
     * is browsed with a mouse rather than driven from the keyboard.
     */
    public function popover(): View
    {
        return view('menubar', [
            'clips' => $this->present($this->history->recent()),
            'paused' => $this->history->isPaused(),
            'onboarded' => Preferences::hasOnboarded(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'clips' => $this->present($this->history->recent()),
        ]);
    }

    public function use(Clip $clip): JsonResponse
    {
        $this->history->use($clip);

        return response()->json(['used' => $clip->id]);
    }

    public function pin(Clip $clip): JsonResponse
    {
        $updated = $this->history->pin($clip->id, ! $clip->pinned);

        return response()->json(['pinned' => $updated?->pinned ?? false]);
    }

    public function destroy(Clip $clip): JsonResponse
    {
        return response()->json(['forgotten' => $this->history->forget($clip->id)]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Clip>  $clips
     * @return array<int, array<string, mixed>>
     */
    private function present($clips): array
    {
        return $clips->map(fn (Clip $clip): array => [
            'id' => $clip->id,
            'preview' => $clip->preview(120),
            'kind' => $clip->kind->value,
            'pinned' => $clip->pinned,
        ])->values()->all();
    }
}
