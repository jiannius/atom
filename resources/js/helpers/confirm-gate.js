import confirm from './confirm'

// The `confirm` prop of <atom:button> and <atom:menu.item>: an element carrying `data-atom-confirm` (the dialog's options as JSON) asks before it acts.
//
// One listener on the window, in the capture phase, so it runs before every handler the click would reach: Livewire's `wire:click`, Alpine's `x-on:click`, an
// anchor's navigation, a submit button's form, the dropdown that closes its menu. It stops the click and opens the confirm. On Accept it clicks the element
// again, and that click passes once; Reject does nothing. The element is read when the click happens, never when it was rendered, so a morph that rewrote
// its attributes in between is already in the copy. A morph that replaced the element while the dialog was open left a detached node: nothing is clicked.
//
// A re-click keeps the element's own behaviour as it is: `wire:click` (with its `wire:loading` and `wire:target`), `x-on:click`, an `href` with or without
// `wire:navigate`, `target="_blank"` (the Accept press is still a user gesture), a submit. A keyboard press is a click too, so Enter and Space come through here.
//
// The open menu closes first: the confirm is a modal dialog, and a popover left open would sit under it.
let passing = null

/**
 * Close the popovers the element sits in (a menu, a dropdown), the outermost last.
 */
const closePopovers = (el) => {
    for (let popover = el.closest('[popover]'); popover; popover = popover.parentElement?.closest('[popover]')) {
        if (popover.matches(':popover-open')) popover.hidePopover()
    }
}

/**
 * The delete confirm of a table's checked bar counts what is ticked; the same rule as Atom::deleteConfirmHandler().
 */
const heading = (el, options) => {
    let { plural, ...rest } = options

    if (!plural) return rest

    let bar = el.closest('[data-atom-table-checked]')
    let $wire = bar ? window.Livewire?.find(el.closest('[wire\\:id]')?.getAttribute('wire:id')) : null
    let count = bar ? Number($wire?._table?.select_all ? bar.dataset.total : $wire?._table?.checkboxes?.length) || 0 : 0

    return count > 1 ? { ...rest, heading: plural.replace(':count', count.toLocaleString('en-US')) } : rest
}

export default () => {
    window.addEventListener('click', (e) => {
        let el = e.target instanceof Element ? e.target.closest('[data-atom-confirm]') : null

        if (!el || el === passing) return

        e.preventDefault()
        e.stopImmediatePropagation()

        // A disabled element never asks, and what it guards never runs: a click on a child of a disabled button still bubbles through it
        // (a script's, or a label with no pointer-events rule), and its `wire:click` would hear it
        if (el.matches(':disabled, [aria-disabled="true"]')) return

        let options

        try {
            options = JSON.parse(el.getAttribute('data-atom-confirm'))
        } catch (error) {
            return
        }

        closePopovers(el)

        confirm(heading(el, options)).then((answer) => {
            if (!el.isConnected || el.matches(':disabled, [aria-disabled="true"]')) return

            // For a host that asked for a password or a reason: `x-on:confirmed="..."` on the element itself hears what was typed
            el.dispatchEvent(new CustomEvent('confirmed', { detail: answer }))

            passing = el

            try { el.click() } finally { passing = null }
        }, () => {})
    }, { capture: true })
}
