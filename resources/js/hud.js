import actions from './clip-actions'
import { onNativeReady } from './native'

/**
 * The palette's filter path runs entirely in memory: the window is pre-warmed
 * and holds the whole bounded history, so a keystroke never crosses a process
 * boundary. Actions post to small JSON routes and update optimistically —
 * the list must never wait for a round trip to redraw.
 */
export default (initial = [], paused = false) => ({
    ...actions,

    clips: initial,
    paused,
    query: '',
    cursor: 0,
    status: 'ready',

    boot() {
        this.focusSearch()

        onNativeReady(() => {
            this.status = 'listening'

            Native.on('App\\Events\\ClipboardUpdated', (clip = {}) => {
                this.clips = [
                    { id: clip.id, preview: clip.preview, kind: clip.kind, pinned: clip.pinned },
                    ...this.clips.filter((c) => c.id !== clip.id),
                ]
                this.cursor = 0
            })

            // Re-focus and reset on every summon, not just on first load.
            Native.on('Native\\Desktop\\Events\\Windows\\WindowShown', () => {
                this.query = ''
                this.cursor = 0
                this.focusSearch()
                this.refresh()
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

        // Put the clip on the clipboard *before* hiding. Hiding first felt
        // faster, but a hidden Electron window has its renderer throttled, so
        // the request could stall and the paste silently never happened. On
        // loopback this costs a couple of milliseconds.
        const ok = await this.post(`/clips/${clip.id}/use`)

        if (ok) this.dismiss()
    },

    dismiss() {
        this.query = ''
        window.blur()
    },
})
