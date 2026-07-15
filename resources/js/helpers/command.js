export default (name = null) => {
    return {
        show () {
            return dispatchEvent(new CustomEvent('atom-command-show', { detail: { name } }))
        },

        close () {
            return dispatchEvent(new CustomEvent('atom-command-close', { detail: { name } }))
        },
    }
}
