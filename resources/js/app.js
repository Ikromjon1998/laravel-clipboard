import Alpine from 'alpinejs'

/**
 * The palette's filter path runs entirely in memory: the window is pre-warmed
 * and holds the whole bounded history, so a keystroke never crosses a process
 * boundary. Actions post to small JSON routes and update optimistically —
 * the list must never wait for a round trip to redraw.
 */
Alpine.data('hud', (initial = [], paused = false) => ({
    clips: initial,
    paused,
    query: '',
    cursor: 0,
    status: 'ready',

    boot() {
        this.focusSearch()

        window.addEventListener('native:init', () => {
            this.status = 'listening'

            Native.on('App\\Events\\ClipboardUpdated', (clip = {}) => {
                this.clips = [
                    { id: clip.id, preview: clip.preview, kind: clip.kind, pinned: clip.pinned },
                    ...this.clips.filter((c) => c.id !== clip.id),
                ]
                this.cursor = 0
            })

            // Re-focus every time the palette is summoned, not just on load.
            Native.on('Native\\Desktop\\Events\\Windows\\WindowShown', () => {
                this.query = ''
                this.cursor = 0
                this.focusSearch()
            })
        })
    },

    focusSearch() {
        this.$nextTick(() => this.$refs.q?.focus())
    },

    get results() {
        const q = this.query.trim().toLowerCase()
        if (!q) return this.clips
        return this.clips.filter((c) => (c.preview || '').toLowerCase().includes(q))
    },

    get selected() {
        return this.results[this.cursor] ?? null
    },

    move(delta) {
        const n = this.results.length
        if (!n) return
        this.cursor = (this.cursor + delta + n) % n
    },

    pick(index) {
        if (index < this.results.length) {
            this.cursor = index
            this.apply()
        }
    },

    async apply() {
        const clip = this.selected
        if (!clip) return

        this.dismiss()
        await this.post(`/clips/${clip.id}/use`)
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

    dismiss() {
        this.query = ''
        window.blur()
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
        } catch (error) {
            this.status = 'action failed'
        }
    },
}))

window.Alpine = Alpine
Alpine.start()
