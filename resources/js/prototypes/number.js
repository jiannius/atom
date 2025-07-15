Number.prototype.currency = function(symbol = null, round = false) {
    const config = { minimumFractionDigits: 2 }

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
