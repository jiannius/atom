Object.defineProperty(Array.prototype, 'pluck', {
    enumerable: false,
    value: function (attr) {
        if (!Array.isArray(this)) return this
        return this.map(val => (val[attr]))
    }
})

Object.defineProperty(Array.prototype, 'unique', {
    enumerable: false,
    value: function (attr = null) {
        if (!Array.isArray(this)) return this

        if (typeof attr === 'function') {
            let values = this.map(row => (attr(row)))
            return [...new Set(values)].map(val => (
                this.find(row => (attr(row) == val))
            ))
        }
        else if (attr) {
            const values = this
                .map(row => (row[attr]))
                .map(val => (this.find(row => (row[attr] == val))))
            return [...new Set(values)]
        }

        return [...new Set([...this])]
    }
})

Object.defineProperty(Array.prototype, 'sum', {
    enumerable: false,
    value: function (attr = null) {
        if (!Array.isArray(this)) return this

        return this.reduce((acc, value) => {
            if (typeof attr === 'function') value = attr(value)
            else if (attr) value = value[attr]
            return +value + acc
        }, 0)
    }
})

Object.defineProperty(Array.prototype, 'where', {
    enumerable: false,
    value: function (key, value) {
        if (!Array.isArray(this)) return this
        return this.filter(item => (item[key] === value))
    }
})

Object.defineProperty(Array.prototype, 'firstWhere', {
    enumerable: false,
    value: function (key, value) {
        if (!Array.isArray(this)) return this
        let index = this.findIndex(item => (item[key] === value))
        return this[index]
    }
})

Object.defineProperty(Array.prototype, 'toggle', {
    enumerable: false,
    value: function (value) {
        if (!Array.isArray(this)) return this
        const index = this.indexOf(value)
        if (index === -1) this.push(value)
        else this.splice(index, 1)
        return this
    }
})
