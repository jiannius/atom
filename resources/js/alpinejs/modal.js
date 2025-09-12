export default (config) => {
    return {
        name: config.name,
        scope: config.scope,
        dismissible: config.dismissible,
        closeable: config.closeable,

        showModal (e) {
            if (this.name === e.detail.name && (this.scope === e.detail.scope || !e.detail.scope)) {
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

        closeModal (e) {
            if (
                (this.name === e.detail.name && (this.scope === e.detail.scope || !e.detail.scope))
                || (!e.detail.name && this.$root.contains(e.target))
            ) {
                this.$root.close();
                this.$root.removeAttribute('data-open');
                this.$root.removeAttribute('data-atom-modal-slide-left');
                this.$root.removeAttribute('data-atom-modal-slide-bottom');
                this.$root.removeAttribute('data-atom-modal-slide');
                this.$root.removeAttribute('data-atom-modal');
                this.$dispatch('closed')
            }
        },

        backdropClick (e) {
            if (!this.dismissible) return
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