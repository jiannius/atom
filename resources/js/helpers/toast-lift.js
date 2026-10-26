import onMorphed from './morphed'

// A toast at the bottom of the screen must never sit on a pinned save bar (`<atom:form.actions bar>`,
// [data-atom-save-bar]): the Save button would be unreachable until the toast is dismissed. The toast is a
// popover in the top layer, so no z-index can help; it is lifted instead, by an inline margin-bottom that
// leaves the bar's top edge below the toast's own padding.
//
// liftAbove(root) lifts the open toast `root` now, keeps the lift right while it is open, and returns a
// function that stops watching and puts the toast back. What can change under an open toast:
// - the bar's height (its buttons wrap at a resize), the viewport, or the page's height: a ResizeObserver on
//   the bars and on <html>, plus `resize`;
// - the bar's place (the page scrolls to its end and the bar takes the page's bottom room): `scroll`, in the
//   capture phase so a scroller that is not the window counts too;
// - a modal that opens or closes under the toast (a bar behind it stops or starts counting): atom's `opened` and the dialog's own
//   `close`, which does not bubble and so is heard in the capture phase;
// - a bar that comes or goes (a Livewire morph, a wire:navigate): onMorphed() and `livewire:navigated`. The
//   margin is an inline style, which a morph strips from the toast root, so the morph also puts it back.
// Every trigger lands in one rAF, so the observers can never report a resize loop.
//
// A bar counts only when it is rendered and in the viewport (a closed modal's is display:none), is not behind an open modal
// (unless it is inside that modal) or under an [inert] ancestor, and only when it
// is in the toast's way (it overlaps the strip the toast takes at the bottom): a bar half way up a short page
// is not. The topmost of those decides. The lift never pushes the toast off the top of the screen.
const BAR = '[data-atom-save-bar]'

const viewportHeight = () => document.documentElement.clientHeight

// the open modal dialogs: the page behind one cannot be reached, so a bar there is not in anyone's way
const openModals = () => {
    try {
        return [...document.querySelectorAll('dialog:modal')]
    }
    catch (e) {
        return [...document.querySelectorAll('dialog[open]')]
    }
}

const visibleBars = () => [...document.querySelectorAll(BAR)].filter((bar) => {
    if (bar.closest('[inert]')) return false

    const modals = openModals()

    if (modals.length && ! modals.some((modal) => modal.contains(bar))) return false

    if (bar.getClientRects().length === 0) return false
    if (typeof bar.checkVisibility === 'function' && ! bar.checkVisibility({ visibilityProperty: true })) return false

    const rect = bar.getBoundingClientRect()

    return rect.height > 0 && rect.bottom > 0 && rect.top < viewportHeight()
})

/**
 * How far to lift the toast `root` so that its bottom sits above the topmost save bar in its way, in px (0: none).
 */
export const liftFor = (root, bars = visibleBars()) => {
    const height = viewportHeight()
    const toastTop = height - root.offsetHeight
    const tops = bars
        .map((bar) => bar.getBoundingClientRect())
        .filter((rect) => rect.bottom > toastTop)
        .map((rect) => rect.top)

    if (! tops.length) return 0

    return Math.max(0, Math.min(height - Math.min(...tops), toastTop))
}

export default (root) => {
    let frame = null
    let stopped = false
    let observed = new Set()

    const resizes = typeof ResizeObserver === 'function' ? new ResizeObserver(() => schedule()) : null

    const apply = () => {
        const bars = visibleBars()
        const lift = liftFor(root, bars)

        root.style.marginBottom = lift ? lift + 'px' : ''

        if (! resizes) return

        // watch the bars that exist now, drop the ones that went
        const next = new Set(document.querySelectorAll(BAR))

        observed.forEach((bar) => next.has(bar) || resizes.unobserve(bar))
        next.forEach((bar) => observed.has(bar) || resizes.observe(bar))
        observed = next
    }

    const schedule = () => {
        if (stopped || frame !== null) return

        frame = requestAnimationFrame(() => {
            frame = null
            if (! stopped) apply()
        })
    }

    // the morph just ran: put the margin back at once (no frame between, so nothing flashes), then check again next frame
    const stopMorph = onMorphed(() => {
        if (stopped) return

        apply()
        schedule()
    })

    addEventListener('resize', schedule)
    addEventListener('scroll', schedule, { capture: true, passive: true })
    addEventListener('livewire:navigated', schedule)
    addEventListener('close', schedule, { capture: true })
    addEventListener('opened', schedule, { capture: true })
    resizes?.observe(document.documentElement)
    apply()

    return () => {
        stopped = true

        if (frame !== null) cancelAnimationFrame(frame)

        stopMorph()
        removeEventListener('resize', schedule)
        removeEventListener('scroll', schedule, { capture: true })
        removeEventListener('livewire:navigated', schedule)
        removeEventListener('close', schedule, { capture: true })
        removeEventListener('opened', schedule, { capture: true })
        resizes?.disconnect()
        root.style.marginBottom = ''
    }
}
