import onMorphed from '../helpers/morphed'

export default (config) => {
    return {
        trails: [],

        // whether the page prints its own <h1> page.header (a modal's is level 2 and does not count). The single-crumb heading
        // would repeat its title, so the template hides it. Read on every navigation, not in CSS: the trail is one persisted
        // element that is not inside the page, so a selector cannot reach from it to the page's header.
        hasPageHeader: false,

        // How many of the middle crumbs (the ones between the first and the parent) the list hides behind its "..." menu,
        // oldest first. Decided by fit() from what the crumbs measure, never by a fixed count.
        hiddenCount: 0,

        init () {
            // A page.header can also appear after navigation, when a Livewire update renders it (an `@if ($record)`, a lazy
            // component), so read it again after every morph. Kept on the element, so a re-run init() drops its earlier registration.
            this.$root._atomBreadcrumbsStopMorph?.()
            this.$root._atomBreadcrumbsStopMorph = onMorphed(() => {
                this.readPageHeader()
                this.fit()
            })

            // The room the trail has changes with the window, the shell's bar and the web font, none of which is a Livewire
            // event. The root is `w-full`, so its width is the room whatever the crumbs are: observing it cannot loop.
            this.$root._atomBreadcrumbsStopWatching?.()

            if (typeof ResizeObserver === 'function') {
                // Fitted in the next frame, not in the observer's own delivery: a fit that changes the list changes what is
                // observed, and Chrome reports that as a window error ("ResizeObserver loop completed with undelivered notifications")
                let frame = 0
                let observer = new ResizeObserver(() => {
                    cancelAnimationFrame(frame)
                    frame = requestAnimationFrame(() => this.fit())
                })
                observer.observe(this.$root)

                let fonts = document.fonts
                let refit = () => this.fit()
                let sm = window.matchMedia?.('(min-width: 40rem)')

                fonts?.ready?.then(refit)
                fonts?.addEventListener?.('loadingdone', refit)
                // the list is `display: none` below sm, so crossing it is a change of box the root's width may not show
                sm?.addEventListener?.('change', refit)

                this.$root._atomBreadcrumbsStopWatching = () => {
                    cancelAnimationFrame(frame)
                    observer.disconnect()
                    fonts?.removeEventListener?.('loadingdone', refit)
                    sm?.removeEventListener?.('change', refit)
                }
            }
        },

        destroy () {
            this.$root._atomBreadcrumbsStopMorph?.()
            this.$root._atomBreadcrumbsStopMorph = null
            this.$root._atomBreadcrumbsStopWatching?.()
            this.$root._atomBreadcrumbsStopWatching = null
        },

        readPageHeader () {
            this.hasPageHeader = !!document.body.querySelector('[data-atom-main] h1[data-atom-page-header-title]')
        },

        get breadcrumbs () {
            // Return the trail from the last element up to and including the first element with home: true
            let idx = this.trails.slice().reverse().findIndex(item => item.home)
            if (idx === -1) return []
            let start = this.trails.length - 1 - idx
            return this.trails.slice(start)
        },

        // the crumb above the current page: the phone's back link
        get parent () {
            return this.breadcrumbs[this.breadcrumbs.length - 2]
        },

        // the middle crumbs, the ones that may collapse: everything between the first crumb, the parent and the current page
        get collapsible () {
            return Math.max(0, this.breadcrumbs.length - 3)
        },

        // What the list renders, in order: every crumb, and the "..." control after the first when there is something to
        // collapse. Always every crumb: a hidden one is only taken out of the flow (absolute, invisible), so fit() can
        // still measure it. Plain flags per row: a binding in the x-for reads only its `item`.
        get entries () {
            let crumbs = this.breadcrumbs
            let hidden = Math.min(this.hiddenCount, this.collapsible)
            let entries = []

            crumbs.forEach((crumb, i) => {
                entries.push({
                    type: 'crumb',
                    key: `${crumb.title}-${crumb.url}-${i}`,
                    title: crumb.title,
                    href: crumb.href,
                    last: i === crumbs.length - 1,
                    hidden: i >= 1 && i <= hidden,
                })

                if (i === 0 && this.collapsible > 0) {
                    entries.push({
                        type: 'more',
                        key: 'more',
                        last: false,
                        hidden: hidden === 0,
                        items: crumbs.slice(1, hidden + 1).map((item, j) => ({ key: `${item.title}-${item.url}-${j + 1}`, title: item.title, href: item.href })),
                    })
                }
            })

            return entries
        },

        // Hide as few middle crumbs as the room needs. The widths are natural ones, read from the list as it is (a crumb the
        // list hides is still laid out, so it has one), which is why the answer does not depend on what shows now and cannot
        // flip back and forth: the smallest h whose crumbs, the "..." control and the gaps fit, the current page at its full
        // width. When even the most collapsed trail does not fit, the current page is what truncates.
        fit () {
            let list = this.$root?.querySelector?.('[data-atom-crumb-list]')

            // no list (one crumb, no trail yet) or not displayed (a phone shows the back link): nothing to fit
            if (!list || !list.clientWidth) return

            let items = [...list.querySelectorAll(':scope > li[data-atom-crumb]')]
            let crumbs = items.filter((li) => li.dataset.atomCrumb === 'crumb')
            let more = items.find((li) => li.dataset.atomCrumb === 'more')

            if (crumbs.length < 4 || !more) {
                if (this.hiddenCount !== 0) this.hiddenCount = 0
                return
            }

            let current = crumbs[crumbs.length - 1].querySelector('[data-atom-crumb-current]')

            // the current page may be truncated now: its natural width is its text's, not its box
            let widths = crumbs.map((li, i) => i === crumbs.length - 1 && current ? current.scrollWidth : li.offsetWidth)
            let gap = parseFloat(getComputedStyle(list).columnGap) || 0
            let room = list.clientWidth - 1
            let collapsible = crumbs.length - 3

            let needed = (hidden) => {
                let shown = widths.filter((_, i) => i === 0 || i > hidden)
                let sum = shown.reduce((total, width) => total + width, 0) + (hidden > 0 ? more.offsetWidth : 0)

                return sum + gap * (shown.length + (hidden > 0 ? 1 : 0) - 1)
            }

            let hidden = 0
            while (hidden < collapsible && needed(hidden) > room) hidden++

            if (hidden !== this.hiddenCount) this.hiddenCount = hidden
        },

        // Focus the nth menu item of the "..." menu that `el` is in.
        menuFocus (el, n) {
            let items = [...el.querySelectorAll('[data-atom-crumb-menu] [data-atom-menu-item]')]

            items[((n % items.length) + items.length) % items.length]?.focus()
        },

        // Arrow Up and Down, Home and End walk the menu; Tab and Escape are the browser's (the popover closes on Escape and
        // gives focus back to the button).
        menuKey (event) {
            let keys = { ArrowDown: 1, ArrowUp: -1, Home: 'first', End: 'last' }

            if (!(event.key in keys)) return

            event.preventDefault()

            let items = [...event.currentTarget.querySelectorAll('[data-atom-menu-item]')]
            let step = keys[event.key]
            let next = step === 'first' ? 0 : step === 'last' ? items.length - 1 : items.indexOf(document.activeElement) + step

            items[((next % items.length) + items.length) % items.length]?.focus()
        },

        back () {
            let idx = this.breadcrumbs.length - 2
            let url = idx > -1 ? atom.safeUrl(this.breadcrumbs[idx].href) : null
            if (url) Livewire.navigate(url)
        },

        // when navigate, the final URL of the page might be different
        // eg, when the query string is updated
        // so we need to update the href inside the breadcrumb trail
        // whenever the navigation is take place
        getLatestHref (e) {
            // an empty trail is a legitimate state — first paint, or a page whose
            // component declares no breadcrumbs() — and this runs on every
            // navigation, so it must not throw out of the handler
            let current = this.trails[this.trails.length - 1]
            if (!current) return

            let href = window.location.href

            if (href.split('?')[0] !== current.url) return

            current.href = href
        },

        // build the breadcrumbs trail
        build () {
            this.readPageHeader()

            // retrieve the breadcrumbs data from the atom component
            // match on wire:id rather than position: the sidebar layout puts an
            // sr-only <h1> in front of the Livewire root when the page has a title,
            // and a consuming app may wrap its component in a plain div. querySelector
            // returns document order, so a nested component can't outrank the page root.
            let wireId = document.body.querySelector('[data-atom-main] [wire\\:id]')?.getAttribute('wire:id')
            if (!wireId) return

            let component = Livewire.find(wireId)
            if (!component) return

            // $_breadcrumbs is [] until a component declares a breadcrumbs() method,
            // and a trail with no root can't be merged into anything
            let data = component._breadcrumbs
            if (!data?.home) return

            let home = { ...data.home, home: true }
            let items = [home, ...(data.items ?? [])].filter(Boolean)

            if (!this.trails.length) {
                this.trails = [...items]
            }
            else {
                let current = items[items.length - 1]

                if (data.replace) {
                    let index = this.trails.findIndex(item => item.title === current.title && item.url === current.url)
                    let last = this.trails[this.trails.length - 1]
                    let rooted = this.trails.some(item => item.home && item.title === home.title && item.url === home.url)

                    // the page is already in the trail, drop everything below it
                    if (index > -1) {
                        this.trails.splice(index + 1)
                    }
                    // `replace` swaps out the sibling crumb this page stands in for, so it assumes
                    // the trail ends with that sibling. That only holds while moving within the
                    // hierarchy — when the crumb we would overwrite is a trail root, or the trail
                    // belongs to another hierarchy altogether, we are entering from outside, so
                    // seed the page's own declared trail instead of destroying the root
                    else if (last?.home || !rooted) {
                        this.trails = [...items]
                    }
                    else {
                        this.trails.splice(this.trails.length - 1, 1, current)
                    }
                }
                else {
                    let index = this.trails.findIndex(item => item.url === current.url)

                    if (index === -1) {
                        this.trails.push(current)
                    }
                    else {
                        this.trails.splice(index)
                        this.trails.push(current)
                    }
                }
            }

            // href will be use in the actual navigation when click on the breadcrumbs trail
            this.trails = this.trails.map(item => ({ href: item.url, ...item }))

            // measure again once the list has rendered the new trail
            this.$nextTick?.(() => this.fit())
        },
    }
}