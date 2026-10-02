// Sticky error toast for <atom:form>.
//
// A failed submit leaves its errors on the fields, and a long form can have every one of
// them off screen, so the submit looks like it did nothing. When a Livewire action named
// by a form's wire:target finishes with errors on the component, show them in a danger
// toast that stays until it is dismissed (delay: 0 = no timer; the toast's own X closes
// it).
//
// Timing, pinned by tests/e2e/form-error-toast.spec.js: Livewire runs an action's
// onFinish after the response's snapshot is merged and morphed, so $wire.$errors holds
// THIS response's errors, and any toast the server dispatched has already fired. onFinish
// also runs on a network failure or a cancel, when the snapshot (and its errors) is the
// previous response's, so the hook acts only once onSuccess has run.

const SOURCE = 'atom-form-error'

let registered = false

/**
 * The wire:target of a form as a list of action names (`save`, `save(1)`, `a, b`).
 */
const targets = (form) => (form.getAttribute('wire:target') || '')
    .split(',')
    .map((target) => target.split('(')[0].trim())
    .filter(Boolean)

/**
 * The opted-in form that this action submitted: it belongs to the action's component
 * (not a child's) and names the action in its wire:target.
 */
const submittedForm = (action) => {
    const root = action.component.el

    const forms = [...root.querySelectorAll('form[data-atom-form][data-atom-error-toast]')]
        .filter((form) => form.closest('[wire\\:id]') === root)
        .filter((form) => targets(form).includes(action.name))

    // two forms can name the same method; the one the action started from wins
    const origin = action.origin?.el
    return forms.find((form) => origin && form.contains(origin)) || forms[0] || null
}

/**
 * The component's current error messages, each sentence once.
 */
const messagesOf = (component) => [...new Set(component.$wire.$errors.all().map(String).filter(Boolean))]

const onFinished = (action) => {
    const form = submittedForm(action)

    if (!form) return

    const messages = messagesOf(action.component)

    if (messages.length) {
        window.atom.toast({
            variant: 'danger',
            heading: form.getAttribute('data-atom-error-heading') || '',
            message: messages,
            delay: 0,
            source: SOURCE,
        })
    }
}

const hook = ({ action, onSuccess, onFinish }) => {
    let succeeded = false

    onSuccess(() => succeeded = true)

    onFinish(() => {
        if (!succeeded) return

        try {
            onFinished(action)
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

    // atom.js can run before or after Livewire boots
    register()
    document.addEventListener('livewire:init', register, { once: true })
}
