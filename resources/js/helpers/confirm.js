export default (config) => {
    return new Promise((resolve, reject) => {
        dispatchEvent(new CustomEvent('atom-confirm-show', { detail: {
            ...config,
            onAccepted: (password = null, passphrase = null, reason = null) => resolve({ password, passphrase, reason }),
            onRejected: () => reject(),
        }}))
    })
}
