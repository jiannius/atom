/**
 * Array helpers: `atom.arr.pluck(rows, 'id')`. Each returns a non-array unchanged.
 */
const isArray = Array.isArray

export default {
    pluck: (arr, attr) => {
        if (!isArray(arr)) return arr
        return arr.map(val => (val[attr]))
    },

    unique: (arr, attr = null) => {
        if (!isArray(arr)) return arr

        if (typeof attr === 'function') {
            let values = arr.map(row => (attr(row)))
            return [...new Set(values)].map(val => (
                arr.find(row => (attr(row) == val))
            ))
        }
        else if (attr) {
            const values = arr
                .map(row => (row[attr]))
                .map(val => (arr.find(row => (row[attr] == val))))
            return [...new Set(values)]
        }

        return [...new Set([...arr])]
    },

    sum: (arr, attr = null) => {
        if (!isArray(arr)) return arr

        return arr.reduce((acc, value) => {
            if (typeof attr === 'function') value = attr(value)
            else if (attr) value = value[attr]
            return +value + acc
        }, 0)
    },

    where: (arr, key, value) => {
        if (!isArray(arr)) return arr
        return arr.filter(item => (item[key] === value))
    },

    firstWhere: (arr, key, value) => {
        if (!isArray(arr)) return arr
        let index = arr.findIndex(item => (item[key] === value))
        return arr[index]
    },

    /** Adds the value or removes it, in place (a Livewire array stays reactive); returns the array. */
    toggle: (arr, value) => {
        if (!isArray(arr)) return arr
        const index = arr.indexOf(value)
        if (index === -1) arr.push(value)
        else arr.splice(index, 1)
        return arr
    },
}
