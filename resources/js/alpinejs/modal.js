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
            if (
                !e
                || this.name === e.detail.name
                || (!e.detail.name && this.$root.contains(e.target))
            ) {
                this.$root.close();
                this.$root.removeAttribute('data-open');
                this.$root.removeAttribute('data-atom-modal-slide-left');
                this.$root.removeAttribute('data-atom-modal-slide-bottom');
                this.$root.removeAttribute('data-atom-modal-slide');
                this.$root.removeAttribute('data-atom-modal');
                this.$dispatch('closed', config.name)
            }
        },

        escapeClose () {
            // keydown.escape is always prevented in the template so the native
            // cancel/close never bypasses our attribute cleanup; only close
            // when the modal is escapable.
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