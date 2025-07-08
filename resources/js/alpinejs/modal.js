export default (config) => {
    return {
        name: config.name,
        scope: config.scope,
        dismissible: config.dismissible,
        closeable: config.closeable,

        showModal (e) {
            if (this.name === e.detail.name && (this.scope === e.detail.scope || !e.detail.scope)) {
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
                this.$dispatch('closed')
            }
        },

        backdropClick (e) {
            if (!this.dismissible) return
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