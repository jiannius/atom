// What atom does on a Livewire morph, kept in one place (five things).
//
// 1. A morph that lands while an atom popover (a menu, a tooltip) is open must not touch the node's own
//    attributes: floating-ui wrote an inline style on it, which the morph would strip, and the panel
//    jumps to the page corner. So an open one is morphed children-only. A closed one is patched in full,
//    so a server-driven attribute or class on it still updates (a static `wire:ignore.self` would freeze
//    it for good), and floating-ui writes its style again on the next open.
// 2. onMorphed() runs a callback after the morph. A morph strips every attribute the server did not
//    render, so state a component writes with JS (aria-expanded, aria-describedby, a filtered list) is
//    gone once it lands, and the component has to write it again. The callback gets the element that was
//    morphed (a component root, or the container of a re-rendered island) and returns nothing. It
//    returns a function that stops listening, for the component's destroy().
// 3. A control behind `wire:ignore` takes no attribute from the server, and what says whether it is
//    disabled is one: a date range, a tiptap editor (its `readonly` follows a morph too), and an autosize textarea
//    (the autosize plugin sets `wire:ignore` on a `wire:model` textarea itself). Livewire fires `morph.updating` with the new markup (`toEl`) before it
//    checks the ignore, so the state is carried by hand here, and the control's own factory reads it again
//    from its `morphed` callback. Only `data-atom-disabled`, `disabled` and `readonly` are carried, and they
//    are server-owned on these roots: a host `x-bind:disabled` or `x-bind:readonly` on an autoresize
//    textarea is overridden by the server's state at the next morph. Never `class`: a class a host or JS
//    added must survive, so the disabled look of such a control comes from the marker
//    (`[[data-atom-disabled]_&]:` utilities for what sits inside the root, `[&[data-atom-disabled]]:` for the root itself), not from a carried class. Add a control to `ignoredControls`
//    only when its server markup holds nothing else that changes between renders.
// 4. A morph that disables a focused native select blurs it first, so WebKit closes its open list (see the hook).
// 5. A morph takes off the ids the command palette wrote on its items, so Livewire patches them instead of replacing them (see the hook).
const ignoredControls = '[data-atom-date-range], [data-atom-tiptap], [data-atom-textarea]'
const carried = ['data-atom-disabled', 'disabled', 'readonly']

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

    window.Livewire.hook('morph.updating', ({ el, toEl, childrenOnly }) => {
        if (toEl && el.matches(`[wire\\:ignore]:is(${ignoredControls})`)) {
            for (const name of carried) {
                if (toEl.hasAttribute(name)) el.setAttribute(name, toEl.getAttribute(name))
                else el.removeAttribute(name)
            }
        }

        // WebKit hides a native <select> list only from the blur's default action, which it skips on a disabled select: a morph that
        // disables a focused (so possibly open) select would leave its list drawn, and a pick from it still changes the value. Blur it
        // first, while it is still enabled, which closes the list; the select loses its focus to the disable anyway. Livewire fires this hook
        // before it checks the ignore, so a select it will not morph (`wire:ignore`, or `wire:ignore.self`, which freezes its own attributes) is
        // left alone: it would be blurred and still enabled.
        if (toEl && el.tagName === 'SELECT' && toEl.hasAttribute('disabled') && !el.disabled && el === document.activeElement
            && el.__livewire_ignore !== true && el.__livewire_ignore_self !== true) {
            el.blur()
        }

        // The palette writes an id on the item it highlights, and the server renders none. Livewire keys a morph by id, so the
        // item would be replaced, not patched, and its focus lost. Take off only the ids the palette wrote (`_atomCommandId`, set by
        // command.js): a host's own id or wire:key stays. The onMorphed() callback of the palette writes it again.
        if (el.matches('[data-atom-command]')) {
            el.querySelectorAll('[data-atom-command-item]').forEach((item) => {
                if (item._atomCommandId && item._atomCommandId === item.id) item.removeAttribute('id')
            })
        }

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
