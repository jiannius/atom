/**
 * Check if value is empty
 */
export default (value) => {
    if (value === undefined || value === null) return true

    value = JSON.parse(JSON.stringify(value))

    return (Array.isArray(value) && !value.length)
        || (typeof value === 'object' && !Object.keys(value).length && Object.getPrototypeOf(value) === Object.prototype)
        || (typeof value === 'string' && value.trim() === '')
}