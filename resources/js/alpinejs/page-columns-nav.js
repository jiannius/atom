import onMorphed, { stopMorphed } from '../helpers/morphed'

// <atom:page.columns>'s `nav` slot. Below 768px of row the navlist in it is a strip that scrolls sideways (atom.css), and a settings
// screen's current item can be past the edge of it: the strip is scrolled, never the page (`scrollIntoView` would move the page too), so
// the current item is fully inside it when the strip appears, after a `wire:navigate` (a nav that survives it) and after a Livewire
// morph that changes the current item. Beside the main column the navlist does not scroll sideways and nothing here moves.
// The reader's own gesture on the strip wins: once they have scrolled it, a resize or a morph that leaves the current item where it
// was does not scroll it back. The factory is on the slot's own element, so a morph that replaces the slot runs init() again;
// the morph callback is kept on the element so a re-run replaces it and listeners are removed in destroy().
const TOUCH = ['wheel', 'touchstart', 'pointerdown', 'keydown', 'focusin']

export default () => ({
    init () {
        this.list = this.$el.querySelector('[data-atom-navlist]')

        if (! this.list) return

        this.touched = false
        this.seen = this.key()

        // a wheel counts only when it moves the strip sideways (deltaX, or shift): a vertical one is the page scrolling under the pointer
        this.touch = (event) => {
            if (event.type === 'wheel' && event.deltaX === 0 && ! event.shiftKey) return

            this.touched = true
        }
        this.settle = () => {
            if (! this.touched) this.reveal()
        }
        this.navigated = () => {
            this.touched = false
            this.seen = this.key()
            this.reveal()
        }

        TOUCH.forEach((type) => this.list.addEventListener(type, this.touch, { passive: true }))
        document.addEventListener('livewire:navigated', this.navigated)

        this.settle()

        // the strip appears when the row narrows (the list is resized), and the items change width when a web font arrives
        this.observer = typeof ResizeObserver === 'function' ? new ResizeObserver(this.settle) : null
        this.observer?.observe(this.list)
        document.fonts?.ready.then(() => this.list?.isConnected && this.settle())

        onMorphed(() => {
            // a current item that is not the one the strip was showing is a navigation: it is brought into view whatever the reader did before
            const key = this.key()

            if (key !== this.seen) {
                this.seen = key
                this.touched = false
            }

            this.settle()
        }, { owner: this.$el, key: '_atomPageColumnsNavStopMorph', scope: this.$el, nested: true })
    },

    /**
     * What names the current item (its href, else its text), so two renders of the same item read alike
     */
    key () {
        const current = this.list?.querySelector('[aria-current="page"]')

        return current ? (current.getAttribute('href') ?? current.textContent.trim()) : null
    },

    /**
     * Scroll the strip, never the page, until the current item is fully inside it (centred when it has to move); nothing when the
     * navlist does not scroll sideways
     */
    reveal () {
        const list = this.list

        if (! list || getComputedStyle(list).overflowX !== 'auto' || list.scrollWidth <= list.clientWidth + 1) return

        const current = list.querySelector('[aria-current="page"]')

        if (! current) return

        const room = list.getBoundingClientRect()
        const item = current.getBoundingClientRect()

        if (item.left >= room.left && item.right <= room.right) return

        list.scrollLeft += (item.left + item.width / 2) - (room.left + room.width / 2)
    },

    destroy () {
        TOUCH.forEach((type) => this.list?.removeEventListener(type, this.touch))
        document.removeEventListener('livewire:navigated', this.navigated)
        this.observer?.disconnect()
        stopMorphed(this.$el, '_atomPageColumnsNavStopMorph')
    },
})
