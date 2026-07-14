export default (config = {}) => ({
    exclusive: config.exclusive ?? false,
    active: [],

    open(id) {
        if (this.active.includes(id)) return
        this.active = this.exclusive ? [id] : [...this.active, id]
    },

    toggle(id) {
        this.isOpen(id)
            ? (this.active = this.active.filter(i => i !== id))
            : this.open(id)
    },

    isOpen(id) {
        return this.active.includes(id)
    },
})
