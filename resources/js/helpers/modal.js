export default (name = null) => {
    return {
        show () {
            return dispatchEvent(new CustomEvent('atom-modal-show', { detail: { name } }))
        },

        slide (position = null) {
            return dispatchEvent(new CustomEvent('atom-modal-show', { detail: {
                name,
                position,
                variant: 'slide',
            } }))
        },

        close () {
            return dispatchEvent(new CustomEvent('atom-modal-close', { detail: { name } }))
        },
    }
}