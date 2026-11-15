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
// Three presses reach a link without a plain click. `wire:navigate` navigates on mousedown and mouseup and on a keydown of Enter, never waiting for the click,
// so those two events are stopped on a gated `wire:navigate` link (the click that follows asks; the re-click is a script's, which Livewire navigates on at
// once). A middle click (auxclick), and a click with Ctrl, Meta or Shift, open a new tab natively: they are stopped and asked about like a click, and Accept
// opens the link in a new tab. The browser's own context menu ("Open link in new tab") is not a click and cannot be gated.
//
// What the markup says is not trusted: only the dialog options atom writes are read (never `html`, which the dialog prints as markup).
const OPTIONS = ['variant', 'heading', 'subheading', 'message', 'buttonConfirm', 'buttonCancel', 'passphrase', 'password', 'reason', 'reasonLabel', 'reasonPlaceholder', 'plural']

let passing = null

/**
 * The gated element a press landed on, or null.
 */
const gated = (e) => (e.target instanceof Element ? e.target.closest('[data-atom-confirm]') : null)

/**
 * Whether the element is a link Livewire navigates (`wire:navigate`, `wire:navigate.hover`).
 */
const navigates = (el) => el.tagName === 'A' && [...el.attributes].some((a) => a.name === 'wire:navigate' || a.name.startsWith('wire:navigate.'))

/**
 * Close the popovers the element sits in (a menu, a dropdown), the outermost last.
 */
const closePopovers = (el) => {
    for (let popover = el.closest('[popover]'); popover; popover = popover.parentElement?.closest('[popover]')) {
        if (popover.matches(':popover-open')) popover.hidePopover()
    }
}

/**
 * The trigger of the dropdown whose menu the element sits in (the outermost one, when menus nest: the inner triggers go with the outer popover), or null.
 * Read before the menu closes, from the dropdown's own `trigger`, so it is the control that dropdown treats as its trigger.
 */
const menuTriggerOf = (el) => {
    let trigger = null

    for (let root = el.closest('[data-atom-dropdown]'); root; root = root.parentElement?.closest('[data-atom-dropdown]')) {
        trigger = window.Alpine?.$data(root)?.trigger ?? trigger
    }

    return el.closest('[popover]') ? trigger : null
}

/**
 * Put the focus back on the trigger once the dialog is gone, when nothing meaningful has it. The browser returns it to the element the click
 * was on, which sat in a menu that is closed now (and Safari never focused it: a press on a button does not focus it there), so it ends on <body>.
 * Cancel and Escape only: an Accept runs something that may navigate or re-render, and its own result decides where the focus goes.
 */
const returnFocus = (trigger, accepted) => {
    let onClosed = (e) => {
        if (e.detail !== 'atom-confirm') return

        window.removeEventListener('closed', onClosed)

        // The next task: the dialog's own `close` handling is done. WebKit has not yet moved the focus off the closed dialog (or the button in it),
        // so that counts as nothing: it is the dialog's leftover, and it is the browser's move away from it that must not land on <body>.
        setTimeout(() => {
            let active = document.activeElement
            let meaningful = active && active !== document.body && active !== document.documentElement && active.isConnected && !active.closest('dialog:not([open])')

            if (accepted() || !trigger.isConnected || meaningful) return

            trigger.focus()
        })
    }

    window.addEventListener('closed', onClosed)
}

/**
 * The dialog options of the element: only the keys atom writes, and a delete in a table's checked bar counts what is ticked (the same rule as
 * Atom::deleteConfirmHandler()).
 */
const optionsOf = (el) => {
    let parsed

    try {
        parsed = JSON.parse(el.getAttribute('data-atom-confirm'))
    } catch (error) {
        return null
    }

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null

    let { plural, ...options } = Object.fromEntries(OPTIONS.filter((key) => Object.hasOwn(parsed, key)).map((key) => [key, parsed[key]]))

    if (typeof plural !== 'string') return options

    let bar = el.closest('[data-atom-table-checked]')
    let $wire = bar ? window.Livewire?.find(el.closest('[wire\\:id]')?.getAttribute('wire:id')) : null
    let count = bar ? Number($wire?._table?.select_all ? bar.dataset.total : $wire?._table?.checkboxes?.length) || 0 : 0

    return count > 1 ? { ...options, heading: plural.replace(':count', count.toLocaleString('en-US')) } : options
}

/**
 * Whether the press asks for a new tab: a middle click, or Ctrl, Meta or Shift on a link.
 */
const wantsNewTab = (e, el) => el.tagName === 'A' && !!el.getAttribute('href') && (e.button === 1 || e.ctrlKey || e.metaKey || e.shiftKey)

/**
 * Stop the press, close the menu it came from and ask. Accept dispatches `confirmed` on the element, then runs what the press was.
 */
const ask = (e, el, newTab) => {
    e.preventDefault()
    e.stopImmediatePropagation()

    // A disabled element never asks, and what it guards never runs: a click on a child of a disabled button still bubbles through it
    // (a script's, or a label with no pointer-events rule), and its `wire:click` would hear it
    if (el.matches(':disabled, [aria-disabled="true"]')) return

    let options = optionsOf(el)

    if (!options) return

    let trigger = menuTriggerOf(el)
    let accepted = false

    closePopovers(el)

    if (trigger) returnFocus(trigger, () => accepted)

    confirm(options).then((answer) => {
        accepted = true

        if (!el.isConnected || el.matches(':disabled, [aria-disabled="true"]')) return

        // For a host that asked for a password or a reason: `x-on:confirmed="..."` on the element itself hears what was typed
        el.dispatchEvent(new CustomEvent('confirmed', { detail: answer }))

        if (newTab) {
            window.open(el.href, '_blank', 'noopener')

            return
        }

        passing = el

        try { el.click() } finally { passing = null }
    }, () => {})
}

export default () => {
    window.addEventListener('click', (e) => {
        let el = gated(e)

        if (!el || el === passing) return

        ask(e, el, wantsNewTab(e, el))
    }, { capture: true })

    window.addEventListener('auxclick', (e) => {
        let el = gated(e)

        if (!el || el === passing || e.button !== 1 || !wantsNewTab(e, el)) return

        ask(e, el, true)
    }, { capture: true })

    // The presses `wire:navigate` acts on before a click: the click that follows (not prevented here) is the one that asks
    for (let type of ['mousedown', 'keydown']) {
        window.addEventListener(type, (e) => {
            let el = gated(e)

            if (!el || el === passing || !navigates(el)) return
            if (type === 'keydown' && e.key !== 'Enter') return

            e.stopImmediatePropagation()
        }, { capture: true })
    }
}
