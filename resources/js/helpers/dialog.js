import { tookEscape } from './dialog-escape'

/**
 * The close-side lifecycle a modal and the command palette share, as methods to spread into an Alpine data object:
 * syncClosed (the browser closed the dialog without the component's own close), escapeClose and backdropClick. The method names are what
 * the Blade templates bind, and every hook receives the component as its first argument.
 *
 * - `shown(c)`: the component's own open state (the data-open attribute, a flag), cleared by `reset(c)`.
 * - `close(c, e)`: the component's close; `e` is only the backdrop click, so a keyboard close passes none.
 * - `escapable(c)`: whether Escape may close it (default: always).
 * - `requireOpen`: an Escape that finds the dialog already closed does nothing (the browser moves the focus out of a closed dialog at its
 *   next frame, so a key before that still lands here and would say `closed` again).
 * - `insideRect`: a click on the dialog element only closes it when outside its box (a backdrop click), not on its own padding.
 */
export default ({ shown, close, reset, escapable = () => true, requireOpen = false, insideRect = false }) => ({
    // The browser closed the dialog without the component's close: an Escape the dialog's own keydown handler never heard (the focus on
    // <body>, a control that stops the key), a <form method="dialog">, a direct close(). `close` is queued, so after the component's own
    // close it finds the state already clean, and after a close and a reopen in one tick it finds the dialog open again.
    syncClosed () {
        if (this.$root.open || !shown(this)) return

        reset(this)
    },

    escapeClose (e) {
        // A popover inside took this press (helpers/dialog-escape.js).
        if (tookEscape(e) || (requireOpen && !this.$root.open)) return

        e.preventDefault()

        // The keydown only arrives while the focus is inside the dialog; with the focus on <body>, or in a control that stops the key, the
        // browser's own cancel is what Escape does (see syncClosed). Only close when the dialog is escapable.
        if (escapable(this)) close(this)
    },

    backdropClick (e) {
        // Only a click on the dialog element itself is the backdrop; clicks on inner content have a descendant target.
        if (e.target !== this.$root) return

        if (insideRect) {
            const rect = e.target.getBoundingClientRect()

            const clickedInDialog = (
                rect.top <= e.clientY &&
                e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX &&
                e.clientX <= rect.left + rect.width
            )

            if (clickedInDialog) return
        }

        close(this, e)
    },
})
