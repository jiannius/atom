// Two things about a Livewire morph, kept in one place.
//
// 1. A morph that lands while an atom popover (a menu, a tooltip) is open must not touch the node's own
//    attributes: floating-ui wrote an inline style on it, which the morph would strip, and the panel
//    jumps to the page corner. So an open one is morphed children-only. A closed one is patched in full,
//    so a server-driven attribute or class on it still updates (a static `wire:ignore.self` would freeze
//    it for good), and floating-ui writes its style again on the next open.
// 2. onMorphed() runs a callback after the morph. A morph strips every attribute the server did
// not render, so state a component writes with JS (aria-expanded, aria-describedby, a filtered list)
// is gone once it lands, and the component has to write it again. The callback gets the element that
// was morphed (a component root, or the container of a re-rendered island) and returns nothing.
// Returns a function that stops listening, for the component's destroy().
const callbacks = new Set()
let hooked = false

const hook = () => {
    // no window when a factory that imports this is loaded by a node test
    if (hooked || typeof window === 'undefined' || typeof window.Livewire?.hook !== 'function') return

    hooked = true

    const run = (el) => callbacks.forEach((callback) => {
        try {
            callback(el)
        }
        catch (e) {
            console.warn('[atom] morph callback failed', e)
        }
    })

    window.Livewire.hook('morph.updating', ({ el, childrenOnly }) => {
        if (el.matches('[data-atom-menu][popover], [data-atom-tooltip-content]') && el.matches(':popover-open')) {
            childrenOnly()
        }
    })
    window.Livewire.hook('morphed', ({ el }) => run(el))
    window.Livewire.hook('island.morphed', ({ startNode }) => run(startNode?.parentElement))
}

// atom.js can run before or after Livewire boots
hook()
if (typeof document !== 'undefined') document.addEventListener('livewire:init', hook, { once: true })

export default (callback) => {
    hook()
    callbacks.add(callback)

    return () => callbacks.delete(callback)
}
