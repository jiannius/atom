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
            Object.assign(element.style, { left: x+'px', top: y+'px' })
        })
    }

    if (!config.autoUpdate) {
        updatePosition()

        return () => {}
    }

    return autoUpdate(anchor, element, updatePosition)
}