import { computePosition, autoUpdate, flip, shift, size, offset } from '@floating-ui/dom'

// What a panel carried inline before size() first touched it, so resetting the
// cap puts a host's own inline max-height back rather than wiping it.
const inlineBeforeSize = new WeakMap()

export default (anchor, element, config = {}) => {
    config = {
        placement: 'bottom-start',
        offset: 2,
        // autoUpdate keeps the panel glued to its anchor on scroll/resize while
        // it stays open (right for a menu). Pass false for a transient panel
        // (e.g. a hover tooltip) that should be positioned once and dismissed
        // rather than chase the page — avoids a persistent loop that would
        // outlive a wire:navigate page swap.
        autoUpdate: true,
        // Cap the panel at the room it has on the side it landed on and let it
        // scroll, so a panel taller than the viewport (a date picker with its
        // time row, about 360px, on a 620px screen) can still be reached rather
        // than running off the bottom, or the top once flipped. Pass false for a
        // panel that must never scroll.
        size: true,
        ...config,
    }

    let updatePosition = () => {
        let scrolled = element.scrollTop

        // Measure the panel at its natural height. Left over from the previous
        // pass, the cap would make flip() think it fits on the side it is
        // already on and never move it to the roomier one.
        if (config.size) {
            if (!inlineBeforeSize.has(element)) {
                inlineBeforeSize.set(element, { maxHeight: element.style.maxHeight, overflowY: element.style.overflowY })
            }

            Object.assign(element.style, inlineBeforeSize.get(element))
        }

        computePosition(anchor, element, {
            placement: config.placement,
            // Use 'fixed' strategy so FloatingUI returns viewport-relative coordinates.
            // Native popover elements live in the browser top layer and behave like
            // position:fixed — without this flag, computePosition adds the scroll offset
            // and the computed top/left value places the panel outside the viewport.
            strategy: 'fixed',
            // Order matters: size() has to read the placement flip() settled on.
            middleware: [
                offset(config.offset),
                flip(),
                shift({ padding: 5 }),
                ...(config.size ? [size({
                    padding: 5,
                    apply({ availableHeight, elements }) {
                        let { floating } = elements
                        let max = Math.max(0, availableHeight)

                        // Only a panel that does not fit becomes a scroll
                        // container: overflow-y:auto on one that does would clip
                        // whatever it lets hang outside its box (a focus ring on
                        // an edge control, an arrow, a nested menu). A max-height
                        // of the panel's own is already in force in offsetHeight,
                        // so it can only be tightened here, never raised.
                        if (floating.offsetHeight > max) {
                            Object.assign(floating.style, { maxHeight: max+'px', overflowY: 'auto' })
                            // Clearing the cap above dropped the scroll position; put it back.
                            floating.scrollTop = scrolled
                        }
                    },
                })] : []),
            ],
        }).then(({x, y}) => {
            // The panels are native [popover] elements, and the UA stylesheet gives
            // them `inset: 0; margin: auto` — which centres them in the leftover space
            // once left/top are set, dragging the panel off its anchor. Neutralise the
            // margin and release the right/bottom insets so left/top are the only
            // constraints. Assign right/bottom individually, never via the `inset`
            // shorthand, which would wipe the left/top set in the same call.
            Object.assign(element.style, {
                margin: '0',
                right: 'auto',
                bottom: 'auto',
                left: x+'px',
                top: y+'px',
            })
        })
    }

    if (!config.autoUpdate) {
        updatePosition()

        return () => {}
    }

    return autoUpdate(anchor, element, updatePosition)
}