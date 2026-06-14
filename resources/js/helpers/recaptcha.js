let scriptPromise = null

// Lazy-load Google reCAPTCHA v3 once, resolving when grecaptcha is ready.
const loadGrecaptcha = (siteKey) => {
    if (scriptPromise) return scriptPromise

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey
        script.async = true
        script.onload = () => window.grecaptcha.ready(() => resolve(window.grecaptcha))
        script.onerror = () => {
            scriptPromise = null
            reject(new Error('reCAPTCHA script failed to load'))
        }
        document.head.appendChild(script)
    })

    return scriptPromise
}

// Run reCAPTCHA before a form submit: mint a token, hand it to the Livewire
// component (so it rides with the submit), then run the submit callback.
//
// The form element carries .is-loading from token-mint through the submit
// round-trip so the submit button keeps spinning. data-recaptcha-pending is a
// troubleshooting hook (devtools/console), not a user-facing indicator.
//
// Fails open on the client: no site key, or a mint failure, still submits —
// the backend verifier decides (and fails open on its own infra errors).
export default async ({ el, wire, action = 'submit', submit }) => {
    const siteKey = document.querySelector('meta[name="recaptcha-sitekey"]')?.content

    if (!siteKey) return submit()

    el?.classList.add('is-loading')
    el?.setAttribute('data-recaptcha-pending', '')
    console.debug('[recaptcha] verifying', action)

    try {
        const grecaptcha = await loadGrecaptcha(siteKey)
        const token = await grecaptcha.execute(siteKey, { action })
        wire?.set('_recaptcha', token, false)
    }
    catch (e) {
        console.warn('[recaptcha] token mint failed, submitting without token', e)
    }

    try {
        await submit()
    }
    finally {
        el?.classList.remove('is-loading')
        el?.removeAttribute('data-recaptcha-pending')
    }
}
