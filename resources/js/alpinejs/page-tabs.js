import onMorphed from '../helpers/morphed'

// <atom:page.tabs>: a strip that scrolls sideways when its tabs do not fit. Two things for the reader on a phone:
// the current tab is brought into view when the strip appears (a record's "Messages" tab was off-screen at 390px with nothing to
// say the strip scrolls), and an edge that has more tabs past it fades (CSS mask on [data-fade-start] / [data-fade-end] in atom.css).
// The marks are written by hand, so a Livewire morph, which strips every attribute the server did not render, writes them again.
// The tabs change width after the first paint (a web font arrives, a hidden strip is shown, a tab gains a count), so the current
// tab is brought into view again whenever the strip or one of its tabs is resized, until the reader scrolls the strip themselves.
const TOUCH = ['wheel', 'touchstart', 'pointerdown', 'keydown']

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

        // the reader's own gesture; not `scroll`, which the reveal fires itself
        this.touched = false
        this.touch = () => { this.touched = true }
        this.settle = () => {
            if (! this.touched) this.reveal()

            this.update()
        }
        this.watch = () => this.list.querySelectorAll('li').forEach((li) => this.observer?.observe(li))

        this.settle()

        this.list.addEventListener('scroll', this.update, { passive: true })
        TOUCH.forEach((type) => this.list.addEventListener(type, this.touch, { passive: true }))

        this.observer = typeof ResizeObserver === 'function' ? new ResizeObserver(this.settle) : null
        this.observer?.observe(this.list)
        this.watch()
        document.fonts?.ready.then(() => this.list?.isConnected && this.settle())

        this.$root._atomPageTabsStopMorph?.()
        this.$root._atomPageTabsStopMorph = onMorphed((el) => {
            if (! el || el.contains(this.$root) || this.$root.contains(el)) {
                this.watch()
                this.update()
            }
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
        TOUCH.forEach((type) => this.list?.removeEventListener(type, this.touch))
        this.observer?.disconnect()
        this.$root._atomPageTabsStopMorph?.()
    },
})
