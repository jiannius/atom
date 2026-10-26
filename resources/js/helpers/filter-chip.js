// A filter control that carries `add` (a select filter, a date range) draws its own chip once it has a value, so the chip is
// the editor: the add pill and the chip are one trigger, and the menu it opens is the same one. It does so only in an
// `<atom:table.filters chips="inline">` bar, and only when it sits in the bar itself: a control in the "More filters" slot
// has no place in the bar for a chip, so there the container draws the chip, as it always did. Both are read from the
// markup, once, when the control starts.

/**
 * Whether the control draws its own chip.
 */
export const drawsOwnChip = (root) => !!root.closest('[data-atom-table-filters][data-chips="inline"]') && !inMoreSlot(root)

// The "More filters" slot (popover, card or modal) is marked `data-atom-table-filters-more`; the button counts the chips whose
// control sits in it (components/table/filters.blade.php reads the event's target).
const inMoreSlot = (root) => !!root.closest('[data-atom-table-filters-more]')
