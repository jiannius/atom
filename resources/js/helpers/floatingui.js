import { computePosition, autoUpdate, flip, shift, offset } from '@floating-ui/dom'

export default (anchor, element, config = {}) => {
    config = {
        placement: 'bottom-start',
        offset: 2,
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

    return autoUpdate(anchor, element, updatePosition)
}