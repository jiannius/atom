// Escape closes the topmost layer only: an auto popover (a dropdown, a menu, a select's list, a date picker) in a modal dialog before the dialog.
//
// The browser cannot be left to do it. The dialog's own keydown handler hears a key pressed inside it, and WebKit closes the popover AND the
// dialog on one Escape wherever the focus is. A prevented keydown stops both native closes in every engine, so the popover is closed from here.
// On the window, in the capture phase, because with the focus on <body> the key never passes through the dialog; not stopped, so a handler on
// the way down still runs (a typed date's revert), and the dialog's handler and the shells' read `defaultPrevented` (a press has one owner).
//
// The press a popover took is remembered (`tookEscape()`), and the dialog's handler asks that, not `defaultPrevented`: ProseMirror prevents every
// Escape in an editor (prosemirror-view captureKeyDown), so a prevented key would keep a modal from closing by Escape with the focus in a Tiptap field.
//
// Not a manual popover: the browser's Escape never closes one, and each looks after itself (a tooltip, a toast, the calendar's day list).
// Open auto popovers are a chain of ancestors, so the last in document order is the top one. One holding an open modal dialog is under that dialog.
const taken = new WeakSet()
const OPEN = 'dialog:modal [popover]:not([popover="manual"]):popover-open:not(:has(dialog:modal))'

/**
 * Whether helpers/dialog-escape.js closed a popover on this keydown: the press is spent, the dialog around it stays.
 */
export const tookEscape = (e) => taken.has(e)

export default () => {
    window.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || e.isComposing) return

        // A held key is one press, and the shells skip `e.repeat` for the same reason. By the first repeat the layer that took the press is
        // closed, so the repeat would read as a new press: escapeClose() would close the modal under it, or, with the focus on <body> or a
        // select that stops the key, the browser's own cancel would (Chromium closes a dialog on a repeat natively). Taken and prevented, not
        // stopped, so neither the dialog's handler nor the native close acts on it. Before the `defaultPrevented` check, and that check never
        // guards a press in a dialog: ProseMirror prevents every Escape in an editor.
        if (e.repeat && document.querySelector('dialog:modal')) {
            taken.add(e)
            e.preventDefault()
            return
        }

        if (e.defaultPrevented) return

        const popover = [...document.querySelectorAll(OPEN)].at(-1)

        if (!popover) return

        taken.add(e)
        e.preventDefault()
        popover.hidePopover()
    }, { capture: true })
}
