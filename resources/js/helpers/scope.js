// The listeners a component binds in init(), tied to its root so a re-run init() (Alpine can re-enter on a node it already set up, and a
// morph can re-evaluate x-data into a new data object) drops the previous run's listeners instead of stacking a second set.

/**
 * A fresh AbortSignal for the listeners of `el`, kept on `el[key]` (the element, not the data object, which a re-run replaces). The
 * previous run's signal is aborted first. Pass it as `{ signal }` to addEventListener().
 */
export const listen = (el, key) => {
    el[key]?.abort()

    const controller = new AbortController()

    el[key] = controller

    return controller.signal
}

/**
 * Drop every listener bound with the signal `listen(el, key)` made (for a component's destroy()).
 */
export const unlisten = (el, key) => {
    el?.[key]?.abort()

    if (el) el[key] = null
}

/**
 * Run `callback` when a wire:navigate page swap starts, until `signal` aborts. A top-layer popover (a menu, a tooltip, a day list) must
 * not outlive the swap, and the page is cached for Back as it is when it is left.
 */
export const closeOnNavigate = (callback, signal) => {
    document.addEventListener('livewire:navigating', callback, { signal })
}
