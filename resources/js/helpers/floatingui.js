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
            middleware: [offset(config.offset), flip(), shift({ padding: 5 })],
        }).then(({x, y}) => {
            Object.assign(element.style, { left: x+'px', top: y+'px' })
        })
    }

    return autoUpdate(anchor, element, updatePosition)
}