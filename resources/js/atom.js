import helpers from './helpers'
import modal from './alpinejs/modal'
import tooltip from './alpinejs/tooltip'
import dropdown from './alpinejs/dropdown'
import breadcrumbs from './alpinejs/breadcrumbs'

document.addEventListener('alpine:init', () => {
    Alpine.data('modal', modal)
    Alpine.data('tooltip', tooltip)
    Alpine.data('dropdown', dropdown)
    Alpine.data('breadcrumbs', breadcrumbs)
})

window.dd = console.log.bind(console)
window.atom = helpers
window.empty = helpers.empty
