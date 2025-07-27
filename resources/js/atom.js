import './prototypes/array'
import './prototypes/number'

import helpers from './helpers'
import tel from './alpinejs/tel'
import modal from './alpinejs/modal'
import select from './alpinejs/select'
import tooltip from './alpinejs/tooltip'
import dropdown from './alpinejs/dropdown'
import datePicker from './alpinejs/date-picker'
import dateRange from './alpinejs/date-range'
import timePicker from './alpinejs/time-picker'
import breadcrumbs from './alpinejs/breadcrumbs'
import Autosize from '@marcreichel/alpine-autosize';

document.addEventListener('alpine:init', () => {
    Alpine.data('tel', tel)
    Alpine.data('modal', modal)
    Alpine.data('select', select)
    Alpine.data('tooltip', tooltip)
    Alpine.data('dropdown', dropdown)
    Alpine.data('breadcrumbs', breadcrumbs)
    Alpine.data('datePicker', datePicker)
    Alpine.data('timePicker', timePicker)
    Alpine.data('dateRange', dateRange)
    Alpine.plugin(Autosize)
})

window.dd = console.log.bind(console)
window.atom = helpers
window.empty = helpers.empty
