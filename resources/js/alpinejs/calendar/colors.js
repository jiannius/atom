// Colours for <atom:calendar>: the accent edge and the dot of an item given a hex colour.
//
// The tint behind such an item is the hex at 18% and the text on it stays neutral, so any colour reads. The edge and the dot are a
// cue and have to show against the ground in both themes (3:1), which a host's own colour may not do: a pale orange on white, a navy on
// the dark ground. Each is moved toward black for the light ground and toward white for the dark one, only as far as it must, so a colour
// that already shows is used as it is. Pure, no DOM.

// the grounds the edge sits on: white, and zinc-800 (what atom's card is in dark mode), and how well it has to show, with a little over 3
// for the tint under it
export const LIGHT_GROUND = [255, 255, 255]
export const DARK_GROUND = [39, 39, 42]
export const EDGE_CONTRAST = 3.4

// #rgb, #rgba, #rrggbb or #rrggbbaa as [r, g, b], or null; the alpha is left out, as the item's tint is drawn from the colour alone
export const parseHex = (value) => {
    // the hash is part of it, as in the server's Color::isHex() and the engine's isHex(): `abc` is no colour
    let digits = typeof value === 'string' && /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value) ? value.slice(1) : null

    if (digits === null) return null

    if (digits.length <= 4) digits = [...digits.slice(0, 3)].map((digit) => digit + digit).join('')
    else digits = digits.slice(0, 6)

    return [0, 2, 4].map((at) => parseInt(digits.slice(at, at + 2), 16))
}

const toHex = (rgb) => '#' + rgb.map((channel) => Math.round(channel).toString(16).padStart(2, '0')).join('')

const luminance = (rgb) => {
    let [r, g, b] = rgb.map((channel) => {
        let value = channel / 255

        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
    })

    return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

export const contrast = (a, b) => {
    let [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)

    return (hi + 0.05) / (lo + 0.05)
}

// Move `rgb` toward `target` in steps of 4% until it shows against `ground`; as it is when it already does.
const until = (rgb, target, ground) => {
    for (let share = 0; share <= 1; share += 0.04) {
        let mixed = rgb.map((channel, i) => channel + (target[i] - channel) * share)

        if (contrast(mixed, ground) >= EDGE_CONTRAST) return mixed
    }

    return target
}

// The edge colour of a hex, for the light and the dark ground: { light, dark } as #rrggbb, or null for something that is not a hex.
export const edgeColors = (value) => {
    let rgb = parseHex(value)

    if (!rgb) return null

    return {
        light: toHex(until(rgb, [0, 0, 0], LIGHT_GROUND)),
        dark: toHex(until(rgb, [255, 255, 255], DARK_GROUND)),
    }
}
