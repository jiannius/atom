import './prototypes/array'
import './prototypes/number'
import './prototypes/string'

import helpers from './helpers'
import accordion from './alpinejs/accordion'
import command from './alpinejs/command'
import modal from './alpinejs/modal'
import tiptap from './alpinejs/tiptap'
import mention from './alpinejs/mention'
import select from './alpinejs/select'
import tooltip from './alpinejs/tooltip'
import dropdown from './alpinejs/dropdown'
import lightbox from './alpinejs/lightbox'
import telInput from './alpinejs/tel-input'
import clipboard from './alpinejs/clipboard'
import datePicker from './alpinejs/date-picker'
import dateRange from './alpinejs/date-range'
import timePicker from './alpinejs/time-picker'
import emailInput from './alpinejs/email-input'
import otp from './alpinejs/otp'
import slider from './alpinejs/slider'
import breadcrumbs from './alpinejs/breadcrumbs'
import calendar from './alpinejs/calendar'
import chartBar from './alpinejs/chart/bar'
import chartArea from './alpinejs/chart/area'
import chartTrend from './alpinejs/chart/trend'
import Autosize from '@marcreichel/alpine-autosize'
import intersect from '@alpinejs/intersect'

document.addEventListener('alpine:init', () => {
    Alpine.data('accordion', accordion)
    Alpine.data('command', command)
    Alpine.data('modal', modal)
    Alpine.data('tiptap', tiptap)
    Alpine.data('mention', mention)
    Alpine.data('select', select)
    Alpine.data('tooltip', tooltip)
    Alpine.data('dropdown', dropdown)
    Alpine.data('lightbox', lightbox)
    Alpine.data('telInput', telInput)
    Alpine.data('emailInput', emailInput)
    Alpine.data('otp', otp)
    Alpine.data('slider', slider)
    Alpine.data('breadcrumbs', breadcrumbs)
    Alpine.data('datePicker', datePicker)
    Alpine.data('timePicker', timePicker)
    Alpine.data('dateRange', dateRange)
    Alpine.data('calendar', calendar)
    Alpine.data('chartBar', chartBar)
    Alpine.data('chartArea', chartArea)
    Alpine.data('chartTrend', chartTrend)
    Alpine.magic('clipboard', clipboard)
    Alpine.plugin(Autosize)
    Alpine.plugin(intersect)
})

window.dd = console.log.bind(console)
window.atom = helpers
window.empty = helpers.empty
