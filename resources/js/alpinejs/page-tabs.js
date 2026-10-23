import onMorphed from '../helpers/morphed'

// <atom:page.tabs>: a strip that scrolls sideways when its tabs do not fit. Two things for the reader on a phone:
// the current tab is brought into view when the strip appears (a record's "Messages" tab was off-screen at 390px with nothing to
// say the strip scrolls), and an edge that has more tabs past it fades (CSS mask on [data-fade-start] / [data-fade-end] in atom.css).
// The marks are written by hand, so a Livewire morph, which strips every attribute the server did not render, writes them again.
export default () => ({
    init () {
        this.list = this.$refs.list

        if (! this.list) return

        this.update = () => {
            const max = this.list.scrollWidth - this.list.clientWidth
            const left = Math.abs(this.list.scrollLeft)

            this.list.toggleAttribute('data-fade-start', max > 1 && left > 1)
            this.list.toggleAttribute('data-fade-end', max > 1 && left < max - 1)
        }

        this.reveal()
        this.update()

        this.list.addEventListener('scroll', this.update, { passive: true })
        this.observer = typeof ResizeObserver === 'function' ? new ResizeObserver(this.update) : null
        this.observer?.observe(this.list)

        this.$root._atomPageTabsStopMorph?.()
        this.$root._atomPageTabsStopMorph = onMorphed((el) => {
            if (! el || el.contains(this.$root) || this.$root.contains(el)) this.update()
        })
    },

    /**
     * Scroll the strip, never the page, until the current tab is fully inside it (centred when it has to move)
     */
    reveal () {
        const current = this.list.querySelector('[aria-current="page"]')

        if (! current) return

        const room = this.list.clientWidth
        const start = current.offsetLeft - this.list.offsetLeft
        const end = start + current.offsetWidth

        if (this.list.scrollWidth <= room) return

        if (start < this.list.scrollLeft || end > this.list.scrollLeft + room) {
            this.list.scrollLeft = Math.max(0, start - (room - current.offsetWidth) / 2)
        }
    },

    destroy () {
        this.list?.removeEventListener('scroll', this.update)
        this.observer?.disconnect()
        this.$root._atomPageTabsStopMorph?.()
    },
})
