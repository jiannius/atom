import { computePosition, autoUpdate, flip, shift, offset } from '@floating-ui/dom'

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
        ...config,
    }

    let updatePosition = () => {
        computePosition(anchor, element, {
            placement: config.placement,
            // Use 'fixed' strategy so FloatingUI returns viewport-relative coordinates.
            // Native popover elements live in the browser top layer and behave like
            // position:fixed — without this flag, computePosition adds the scroll offset
            // and the computed top/left value places the panel outside the viewport.
            strategy: 'fixed',
            middleware: [offset(config.offset), flip(), shift({ padding: 5 })],
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