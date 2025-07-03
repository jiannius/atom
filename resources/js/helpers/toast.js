export default (config) => {
    dispatchEvent(new CustomEvent('atom-toast-show', { detail: config }))
}