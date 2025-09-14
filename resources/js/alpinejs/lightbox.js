export default () => {
    return {
        gallery: [],
        pointer: null,

        open (el) {
            this.gallery = Array.from(el.closest('[data-lightbox]').querySelectorAll('[data-lightbox-url]')).map(item => this.getSlideResource(item))
            this.pointer = this.gallery.findIndex(item => item.url === el.getAttribute('data-lightbox-url'))

            this.$nextTick(() => {
                this.$root.showModal();
                this.$root.setAttribute('data-open', '')
            })
        },

        close () {
            this.$root.close();
            this.$root.removeAttribute('data-open');
        },

        getSlideResource (el) {
            let id = el.getAttribute('data-lightbox-id')
            let url = el.getAttribute('data-lightbox-url')
            let name = el.getAttribute('data-lightbox-name')

            return { id, url, name, isImage: /\.(jpe?g|png|gif|webp|bmp|svg|tiff?)$/i.test(url.split('?')[0]) }
        },

        prev () {
            let prev = ((this.pointer + this.gallery.length) - 1) % this.gallery.length
            this.pointer = null
            setTimeout(() => this.pointer = prev, 150)
        },

        next () {
            let next = (this.pointer + 1) % this.gallery.length
            this.pointer = null
            setTimeout(() => this.pointer = next, 150)
        },
    }
}
