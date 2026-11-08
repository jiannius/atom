// A shell's panel that behaves as a modal layer below lg (layouts.sidebar's drawer, layouts.header's menu panel).
//
// While `isOpen()` is true the page behind (`page`, a selector of nodes the LAYOUT renders, outside any Livewire component, so a
// morph never strips what is written on them) is `inert`, and the focus moves into `container()`: the link of the page you are on,
// else the first focusable thing. When it closes the page is let go and, if the shell gave a `returnTo()`, the focus goes there.
//
// - A MODAL dialog (`dialog[open]:modal`) lifts the inert while it is open: it blocks the rest of the document itself, and a dialog
//   inside an inert <main> would be dead. A non-modal one (show(), a permanent <dialog open>) blocks nothing and lifts nothing. When
//   a modal closes, or is removed while open (the observer watches childList), the focus it gave back is pulled into the layer again
//   if it is lost; any other mutation (a poll, a stream, a toast) leaves the focus and the scroll alone.
// - Only the `inert` this layer wrote is ever removed: a host's own, or another instance's, is left alone.
// - A wire:navigate (or Back) caches the page as it is when it navigates away, so `livewire:navigating` (fired before that snapshot)
//   closes the layer through `close()` and lets the page go; Back then restores a closed page. No focus is moved for that.
// - `start()` is safe to run again without `stop()`: it stops the old listeners first and keeps what the layer made inert.
//
// The shell calls sync() when its own state changes; `attributes` are the attributes the observer should also watch (a body flag),
// and `listen(sync)` may subscribe to something else (a media query) and return the function that unsubscribes.
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

export default ({ isOpen, page, container, scroll = null, returnTo = null, close, attributes = [], listen = null }) => {
    let applied = new Set()
    let open = false
    let modal = false
    let observer = null
    let unlisten = null
    let leaving = null

    /**
     * The focus is nowhere (the page, or a node that has just become inert).
     */
    const lost = () => {
        let active = document.activeElement

        return !active || active === document.body || !!active.closest('[inert]')
    }

    /**
     * Into the layer: the link of the page you are on, else the first focusable thing. preventScroll, because the layer may still be
     * sliding in; the scroll box inside it is moved by hand.
     */
    const focusIn = () => {
        let root = container()

        if (!root) return

        let target = [...root.querySelectorAll('[aria-current="page"]')].find(shown) ?? [...root.querySelectorAll(FOCUSABLE)].find(shown)

        if (!target) return

        target.focus({ preventScroll: true })

        let box = scroll ? target.closest(scroll) : null

        if (!box) return

        let inner = box.getBoundingClientRect()
        let rect = target.getBoundingClientRect()

        if (rect.top < inner.top) box.scrollTop -= inner.top - rect.top
        else if (rect.bottom > inner.bottom) box.scrollTop += rect.bottom - inner.bottom
    }

    /**
     * Back to where the shell says (its menu button), unless a layer above the page has the focus (a dialog, a dropdown) or the button is not drawn.
     */
    const focusOut = () => {
        let button = returnTo?.()

        if (!button || button.getClientRects().length === 0) return
        if (document.activeElement?.closest('dialog[open], :popover-open:not([data-atom-toast])')) return

        button.focus()
    }

    /**
     * Make the page inert, or give back what this layer made inert (an `inert` that was there already is not ours).
     */
    const setInert = (on) => {
        if (on) {
            document.querySelectorAll(page).forEach((el) => {
                if (el.hasAttribute('inert')) return

                el.setAttribute('inert', '')
                applied.add(el)
            })
        }
        else {
            applied.forEach((el) => el.removeAttribute('inert'))
            applied.clear()
        }
    }

    const sync = () => {
        let want = !!isOpen()
        let wasOpen = open

        // closed and staying closed: the common case, a mutation somewhere in the page
        if (!want && !wasOpen) return

        let isModal = want && modalOpen()
        let modalClosed = modal && !isModal

        open = want
        modal = isModal
        setInert(want && !isModal)

        if (want && !wasOpen) focusIn()
        // a modal closed (or was removed while open) and the focus it gave back is on a page that is inert again
        else if (want && modalClosed && lost()) focusIn()
        else if (!want && wasOpen) focusOut()
    }

    const stop = () => {
        observer?.disconnect()
        observer = null
        unlisten?.()
        unlisten = null

        if (leaving) document.removeEventListener('livewire:navigating', leaving)

        leaving = null
    }

    const start = () => {
        stop()

        // the shell's attributes, a dialog opening or closing anywhere (its `open` attribute), and a dialog taken out of the page while
        // it is open (a re-render removes it with no `open` change); sync() is cheap and does nothing when nothing changed.
        // One observe() call: a second one on the same node replaces the options of the first.
        observer = new MutationObserver(sync)
        observer.observe(document.body, { attributes: true, attributeFilter: [...attributes, 'open'], subtree: true, childList: true })
        unlisten = listen?.(sync) ?? null

        // the page is cached for Back as it is when it is left: leave it closed
        leaving = () => {
            open = false
            modal = false
            setInert(false)
            close()
        }

        document.addEventListener('livewire:navigating', leaving)

        sync()
    }

    return { start, stop, sync, release: () => setInert(false) }
}
