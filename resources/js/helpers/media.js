// The viewport widths the shell layouts switch at, and a media-query listener that hands back its own stop function.
export const LG = '(min-width: 64rem)'

/**
 * Call `callback` whenever `query` starts or stops matching. Returns the function that stops listening.
 */
export const onMedia = (query, callback) => {
    const list = window.matchMedia(query)

    list.addEventListener('change', callback)

    return () => list.removeEventListener('change', callback)
}
