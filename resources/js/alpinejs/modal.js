import dialog from '../helpers/dialog'

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

        // A non-escapable modal also refuses the cancel where closedby="none" does not exist (Safari). Chromium and Firefox let
        // a page prevent only two cancels in a row, which is why this is not the only guard.
        cancelModal (e) {
            if (!this.escapable) e.preventDefault()
        },

        ...dialog({
            shown: (c) => c.$root.hasAttribute('data-open'),
            close: (c, e) => c.closeModal(e),
            reset: (c) => c.resetModal(),
            escapable: (c) => c.escapable,
            requireOpen: true,
            insideRect: true,
        }),
    }
}
