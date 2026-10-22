/**
 * Format a number as money: at least 2 decimals, at most `maxPrecision` (clamped 2-100, Intl's limit).
 * Intl rounds half away from zero, as PHP's half-up does. `round` snaps to the 0.05 step.
 */
export default (value, symbol = null, round = false, maxPrecision = null) => {
    const config = { minimumFractionDigits: 2 }

    // A numeric string counts ("6"); null, undefined, '' and NaN mean unset (Number(null) is 0, which would clamp to 2)
    const places = (maxPrecision === null || maxPrecision === undefined || String(maxPrecision).trim() === '') ? NaN : Number(maxPrecision)
    if (!Number.isNaN(places)) config.maximumFractionDigits = Math.min(100, Math.max(2, places))

    let currency
    let num = Number(value)

    if (round) {
        num = num + Number.EPSILON
        const rounded = Math.round(num * 2 * 10)/10/2
        currency = rounded.toLocaleString('en-US', config)
    }
    else {
        currency = num.toLocaleString('en-US', config)
    }

    return symbol ? symbol+' '+currency : currency
}
