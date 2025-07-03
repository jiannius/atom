export default (config) => {
    return new Promise((resolve, reject) => {
        dispatchEvent(new CustomEvent('atom-alert-show', { detail: {
            ...config,
            onDismissed: () => resolve(),
        }}))
    })
}