import onMorphed from '../helpers/morphed'
import modalLayer from '../helpers/modal-layer'

// layouts.header: the menu panel below lg, the search field, and the priority-plus nav from lg up.
//
// Priority-plus: the nav slot's top-level items (an item, or a navlist.group) stay in the bar while they fit; the ones
// that do not are hidden with [data-atom-overflow] and listed in a trailing "More" dropdown, in order. Nothing is
// measured against a fixed width: the bar is laid out once with every item showing, the navlist's own box (a flex
// item that does not depend on its content while measuring) says how much room there is, and the items' right
// edges say how many fit.
//
// The originals stay where they are, hidden, and the "More" panel holds a COPY of each moved item. Moving the nodes
// instead would break the next Livewire morph, which diffs the navlist's children by position: it would put every
// moved item back and leave the panel stale. The copies carry no `id`, so ids stay unique, and the panel is
// `wire:ignore`d and rebuilt by fit(), which runs again after every morph (a morph also strips the attributes JS
// wrote). The "More" trigger gets the current underline from CSS (:has([data-current])) because a copy of the
// current item is inside it.
const LG = '(min-width: 64rem)'
const XL = '(min-width: 80rem)'
// the page behind the open menu panel: the layout's own nodes, outside any Livewire component (the bar itself stays live)
const PAGE = '[data-atom-main], [data-atom-footer], [data-atom-shell-crumbs]'
const REFERENCES = ['aria-describedby', 'aria-labelledby', 'aria-controls', 'for']
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
const FIELD = 'input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [contenteditable=""], [contenteditable="true"]'

export default () => ({
    menu: false,
    search: false,
    more: false,
    observer: null,
    destroyed: false,
    signature: '',

    init () {
        this.destroyed = false

        // The panel below lg is a modal layer (helpers/modal-layer.js, as layouts.sidebar's drawer): the page behind is inert while it
        // is open, and the focus moves into it. Closing returns no focus by itself: the menu button and Escape do it
        // (toggleMenu(), escape()), a click elsewhere or a navigation must leave it where the user put it. The layer is kept on the root,
        // not on the Alpine data (a re-run of init() keeps what it made inert).
        this.$el._atomMenuLayer ??= modalLayer({
            isOpen: () => this.menu && !window.matchMedia(LG).matches,
            page: PAGE,
            container: () => this.$refs.nav,
            scroll: '[data-atom-header-nav]',
            close: () => this.closeAll(),
            listen: (sync) => {
                let lg = window.matchMedia(LG)

                lg.addEventListener('change', sync)

                return () => lg.removeEventListener('change', sync)
            },
        })

        this.$el._atomMenuLayer.start()

        // the panel is drawn from `menu` by an effect that has not run yet: write it, then the layer measures it
        this.$watch('menu', () => {
            this.sync()
            this.$el._atomMenuLayer?.sync()
        })

        this.fit()

        this.observer?.disconnect()
        this.observer = new ResizeObserver(() => this.fit())
        this.watch()

        // item widths change when the web font lands
        let refit = () => this.fit()

        document.fonts?.ready?.then(refit)
        document.fonts?.addEventListener?.('loadingdone', refit)

        // crossing lg changes what the panel, "More" and the search field are, and crossing xl what the search field is:
        // whatever was open belongs to the layout that is gone, and would still be open when the width comes back
        let lg = window.matchMedia(LG)
        let xl = window.matchMedia(XL)
        let leaveLg = () => this.closeAll()
        let leaveXl = () => { this.search = false }

        lg.addEventListener('change', leaveLg)
        xl.addEventListener('change', leaveXl)

        this.$el._atomHeaderStopListening?.()
        this.$el._atomHeaderStopListening = () => {
            document.fonts?.removeEventListener?.('loadingdone', refit)
            lg.removeEventListener('change', leaveLg)
            xl.removeEventListener('change', leaveXl)
        }

        // a morph strips every attribute the server did not render: data-open on the panel and the search field,
        // data-menu-open on "More", the overflow marks. Write them again from the state, then measure again.
        this.$el._atomHeaderStopMorph?.()
        this.$el._atomHeaderStopMorph = onMorphed((el) => {
            if (!el || el.contains(this.$el) || this.$el.contains(el)) {
                this.sync()
                this.watch()
                this.fit()
            }
        })
    },

    destroy () {
        this.destroyed = true
        this.$el._atomMenuLayer?.stop()
        this.$el._atomMenuLayer?.release()
        this.observer?.disconnect()
        this.$el._atomHeaderStopMorph?.()
        this.$el._atomHeaderStopListening?.()
    },

    /**
     * Watch what decides how much room the nav units have: the nav, the bar that holds it, and the context and search
     * slots inside it (a slot that grows takes room from the units without changing the nav's own box), and each unit
     * (one shown by a class or style toggle goes from no box to a box, which changes nothing else in the bar).
     */
    watch () {
        let nav = this.$refs.nav

        if (!this.observer || !nav) return

        this.observer.disconnect()

        let units = this.unitsOf(nav)

        // DOM nodes on the root, not on the Alpine data: a node list kept there is read back through a proxy
        this.$el._atomHeaderWatched = units

        for (let el of [nav, nav.parentElement, nav.querySelector('[data-atom-shell-context]'), this.$refs.search, ...units]) {
            if (el) this.observer.observe(el)
        }
    },

    /**
     * Observe the units again when the list of them is not the one being watched (a morph or a script added or
     * removed one). Observing starts one callback of its own, so it is not done when nothing changed.
     */
    rewatch () {
        let nav = this.$refs.nav

        if (this.destroyed || !this.observer || !nav) return

        let watched = this.$el._atomHeaderWatched ?? []
        let units = this.unitsOf(nav)

        if (units.length !== watched.length || units.some((el, i) => el !== watched[i])) this.watch()
    },

    /**
     * The top-level units of the first navlist in the nav slot, the one that is fitted.
     */
    unitsOf (nav) {
        let list = [...nav.querySelectorAll('[data-atom-navlist]')].find((el) => !el.closest('[data-atom-navfoot], [data-atom-header-more]'))

        return list ? this.units(list) : []
    },

    /**
     * Write the state JS owns on nodes the server renders.
     */
    sync () {
        this.$refs.nav.toggleAttribute('data-open', this.menu)
        this.$refs.search?.toggleAttribute('data-open', this.search)
        this.$refs.more?.toggleAttribute('data-menu-open', this.more)
    },

    /**
     * The menu button: it opens the panel, or closes it and keeps the focus on itself (a click does not focus a button in Safari).
     */
    toggleMenu () {
        this.menu = !this.menu

        if (!this.menu) this.$refs.menuButton?.focus()
    },

    toggleSearch () {
        // one layer at a time, and not by waiting for a focusout: Safari and Firefox do not focus a button on click
        if (!this.search) {
            this.more = false
            this.menu = false
        }

        this.search = !this.search

        if (!this.search) return

        this.$nextTick(() => {
            let panel = this.$refs.search
            let target = panel.querySelector(FIELD) ?? panel.querySelector(FOCUSABLE)

            target?.focus()
        })
    },

    toggleMore () {
        if (!this.more) this.search = false

        this.more = !this.more
    },

    openMore () {
        this.search = false
        this.more = true
        this.$nextTick(() => this.$refs.moreItems.querySelector(FOCUSABLE)?.focus())
    },

    /**
     * Escape closes the innermost thing that is open and nothing else. It is heard on the window, in the capture phase, not on the
     * header: a click on something that takes no focus (Safari does not focus a button on click; blank space in the panel, the search
     * popover's edge, the bar) leaves the focus on <body>, and an Escape that only the header heard would be dead. So the layers are
     * asked instead, as layouts.sidebar does: a modal dialog or a popover (a dropdown in the bar or the panel, a tooltip; not a toast,
     * which never takes Escape) has the key first and the next press is ours. Then the search field, then "More", then a navlist.group
     * dropdown (asked to close with an event, so one place decides and a host's own Escape listener cannot leave it open), then the menu panel. Only a press that
     * closes one of those is prevented and stopped, so a host's own Escape listener still runs when nothing here is open.
     */
    escape (e) {
        let group

        // a held key or an IME composition being cancelled is not a press for us
        if (e.repeat || e.isComposing || e.defaultPrevented) return
        if (document.querySelector('dialog:modal, :popover-open:not([data-atom-toast])')) return

        if (this.search) {
            this.take(e)
            this.search = false
            this.$refs.searchButton?.focus()
        }
        else if (this.more) {
            this.take(e)
            this.more = false
            this.$refs.moreButton?.focus()
        }
        else if ((group = this.$el.querySelector('[data-atom-navlist-group][data-menu-open]'))) {
            // a navlist.group dropdown: it closes and returns the focus to its trigger when asked
            this.take(e)
            group.dispatchEvent(new CustomEvent('atom-navlist-group-close'))
        }
        else if (this.menu && !window.matchMedia(LG).matches) {
            this.take(e)
            this.menu = false
            this.$refs.menuButton?.focus()
        }
    },

    /**
     * This press is ours: the Escape handlers of the page under it do not also run.
     */
    take (e) {
        e.preventDefault()
        e.stopPropagation()
    },

    /**
     * Click outside, or navigation: everything closes.
     */
    closeAll () {
        this.menu = false
        this.search = false
        this.more = false
    },

    /**
     * The nav's top-level units: an item or a group, looking through the headingless sections.
     */
    units (list) {
        let found = []
        let walk = (parent) => {
            for (let el of parent.children) {
                if (el.matches('[data-atom-navlist-item], [data-atom-navlist-group]')) found.push(el)
                else if (el.matches('[data-atom-navlist-section]')) walk(el)
            }
        }

        walk(list)

        return found
    },

    /**
     * Move what does not fit into "More", then watch the units that are there now.
     */
    fit () {
        this.place()
        this.rewatch()
    },

    /**
     * The measuring and the moving.
     */
    place () {
        if (this.destroyed || !this.$el?.isConnected) return

        let nav = this.$refs.nav
        let wrap = this.$refs.more
        let list = [...nav.querySelectorAll('[data-atom-navlist]')].find((el) => !el.closest('[data-atom-navfoot], [data-atom-header-more]'))

        if (!wrap || !list) return

        let units = this.units(list)

        units.forEach((el) => el.removeAttribute('data-atom-overflow'))
        wrap.removeAttribute('data-visible')

        // below lg the nav is a panel of rows: everything shows, nothing is moved
        if (!window.matchMedia(LG).matches) {
            nav.removeAttribute('data-atom-fit')
            this.clear()
            return
        }

        nav.setAttribute('data-atom-fit', '')
        nav.setAttribute('data-atom-measuring', '')

        let rtl = getComputedStyle(list).direction === 'rtl'
        let extent = (el, base) => rtl ? base.right - el.getBoundingClientRect().left : el.getBoundingClientRect().right - base.left
        let room = () => list.getBoundingClientRect().width

        // a unit that is not shown (display: none, or inside something that is not) has no box: it takes no room, and
        // is never moved, so it is left out of the measuring and out of "More"
        units = units.filter((el) => el.getClientRects().length > 0)

        let base = list.getBoundingClientRect()
        let edges = units.map((el) => extent(el, base))
        let fits = edges.length === 0 || Math.max(...edges) <= room() + 0.01

        if (fits) {
            this.clear()
            nav.removeAttribute('data-atom-measuring')
            return
        }

        // "More" takes room of its own: measure again with it in place
        wrap.setAttribute('data-visible', '')
        let available = room() + 0.01
        let keep = edges.findIndex((edge) => edge > available)
        let moved = units.slice(keep === -1 ? units.length : keep)

        moved.forEach((el) => el.setAttribute('data-atom-overflow', ''))
        nav.removeAttribute('data-atom-measuring')

        this.fill(moved)
    },

    /**
     * Nothing is moved: "More" is not shown, so it is not open either.
     */
    clear () {
        this.more = false
        this.signature = ''
        this.$refs.moreItems.replaceChildren()
    },

    /**
     * Rebuild the "More" panel from copies of the moved units. A group becomes a labelled section. The panel is left
     * alone when the copies would be the same as the ones in it: a morph elsewhere in the header refits, and replacing
     * the copies would drop the focus off the one the user is on.
     */
    fill (moved) {
        let panel = this.$refs.moreItems
        let nodes = []

        for (let el of moved) {
            if (el.matches('[data-atom-navlist-group]')) {
                let section = document.createElement('div')
                let label = document.createElement('div')

                section.setAttribute('data-atom-header-more-section', '')
                label.setAttribute('data-atom-header-more-label', '')
                label.textContent = el.querySelector('[data-atom-navlist-group-trigger]')?.textContent.trim() ?? ''
                section.append(label)

                el.querySelectorAll('[data-atom-navlist-group-items] [data-atom-navlist-item]').forEach((item) => section.append(this.copy(item)))
                nodes.push(section)
            }
            else nodes.push(this.copy(el))
        }

        let signature = nodes.map((node) => node.outerHTML).join('')

        if (signature === this.signature && panel.childElementCount === nodes.length) return

        this.signature = signature
        panel.replaceChildren(...nodes)
    },

    copy (el) {
        let clone = el.cloneNode(true)

        clone.removeAttribute('data-atom-overflow')
        clone.setAttribute('data-atom-more-copy', '')
        ;[clone, ...clone.querySelectorAll('[id]')].forEach((node) => node.removeAttribute('id'))

        // the copy carries no id, so nothing inside it can be the target of one of these
        ;[clone, ...clone.querySelectorAll(REFERENCES.map((name) => `[${name}]`).join(','))].forEach((node) => {
            REFERENCES.forEach((name) => node.removeAttribute(name))
        })

        return clone
    },
})
