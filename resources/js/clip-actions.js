/**
 * Clip operations shared by the palette and the tray popover.
 *
 * Both surfaces show the same list and must behave identically when acting
 * on it; the difference between them is navigation, not semantics.
 */
export default {
    /**
     * Re-read the history from the server.
     *
     * Both surfaces are rendered once and then kept current by broadcasts,
     * but a window that is closed or hidden does not reliably receive them —
     * so whatever was copied while it was away would never appear. Showing a
     * clipboard manager a stale history is the one thing it must not do, so
     * every summon re-reads rather than trusting the events.
     */
    async refresh() {
        try {
            const response = await fetch('/clips', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })

            if (!response.ok) return

            const { clips } = await response.json()
            if (Array.isArray(clips)) this.clips = clips
        } catch {
            // Keep showing the last known list rather than emptying it.
        }
    },

    async togglePin() {
        const clip = this.selected
        if (!clip) return

        clip.pinned = !clip.pinned
        this.resort()
        await this.post(`/clips/${clip.id}/pin`)
    },

    async forget() {
        const clip = this.selected
        if (!clip) return

        this.clips = this.clips.filter((c) => c.id !== clip.id)
        this.cursor = Math.min(this.cursor, Math.max(this.results.length - 1, 0))
        await this.post(`/clips/${clip.id}`, 'DELETE')
    },

    /** Mirrors the server's ordering: pinned first, then existing recency. */
    resort() {
        this.clips = [
            ...this.clips.filter((c) => c.pinned),
            ...this.clips.filter((c) => !c.pinned),
        ]
    },

    async post(url, method = 'POST') {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            })

            // fetch only rejects on network failure, so an expired CSRF token
            // or a server error arrives as a perfectly resolved promise. Left
            // unchecked, the action simply appears to do nothing.
            if (!response.ok) {
                this.status = response.status === 419 ? 'session expired' : `failed (${response.status})`

                return false
            }

            return true
        } catch {
            this.status = 'no connection'

            return false
        }
    },
}
