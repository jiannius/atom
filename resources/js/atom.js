import './prototypes/number'

import helpers from './helpers'
import modal from './alpinejs/modal'
import select from './alpinejs/select'
import tooltip from './alpinejs/tooltip'
import dropdown from './alpinejs/dropdown'
import breadcrumbs from './alpinejs/breadcrumbs'
import Autosize from '@marcreichel/alpine-autosize';

document.addEventListener('alpine:init', () => {
    Alpine.data('modal', modal)
    Alpine.data('select', select)
    Alpine.data('tooltip', tooltip)
    Alpine.data('dropdown', dropdown)
    Alpine.data('breadcrumbs', breadcrumbs)
    Alpine.plugin(Autosize)
})

window.dd = console.log.bind(console)
window.atom = helpers
window.empty = helpers.empty
