// layouts.sidebar: the stashed sidebar below lg behaves as a modal drawer.
//
// The body flag `data-show-stashed-sidebar` is the one source of truth (the menu button, the backdrop, Escape and
// the sidebar's own resize watch all write it), so this watches the flag instead of the writers:
// - open: the page behind goes `inert` (so Tab and a screen reader stay in the drawer, the menu button and the
//   account menu in the bar), and the focus moves into the drawer: the current page's link, else the first
//   focusable thing;
// - close: the page is let go and the focus returns to the menu button.
//
// What is inert is chosen by data attributes the layout puts on its own nodes (PAGE below): <main>, the footer, the
// crumbs and the host's `actions` slot. They are written by the layout, outside any Livewire component, so a morph
// does not reach them; the menu button, the account menu and the dark toggle are left out on purpose. `inert` is
// not set from lg up (a stale flag must never lock the page), and not while a modal dialog is open: it blocks the
// rest of the document itself, and a dialog inside <main> would be dead under an inert <main>. A non-modal dialog
// (show(), a permanent <dialog open>) blocks nothing and lifts nothing.
//
// Only the `inert` this instance wrote is ever removed: a host's own `inert` on one of these nodes, or another
// instance's, is left alone. A wire:navigate (or Back) caches the page as it is when it navigates away, so the flag
// and the inert are cleared on `livewire:navigating`, which fires before that snapshot is taken; Back then restores
// a closed drawer.
//
// The closed drawer needs none of this: atom.css hides it with `visibility` below lg.
const LG = '(min-width: 64rem)'
const FLAG = 'data-show-stashed-sidebar'
const PAGE = '[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs], [data-atom-shell-page-actions]'
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

/**
 * A modal dialog is open (showModal()): the document is blocked by the browser itself.
 */
const modalOpen = () => {
    try {
        return !!document.querySelector('dialog[open]:modal')
    }
    catch (e) {
        return !!document.querySelector('dialog[open]')
    }
}

const shown = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden'

export default () => ({
    open: false,
    modal: false,

    init () {
        let lg = window.matchMedia(LG)
        let sync = () => this.sync(lg.matches)

        // the flag, a dialog opening or closing anywhere (its `open` attribute), and a dialog taken out of the page while it is open
        // (a re-render removes it with no `open` change); sync() is cheap and does nothing when nothing changed
        let observer = new MutationObserver(sync)

        // one call: a second observe() on the same node replaces the options of the first
        observer.observe(document.body, { attributes: true, attributeFilter: [FLAG, 'open'], subtree: true, childList: true })
        lg.addEventListener('change', sync)

        // the page is cached for Back as it is when it is left: leave it closed
        let leaving = () => {
            this.open = false
            this.setInert(false)
            document.body.removeAttribute(FLAG)
        }

        document.addEventListener('livewire:navigating', leaving)

        // a run of init() without destroy() stops the old listeners first and keeps the set: it holds what the first run made inert
        this.$el._atomDrawerStop?.()
        // DOM nodes on the root, not on the Alpine data: a set kept there is read back through a proxy
        this.$el._atomDrawerApplied ??= new Set()
        this.$el._atomDrawerStop = () => {
            observer.disconnect()
            lg.removeEventListener('change', sync)
            document.removeEventListener('livewire:navigating', leaving)
        }

        sync()
    },

    destroy () {
        this.$el._atomDrawerStop?.()
        this.setInert(false)
    },

    sync (atLg = window.matchMedia(LG).matches) {
        let want = !atLg && document.body.hasAttribute(FLAG)
        let wasOpen = this.open

        // closed and staying closed: the common case, a mutation somewhere in the page
        if (!want && !wasOpen) return

        let modal = want && modalOpen()
        let modalClosed = this.modal && !modal

        this.open = want
        this.modal = modal
        this.setInert(want && !modal)

        if (want && !wasOpen) this.focusIn()
        // a modal closed (or was removed while open) and the focus it gave back is on a page that is inert again; any other mutation
        // (a poll, a stream, a toast) leaves the focus and the scroll where they are
        else if (want && modalClosed && this.lost()) this.focusIn()
        else if (!want && wasOpen) this.focusOut()
    },

    /**
     * Make the page inert, or give back what this instance made inert (an `inert` that was there already is not ours).
     */
    setInert (on) {
        let applied = this.$el._atomDrawerApplied

        if (!applied) return

        if (on) {
            document.querySelectorAll(PAGE).forEach((el) => {
                if (el.hasAttribute('inert')) return

                el.setAttribute('inert', '')
                applied.add(el)
            })
        }
        else {
            applied.forEach((el) => el.removeAttribute('inert'))
            applied.clear()
        }
    },

    /**
     * The focus is nowhere (the page, or a node that has just become inert).
     */
    lost () {
        let active = document.activeElement

        return !active || active === document.body || !!active.closest('[inert]')
    },

    /**
     * Into the drawer: the link of the page you are on, else the first focusable thing. preventScroll, because the drawer is
     * still sliding in; the scroll box inside it is moved by hand.
     */
    focusIn () {
        let sidebar = document.querySelector('[data-atom-sidebar]')

        if (!sidebar) return

        let target = [...sidebar.querySelectorAll('[aria-current="page"]')].find(shown) ?? [...sidebar.querySelectorAll(FOCUSABLE)].find(shown)

        if (!target) return

        target.focus({ preventScroll: true })

        let box = target.closest('[data-atom-sidebar-scroll]')

        if (!box) return

        let inner = box.getBoundingClientRect()
        let rect = target.getBoundingClientRect()

        if (rect.top < inner.top) box.scrollTop -= inner.top - rect.top
        else if (rect.bottom > inner.bottom) box.scrollTop += rect.bottom - inner.bottom
    },

    /**
     * Back to the menu button, unless a layer above the page has the focus (a dialog, a dropdown) or the button is not drawn (lg).
     */
    focusOut () {
        let button = document.querySelector('[data-atom-sidebar-toggle]')

        if (!button || button.getClientRects().length === 0) return
        if (document.activeElement?.closest('dialog[open], :popover-open:not([data-atom-toast])')) return

        button.focus()
    },
})
