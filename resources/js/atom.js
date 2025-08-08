import './prototypes/array'
import './prototypes/number'
import './prototypes/string'

import helpers from './helpers'
import modal from './alpinejs/modal'
import select from './alpinejs/select'
import tooltip from './alpinejs/tooltip'
import dropdown from './alpinejs/dropdown'
import telInput from './alpinejs/tel-input'
import clipboard from './alpinejs/clipboard'
import datePicker from './alpinejs/date-picker'
import dateRange from './alpinejs/date-range'
import timePicker from './alpinejs/time-picker'
import emailInput from './alpinejs/email-input'
import breadcrumbs from './alpinejs/breadcrumbs'
import Autosize from '@marcreichel/alpine-autosize';

document.addEventListener('alpine:init', () => {
    Alpine.data('modal', modal)
    Alpine.data('select', select)
    Alpine.data('tooltip', tooltip)
    Alpine.data('dropdown', dropdown)
    Alpine.data('telInput', telInput)
    Alpine.data('emailInput', emailInput)
    Alpine.data('breadcrumbs', breadcrumbs)
    Alpine.data('datePicker', datePicker)
    Alpine.data('timePicker', timePicker)
    Alpine.data('dateRange', dateRange)
    Alpine.magic('clipboard', clipboard)
    Alpine.plugin(Autosize)
})

window.dd = console.log.bind(console)
window.atom = helpers
window.empty = helpers.empty
