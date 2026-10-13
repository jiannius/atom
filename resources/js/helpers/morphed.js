// Runs a callback after Livewire has morphed the DOM. A morph strips every attribute the server did
// not render, so state a component writes with JS (aria-expanded, aria-describedby, a filtered list)
// is gone once it lands, and the component has to write it again. The callback gets the element that
// was morphed (a component root, or the container of a re-rendered island) and returns nothing.
// Returns a function that stops listening, for the component's destroy().
const callbacks = new Set()
let hooked = false

const hook = () => {
    if (hooked || typeof window.Livewire?.hook !== 'function') return

    hooked = true

    const run = (el) => callbacks.forEach((callback) => {
        try {
            callback(el)
        }
        catch (e) {
            console.warn('[atom] morph callback failed', e)
        }
    })

    window.Livewire.hook('morphed', ({ el }) => run(el))
    window.Livewire.hook('island.morphed', ({ startNode }) => run(startNode?.parentElement))
}

// atom.js can run before or after Livewire boots
document.addEventListener('livewire:init', hook, { once: true })

export default (callback) => {
    hook()
    callbacks.add(callback)

    return () => callbacks.delete(callback)
}
