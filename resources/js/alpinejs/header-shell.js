import onMorphed from '../helpers/morphed'

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
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
const FIELD = 'input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [contenteditable=""], [contenteditable="true"]'

export default () => ({
    menu: false,
    search: false,
    more: false,
    stopMorph: null,
    observer: null,

    init () {
        this.fit()

        this.observer?.disconnect()
        this.observer = new ResizeObserver(() => this.fit())
        this.observer.observe(this.$refs.nav)

        // item widths change when the web font lands
        document.fonts?.ready?.then(() => this.fit())

        // a morph strips every attribute the server did not render: data-open on the panel and the search field,
        // data-menu-open on "More", the overflow marks. Write them again from the state, then measure again.
        this.$el._atomHeaderStopMorph?.()
        this.$el._atomHeaderStopMorph = onMorphed((el) => {
            if (!el || el.contains(this.$el) || this.$el.contains(el)) {
                this.sync()
                this.fit()
            }
        })
    },

    destroy () {
        this.observer?.disconnect()
        this.$el._atomHeaderStopMorph?.()
    },

    /**
     * Write the state JS owns on nodes the server renders.
     */
    sync () {
        this.$refs.nav.toggleAttribute('data-open', this.menu)
        this.$refs.search?.toggleAttribute('data-open', this.search)
        this.$refs.more?.toggleAttribute('data-menu-open', this.more)
    },

    toggleSearch () {
        this.search = !this.search

        if (!this.search) return

        this.$nextTick(() => {
            let panel = this.$refs.search
            let target = panel.querySelector(FIELD) ?? panel.querySelector(FOCUSABLE)

            target?.focus()
        })
    },

    openMore () {
        this.more = true
        this.$nextTick(() => this.$refs.moreItems.querySelector(FOCUSABLE)?.focus())
    },

    /**
     * Escape closes the innermost thing that is open and nothing else: a dropdown inside the bar or the panel
     * (a context switcher, the account menu) is a native popover that closes itself on this same keypress, so
     * the capture phase asks before it has. Then the search field, then "More", then the menu panel.
     */
    escape (e) {
        if (this.$el.querySelector(':popover-open')) return

        if (this.search) {
            this.search = false
            this.$refs.searchButton?.focus()
        }
        else if (this.more) {
            this.more = false
            this.$refs.moreButton?.focus()
        }
        else if (this.$el.querySelector('[data-atom-navlist-group][data-menu-open]')) {
            // a navlist.group dropdown closes itself and returns focus to its trigger
        }
        else if (this.menu) {
            this.menu = false
            this.$refs.menuButton?.focus()
        }
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
     * Move what does not fit into "More".
     */
    fit () {
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
            this.$refs.moreItems.replaceChildren()
            return
        }

        nav.setAttribute('data-atom-fit', '')
        nav.setAttribute('data-atom-measuring', '')

        let rtl = getComputedStyle(list).direction === 'rtl'
        let extent = (el, base) => rtl ? base.right - el.getBoundingClientRect().left : el.getBoundingClientRect().right - base.left
        let room = () => list.getBoundingClientRect().width

        let base = list.getBoundingClientRect()
        let edges = units.map((el) => extent(el, base))
        let fits = edges.length === 0 || edges[edges.length - 1] <= room() + 0.01

        if (fits) {
            this.$refs.moreItems.replaceChildren()
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
     * Rebuild the "More" panel from copies of the moved units. A group becomes a labelled section.
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

        panel.replaceChildren(...nodes)
    },

    copy (el) {
        let clone = el.cloneNode(true)

        clone.removeAttribute('data-atom-overflow')
        clone.setAttribute('data-atom-more-copy', '')
        ;[clone, ...clone.querySelectorAll('[id]')].forEach((node) => node.removeAttribute('id'))

        return clone
    },
})
