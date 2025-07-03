export default (config) => {
    return new Promise((resolve, reject) => {
        dispatchEvent(new CustomEvent('atom-confirm-show', { detail: {
            ...config,
            onAccepted: (password = null, passphrase = null) => resolve({ password, passphrase }),
            onRejected: () => reject(),
        }}))
    })
}