/**
 * `n` as a string of at least `length` digits, zero-padded on the left (`pad(5)` is `05`).
 */
export const pad = (n, length = 2) => String(n).padStart(length, '0')

/**
 * `value` held between `low` and `high`.
 */
export const clamp = (value, low, high) => Math.min(Math.max(value, low), high)
