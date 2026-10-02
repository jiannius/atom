// Error toast for <atom:form>.
//
// A failed submit leaves its errors on the fields, and a long form can have every one of
// them off screen, so the submit looks like it did nothing. When a Livewire action named
// by a form's wire:target finishes and THAT FORM has errors, show them in a danger toast.
// Outside a modal it is sticky (delay: 0 = no timer; the toast's own X closes it). Inside
// a <dialog> it is timed instead: a modal dialog makes everything outside it inert, so the
// toast's X can't be clicked, Escape doesn't reach a popover=manual, and nothing would ever
// close it. It is also closed when that dialog closes, however it closes (Escape, a close
// button, an atom-modal-close dispatch: all end in dialog.close(), which fires `close`).
//
// The next submit of the same form that comes back clean closes it again, but only if it is
// still the toast on screen: the check is the toast's own `source` match, so an app's
// "Saved" dispatched by that same response is left alone (it fires before onFinish). The
// form is captured when the action STARTS: a successful save can remove the form from the
// page (an @if swap), and by onFinish there is nothing left to look up.
//
// Which errors are the form's: Livewire's $errors is per component, so a submit of a form
// that never validates would otherwise re-toast another form's stale errors. A form owns an
// error key when it renders a field (wire:model / name) for that key or a parent or child of
// it (`items` / `items.0.name`). The toast lists only those. An error with no field in the
// form (a failed API call, addError('general')) is the host's to report, e.g. with
// $this->toast(): atom can't know what a host's submit action does.
//
// Timing, pinned by tests/e2e/form-error-toast.spec.js: Livewire runs an action's
// onFinish after the response's snapshot is merged and morphed, so $wire.$errors holds
// THIS response's errors, and any toast the server dispatched has already fired. onFinish
// also runs on a network failure or a cancel, when the snapshot (and its errors) is the
// previous response's, so the hook acts only once onSuccess has run.

const SOURCE = 'atom-form-error'
const MODAL_DELAY = 6000
const MAX_MESSAGES = 5

// The form that opened the toast now showing, so a successful submit of another form
// leaves it alone, and the listener that closes the toast with its dialog.
let owner = null
let unwatchDialog = null
let registered = false
let lastForm = null

/**
 * Split a wire:target on the commas that separate targets, not those inside an argument
 * list or a quoted string (`save(1, 2), other`).
 */
const splitTargets = (value) => {
    const parts = []
    let current = ''
    let depth = 0
    let quote = null

    for (const char of value) {
        if (quote) {
            if (char === quote) quote = null
        }
        else if (char === '"' || char === "'") quote = char
        else if (char === '(') depth++
        else if (char === ')') depth = Math.max(0, depth - 1)
        else if (char === ',' && depth === 0) {
            parts.push(current)
            current = ''
            continue
        }

        current += char
    }

    parts.push(current)

    return parts
}

/**
 * The wire:target of a form as a list of action names (`save`, `save(1)`, `a, b`).
 */
export const targets = (form) => splitTargets(form.getAttribute('wire:target') || '')
    .map((target) => target.split('(')[0].trim())
    .filter(Boolean)

/**
 * The opted-in form that this action submitted, or null when that can't be told.
 *
 * It belongs to the action's component (not a child's) and names the action in its
 * wire:target. Two forms can name the same method: the one the action started from wins.
 * A call with no origin (`$wire.save()`, as the reCAPTCHA path makes) picks the only
 * candidate, else the candidate that last had focus or was submitted, else nothing: guessing
 * the first form would toast, and later close, the wrong form's errors.
 */
const submittedForm = (action) => {
    const root = action.component.el

    const forms = [...root.querySelectorAll('form[data-atom-form][data-atom-error-toast]')]
        .filter((form) => form.closest('[wire\\:id]') === root)
        .filter((form) => targets(form).includes(action.name))

    const origin = action.origin?.el

    if (origin) return forms.find((form) => form.contains(origin)) || null
    if (forms.length === 1) return forms[0]

    return forms.find((form) => form === lastForm) || null
}

/**
 * The error keys a form renders a field for, from wire:model (any modifier) and name.
 */
const fieldNames = (form) => {
    const names = new Set()

    form.querySelectorAll('*').forEach((el) => {
        for (const attribute of el.attributes) {
            if (attribute.name.startsWith('wire:model') && attribute.value.trim()) names.add(attribute.value.trim())
        }

        const name = el.getAttribute('name')

        if (name) names.add(name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, ''))
    })

    return [...names]
}

/**
 * Whether an error key belongs to one of these field names: the same key, or a parent or
 * child of it (`items` / `items.0.name`).
 */
const matches = (names, key) => names.some((name) => name === key || name.startsWith(key + '.') || key.startsWith(name + '.'))

/**
 * The error keys of this form: those that one of its fields renders.
 */
export const errorKeys = (form, component) => {
    if (!form.isConnected) return []

    const names = fieldNames(form)

    return component.$wire.$errors.keys().filter((key) => matches(names, key))
}

/**
 * Each sentence of these error keys once.
 */
const messagesOf = (keys, component) => {
    const errors = component.$wire.$errors

    const messages = keys
        .flatMap((key) => [errors.get(key)].flat(2))
        .map(String)
        .filter(Boolean)

    return [...new Set(messages)]
}

/**
 * Close the error toast, and stop watching the dialog that was going to close it.
 */
const closeToast = () => {
    owner = null
    unwatchDialog?.()
    unwatchDialog = null

    dispatchEvent(new CustomEvent('atom-toast-close', { detail: { source: SOURCE } }))
}

const showToast = (form, messages, keys) => {
    const extra = messages.length - MAX_MESSAGES
    const dialog = form.closest('dialog')

    if (extra > 0) {
        const more = form.getAttribute('data-atom-error-more') || 'and :count more'
        messages = [...messages.slice(0, MAX_MESSAGES), more.replace(':count', extra)]
    }

    unwatchDialog?.()
    unwatchDialog = null
    owner = form

    if (dialog) {
        const onClose = () => {
            if (owner === form) closeToast()
        }

        dialog.addEventListener('close', onClose, { once: true })
        unwatchDialog = () => dialog.removeEventListener('close', onClose)
    }

    window.atom.toast({
        variant: 'danger',
        heading: form.getAttribute('data-atom-error-heading') || '',
        message: messages,
        delay: dialog ? MODAL_DELAY : 0,
        // the error keys behind the lines (the list is cut at five), for tests
        meta: { keys },
        source: SOURCE,
    })
}

const onFinished = (action, form) => {
    const keys = errorKeys(form, action.component)
    const messages = messagesOf(keys, action.component)

    if (messages.length) showToast(form, messages, keys)
    else if (owner === form) closeToast()
}

const hook = ({ action, onSuccess, onFinish }) => {
    let form = null

    try {
        form = submittedForm(action)
    }
    catch (e) {
        console.warn('[atom] form error toast failed', e)
    }

    if (!form) return

    let succeeded = false

    onSuccess(() => succeeded = true)

    onFinish(() => {
        if (!succeeded) return

        try {
            onFinished(action, form)
        }
        catch (e) {
            console.warn('[atom] form error toast failed', e)
        }
    })
}

export default () => {
    const register = () => {
        if (registered || typeof window.Livewire?.interceptAction !== 'function') return

        registered = true
        window.Livewire.interceptAction(hook)
    }

    // the form a call with no origin most likely came from
    const remember = (e) => lastForm = e.target?.closest?.('form[data-atom-form]') || lastForm

    document.addEventListener('focusin', remember, true)
    document.addEventListener('submit', remember, true)

    // atom.js can run before or after Livewire boots
    register()
    document.addEventListener('livewire:init', register, { once: true })
}
