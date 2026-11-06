// `$clipboard(text)` is the browser's own write: it resolves once the text is on the clipboard and rejects when the
// browser refuses it (an insecure context, no permission, the document not focused), so a caller can `.catch()` it.
export default () => {
    return (subject) => {
        try {
            return Promise.resolve(navigator.clipboard.writeText(subject))
        } catch (error) {
            // no navigator.clipboard at all (an insecure context) throws before there is a promise to reject
            return Promise.reject(error)
        }
    }
}
