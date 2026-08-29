import actions from './clip-actions'
import { onNativeReady } from './native'

/**
 * The tray popover: the same history as the palette, browsed rather than
 * driven. Clicking a clip copies it and closes the popover, which is what
 * a mouse user reaching for the menu bar is trying to do.
 */
export default (initial = [], paused = false, onboarded = true) => ({
    ...actions,

    clips: initial,
    paused,
    onboarded,
    query: '',
    cursor: -1,
    copiedId: null,

    boot() {
        onNativeReady(() => {
            Native.on('Native\\Desktop\\Events\\MenuBar\\MenuBarShown', () => {
                this.query = ''
                this.cursor = -1
                this.refresh()
            })

            Native.on('App\\Events\\ClipboardUpdated', (clip = {}) => {
                this.clips = [
                    { id: clip.id, preview: clip.preview, kind: clip.kind, pinned: clip.pinned },
                    ...this.clips.filter((c) => c.id !== clip.id),
                ]
            })
        })
    },

    get results() {
        const q = this.query.trim().toLowerCase()
        if (!q) return this.clips
        return this.clips.filter((c) => (c.preview || '').toLowerCase().includes(q))
    },

    get selected() {
        return this.results[this.cursor] ?? null
    },

    /**
     * Confirm in place rather than closing instantly: this window has no
     * cursor-anchored context, so a silent close leaves the user unsure
     * whether anything happened.
     */
    async copy(index) {
        const clip = this.results[index]
        if (!clip) return

        this.cursor = index
        this.copiedId = clip.id
        await this.post(`/clips/${clip.id}/use`)
        setTimeout(() => (this.copiedId = null), 900)
    },

    async pinAt(index) {
        this.cursor = index
        await this.togglePin()
    },

    async forgetAt(index) {
        this.cursor = index
        await this.forget()
    },
})
