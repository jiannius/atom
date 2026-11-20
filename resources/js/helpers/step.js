/**
 * The index one step from `current` in a list of `length`, wrapping at the ends. With nothing active (`current` below 0):
 * Down (`dir` > 0) lands on the first, Up on the last.
 */
export default (current, dir, length) => current < 0
    ? (dir > 0 ? 0 : length - 1)
    : (current + dir + length) % length
