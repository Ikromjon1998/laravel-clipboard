/**
 * Clip operations shared by the palette and the tray popover.
 *
 * Both surfaces show the same list and must behave identically when acting
 * on it; the difference between them is navigation, not semantics.
 */
export default {
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
            await fetch(url, {
                method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            })
        } catch {
            this.status = 'action failed'
        }
    },
}
