/**
 * Settings window behaviour.
 *
 * Every control saves on change rather than behind a Save button: there is
 * nothing here worth staging, and a settings window that can be closed in a
 * dirty state is a way to lose someone's preferences.
 */
import { onNativeReady } from './native'

export default (initial = {}, onboarded = false) => ({
    form: { ...initial },
    onboarded,
    recording: false,
    confirming: false,
    justSaved: false,
    savedTimer: null,
    confirmTimer: null,

    boot() {
        onNativeReady(() => {
            // Another surface (the tray menu) can change the same state, so
            // re-read it whenever this window is brought forward.
            Native.on('Native\\Desktop\\Events\\Windows\\WindowFocused', () => this.refresh())
        })
    },

    async refresh() {
        const state = await this.request('/settings', 'POST', {})
        if (state) this.form = { ...this.form, ...state }
    },

    async save(field) {
        const state = await this.request('/settings', 'POST', { [field]: this.form[field] })
        if (state) {
            this.form = { ...this.form, ...state }
            this.flashSaved()
        }
    },

    /**
     * Two-step, because clearing history is unrecoverable. The confirmation
     * lapses on its own so a stray click cannot arm it indefinitely.
     */
    async clearHistory() {
        if (!this.confirming) {
            this.confirming = true
            this.confirmTimer = setTimeout(() => (this.confirming = false), 3000)
            return
        }

        clearTimeout(this.confirmTimer)
        this.confirming = false

        const state = await this.request('/settings/history', 'DELETE')
        if (state) {
            this.form = { ...this.form, ...state }
            this.flashSaved()
        }
    },

    async finishOnboarding() {
        await this.request('/settings/onboarded', 'POST')
        this.onboarded = true
    },

    startRecording() {
        this.recording = true
        this.$el.focus()
    },

    /**
     * Build an Electron accelerator from a real keypress. Modifier-only
     * presses are ignored so the field waits for a complete combination.
     */
    capture(event) {
        if (!this.recording) return

        if (event.key === 'Escape') {
            this.recording = false
            return
        }

        const parts = []
        if (event.metaKey) parts.push('CmdOrCtrl')
        if (event.ctrlKey && !event.metaKey) parts.push('Ctrl')
        if (event.altKey) parts.push('Alt')
        if (event.shiftKey) parts.push('Shift')

        const key = this.normaliseKey(event)
        if (!key) return

        // A bare key would swallow that key system-wide.
        if (parts.length === 0) return

        parts.push(key)
        this.recording = false
        this.form.hotkey = parts.join('+')
        this.save('hotkey')
    },

    normaliseKey(event) {
        const code = event.code || ''

        if (/^Key[A-Z]$/.test(code)) return code.slice(3)
        if (/^Digit[0-9]$/.test(code)) return code.slice(5)
        if (/^F([1-9]|1[0-9]|2[0-4])$/.test(code)) return code

        const named = {
            Space: 'Space',
            Enter: 'Return',
            Backspace: 'Backspace',
            Delete: 'Delete',
            ArrowUp: 'Up',
            ArrowDown: 'Down',
            ArrowLeft: 'Left',
            ArrowRight: 'Right',
        }

        return named[code] ?? null
    },

    /** Render an accelerator the way macOS shows it. */
    display(hotkey) {
        if (!hotkey) return ''
        return hotkey
            .replace('CmdOrCtrl', '⌘')
            .replace('Command', '⌘')
            .replace('Ctrl', '⌃')
            .replace('Alt', '⌥')
            .replace('Shift', '⇧')
            .replace(/\+/g, '')
    },

    countLabel() {
        const n = this.form.clip_count ?? 0
        return n === 1 ? '1 clip stored' : `${n} clips stored`
    },

    flashSaved() {
        this.justSaved = true
        clearTimeout(this.savedTimer)
        this.savedTimer = setTimeout(() => (this.justSaved = false), 1200)
    },

    async request(url, method, body) {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: body === undefined ? undefined : JSON.stringify(body),
            })

            return response.ok ? await response.json() : null
        } catch {
            return null
        }
    },
})
