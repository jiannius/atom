// Return the URL if it is safe to bind to an href or navigate to, else null.
// Mirrors the PHP safe_url() helper (src/Helpers.php) — keep the two in step.
//
// Allowed: http, https, mailto, tel, sms, and any scheme-less URL (relative,
// root-relative, protocol-relative, fragment-only, query-only). The check runs
// on what a browser would see: HTML entities are decoded until stable, tab and
// newline are dropped from anywhere, leading control characters and spaces are
// stripped, and the scheme is lowercased. The first path segment is treated as
// a scheme when it holds a colon, so `foo:bar` must be written `./foo:bar`.
const allowed = ['http', 'https', 'mailto', 'tel', 'sms']

// The HTML parser's own decoder, so named and unterminated references behave
// exactly as they would in an attribute. The textarea is never attached.
const decode = (value) => {
    let el = document.createElement('textarea')
    el.innerHTML = value
    return el.value
}

export default (url) => {
    if (typeof url === 'number') url = String(url)
    if (typeof url !== 'string' || url === '') return null

    let normalised = url

    for (let pass = 0; ; pass++) {
        if (pass >= 8) return null // never settles: not a URL worth trusting

        let decoded = decode(normalised).replace(/[\t\n\r]/g, '')

        if (decoded === normalised) break

        normalised = decoded
    }

    normalised = normalised.replace(/^[\x00-\x20]+/, '')

    let segment = normalised.split(/[/?#]/)[0]
    let colon = segment.indexOf(':')

    if (colon === -1) return url

    let scheme = segment.slice(0, colon).replace(/[A-Z]/g, c => c.toLowerCase()) // ASCII only, like PHP

    return allowed.includes(scheme) ? url : null
}
