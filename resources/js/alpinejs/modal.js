export default (config) => {
    return {
        name: config.name,
        escapable: config.escapable,

        showModal (e) {
            if (this.name === e.detail.name) {
                if (this.$root.open) return // showModal() throws on an already-open dialog

                let variant = e.detail.variant
                let position = e.detail.position

                if (variant === 'slide') {
                    if (position === 'left') this.$root.setAttribute('data-atom-modal-slide-left', true)
                    else if (position === 'bottom') this.$root.setAttribute('data-atom-modal-slide-bottom', true)
                    else this.$root.setAttribute('data-atom-modal-slide', true)
                }
                else this.$root.setAttribute('data-atom-modal', true)

                this.$root.showModal();
                this.$root.setAttribute('data-open', '')
                this.$dispatch('opened')
            }
        },

        closeModal (e = null) {
            // Livewire's $dispatch('atom-modal-close') with no payload delivers a
            // CustomEvent whose detail is NULL, not {} — and that no-payload form is
            // the documented way to close the enclosing modal from inside it. Reading
            // e.detail.name directly threw on every modal listening on the page, so a
            // single close fired one TypeError per open modal component.
            const name = e?.detail?.name

            if (
                !e
                || this.name === name
                || (!name && this.$root.contains(e.target))
            ) {
                this.$root.close();
                this.resetModal()
            }
        },

        resetModal () {
            this.$root.removeAttribute('data-open');
            this.$root.removeAttribute('data-atom-modal-slide-left');
            this.$root.removeAttribute('data-atom-modal-slide-bottom');
            this.$root.removeAttribute('data-atom-modal-slide');
            this.$root.removeAttribute('data-atom-modal');
            this.$dispatch('closed', config.name)
        },

        // The browser closed the dialog without closeModal(): an Escape the dialog's own keydown handler never heard (the focus on
        // <body>, a select that stops the key), a <form method="dialog">, a direct close(). `close` is queued, so after closeModal()
        // it finds the state already clean, and after a close and a reopen in one tick it finds the dialog open again.
        syncClosed () {
            if (this.$root.open || !this.$root.hasAttribute('data-open')) return

            this.resetModal()
        },

        // A non-escapable modal also refuses the cancel where closedby="none" does not exist (Safari). Chromium and Firefox let
        // a page prevent only two cancels in a row, which is why this is not the only guard.
        cancelModal (e) {
            if (!this.escapable) e.preventDefault()
        },

        escapeClose () {
            // The keydown only arrives while the focus is inside the dialog; with the focus on <body>, or in a
            // control that stops the key, the browser's own cancel is what Escape does (see cancelModal and
            // syncClosed). Only close when the modal is escapable.
            if (this.escapable) this.closeModal()
        },

        backdropClick (e) {
            if (e.target !== this.$root) return
            if (e.target.tagName !== 'DIALOG') return

            const rect = e.target.getBoundingClientRect()

            const clickedInDialog = (
                rect.top <= e.clientY &&
                e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX &&
                e.clientX <= rect.left + rect.width
            )

            if (!clickedInDialog) this.closeModal(e)
        },
    }
}