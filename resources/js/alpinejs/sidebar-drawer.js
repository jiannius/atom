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
// not set from lg up (a stale flag must never lock the page), and not while a dialog is open: a modal dialog
// blocks the rest of the document itself, and a dialog inside <main> would be dead under an inert <main>.
//
// The closed drawer needs none of this: atom.css hides it with `visibility` below lg.
const LG = '(min-width: 64rem)'
const FLAG = 'data-show-stashed-sidebar'
const PAGE = '[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs], [data-atom-shell-page-actions]'
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

const shown = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden'

export default () => ({
    open: false,

    init () {
        let lg = window.matchMedia(LG)
        let sync = () => this.sync(lg.matches)

        // the flag, and a dialog opening or closing anywhere (its `open` attribute)
        let observer = new MutationObserver(sync)

        // one call: a second observe() on the same node replaces the options of the first
        observer.observe(document.body, { attributes: true, attributeFilter: [FLAG, 'open'], subtree: true })
        lg.addEventListener('change', sync)

        this.$el._atomDrawerStop?.()
        this.$el._atomDrawerStop = () => {
            observer.disconnect()
            lg.removeEventListener('change', sync)
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

        this.open = want
        this.setInert(want && !document.querySelector('dialog[open]'))

        if (want && !wasOpen) this.focusIn()
        else if (want && this.lost()) this.focusIn() // a modal closed and gave the focus back to a page that is inert again
        else if (!want && wasOpen) this.focusOut()
    },

    setInert (on) {
        document.querySelectorAll(PAGE).forEach((el) => el.toggleAttribute('inert', on))
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
