import helpers from './helpers'
import modal from './alpinejs/modal'
import dropdown from './alpinejs/dropdown'

document.addEventListener('alpine:init', () => {
    Alpine.data('modal', modal)
    Alpine.data('dropdown', dropdown)
})

window.dd = console.log.bind(console)
window.atom = helpers
