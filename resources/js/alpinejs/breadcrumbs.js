export default (config) => {
    return {
        trails: [],
    
        get breadcrumbs () {
            // Return the trail from the last element up to and including the first element with home: true
            let idx = this.trails.slice().reverse().findIndex(item => item.home)
            if (idx === -1) return []
            let start = this.trails.length - 1 - idx
            return this.trails.slice(start)
        },

        back () {
            let idx = this.breadcrumbs.length - 2
            if (idx > -1) Livewire.navigate(this.breadcrumbs[idx].url)
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
        },
    }
}