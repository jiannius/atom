Number.prototype.currency = function(symbol = null, round = false, maxPrecision = null) {
    const config = { minimumFractionDigits: 2 }

    // at least 2 decimals, at most maxPrecision; Intl rounds half away from zero, as PHP's half-up does
    if (maxPrecision !== null) config.maximumFractionDigits = Math.max(2, maxPrecision)

    let currency
    let num = Number(this)

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
