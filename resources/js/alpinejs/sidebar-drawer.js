import modalLayer from '../helpers/modal-layer'

// layouts.sidebar: the stashed sidebar below lg behaves as a modal drawer (helpers/modal-layer.js holds the behaviour: inert page,
// focus in, a modal lifting the inert, Back).
//
// The body flag `data-show-stashed-sidebar` is the one source of truth (the menu button, the backdrop, Escape and
// the sidebar's own resize watch all write it), so this watches the flag instead of the writers:
// - open: the page behind goes `inert` (so Tab and a screen reader stay in the drawer, the menu button and the
//   account menu in the bar), and the focus moves into the drawer;
// - close: the page is let go and the focus returns to the menu button.
//
// What is inert is chosen by data attributes the layout puts on its own nodes (PAGE below): <main>, the footer, the
// crumbs and the host's `actions` slot. The menu button, the account menu and the dark toggle are left out on purpose.
// `inert` is not set from lg up (a stale flag must never lock the page).
//
// The closed drawer needs none of this: atom.css hides it with `visibility` below lg.
const LG = '(min-width: 64rem)'
const FLAG = 'data-show-stashed-sidebar'
const PAGE = '[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs], [data-atom-shell-page-actions]'

export default () => ({
    init () {
        // the layer is kept on the root, not on the Alpine data (a re-run of init() keeps what it made inert)
        this.$el._atomDrawerLayer ??= modalLayer({
            isOpen: () => !window.matchMedia(LG).matches && document.body.hasAttribute(FLAG),
            page: PAGE,
            container: () => document.querySelector('[data-atom-sidebar]'),
            scroll: '[data-atom-sidebar-scroll]',
            returnTo: () => document.querySelector('[data-atom-sidebar-toggle]'),
            close: () => document.body.removeAttribute(FLAG),
            attributes: [FLAG],
            listen: (sync) => {
                let lg = window.matchMedia(LG)

                lg.addEventListener('change', sync)

                return () => lg.removeEventListener('change', sync)
            },
        })

        this.$el._atomDrawerLayer.start()
    },

    destroy () {
        this.$el._atomDrawerLayer?.stop()
        this.$el._atomDrawerLayer?.release()
    },
})
