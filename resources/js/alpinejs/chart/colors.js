// The colours of <atom:chart>. A series takes a tone, never a palette step: the name is mapped onto a tone the way Color::tone() maps a
// colour name on the server (red is danger, green success, orange and yellow warning...), and the tone's ink is read from the role
// token on the page, so a chart follows the theme and the host's accent. ApexCharts paints with plain colours, so a token is resolved
// to `#rrggbb` (or `rgba()` for the hairline) through a probe element and a canvas, which also turns the accent's relative colour
// (`oklch(from ...)`) into something Apex can handle. A hex a host passes is used as it is.

const TONES = {
    info: ['info', 'blue', 'sky', 'cyan', 'indigo'],
    success: ['success', 'green', 'emerald', 'teal', 'lime'],
    warning: ['warning', 'yellow', 'amber', 'orange'],
    danger: ['danger', 'error', 'red', 'rose'],
    accent: ['accent', 'primary', 'purple', 'violet', 'fuchsia', 'pink'],
}

// the role token each tone's ink is read from (the fg half of the pair; the accent's is its ink)
const INK = {
    neutral: '--atom-fg-subtle',
    info: '--atom-info-fg',
    success: '--atom-success-fg',
    warning: '--atom-warning-fg',
    danger: '--atom-danger-fg',
    accent: '--atom-accent-ink',
}

/** The tone a colour name stands for; `neutral` for gray, an unknown name or no name at all. */
export const toneOf = (name) => {
    const key = typeof name === 'string' ? name.trim().toLowerCase() : ''

    return Object.keys(TONES).find((tone) => TONES[tone].includes(key)) ?? 'neutral'
}

let context = null

/** A CSS colour as a browser draws it, as `#rrggbb`, or `rgba()` when it is not opaque. */
const toPlain = (css) => {
    context ??= document.createElement('canvas').getContext('2d', { willReadFrequently: true })
    context.clearRect(0, 0, 1, 1)
    context.fillStyle = '#000'
    context.fillStyle = css
    context.fillRect(0, 0, 1, 1)

    const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data

    if (a === 255) return '#' + [r, g, b].map((channel) => channel.toString(16).padStart(2, '0')).join('')

    return `rgba(${r}, ${g}, ${b}, ${Math.round((a / 255) * 100) / 100})`
}

// what a token falls back to on a page that does not define it (no atom.tailwind.css): neutral greys that read on a light or a dark page
const FALLBACK = {
    '--atom-fg': '#6f6b62',
    '--atom-fg-muted': '#6f6b62',
    '--atom-fg-subtle': '#8d887e',
    '--atom-border': '#d3cfc6',
    '--atom-canvas': '#ffffff',
    '--atom-accent-ink': '#4c55c9',
    '--atom-info-fg': '#1f4fa0',
    '--atom-success-fg': '#17663a',
    '--atom-warning-fg': '#7a5200',
    '--atom-danger-fg': '#a3261c',
}

/**
 * The value of a role token (`--atom-fg-muted`) as the chart's own element sees it, as a plain colour. The probe lives inside `el`, so a
 * nested `.dark` subtree or a density scope is honoured; a page with no tokens gets a grey and never a full-ink line.
 */
export const role = (name, el = document.documentElement) => {
    const probe = document.createElement('span')

    probe.style.cssText = `position: absolute; visibility: hidden; color: var(${name}, ${FALLBACK[name] ?? FALLBACK['--atom-fg-muted']})`
    el.append(probe)

    const css = getComputedStyle(probe).color

    probe.remove()

    return toPlain(css)
}

/** The colour of a series: a hex as it is, else the ink of the tone the name stands for (`fallback` when there is no name). */
export const seriesColor = (name, fallback = 'neutral', el = document.documentElement) => {
    if (typeof name === 'string' && name.startsWith('#')) return name

    return role(INK[name ? toneOf(name) : fallback], el)
}

/** What the chart draws around the series, from the tokens: text, the hairline grid, and the label of the goal line. */
export const chrome = (el = document.documentElement) => ({
    text: role('--atom-fg-muted', el),
    grid: role('--atom-border', el),
    line: role('--atom-fg', el),
    onLine: role('--atom-canvas', el),
})

/** The tooltip Apex prints for a point: the tooltip's recipe (the foreground as the fill, the canvas as the text), as inline styles so no host build has to find a class. */
export const tooltipHtml = (text) => {
    const tip = document.createElement('div')

    tip.style.cssText = 'background: var(--atom-fg); color: var(--atom-canvas); font-size: 12px; line-height: 1.4; border-radius: 6px; padding: 4px 8px;'
    tip.innerText = text

    return tip.outerHTML
}
