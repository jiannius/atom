export default () => {
    return (subject) => {
        return (new Promise((resolve, reject) => {
            try {
                navigator.clipboard.writeText(subject)
            } catch (error) {
                console.error('Unable to copy to clipboard.')
            }

            resolve()
        }))
    }
}