import Pikaday from 'pikaday'
import dayjs from 'dayjs'
import { drawsOwnChip } from '../helpers/filter-chip'
import { parseTypedDate, splitTypedRange } from '../helpers/typed-date'
import { zoneOf } from '../helpers/zone'

export default (config) => {
    // With `config.timezone` the pickers read and write the wall clock in that zone (helpers/zone.js); without one, the browser's.
    const zone = zoneOf(config.timezone)

    return {
        pikaday: [],
        endValue: null,
        startValue: null,
        dateRangeValue: null,
        // an `add` range in an inline filter bar draws its own chip (helpers/filter-chip.js); set when it starts
        selfChip: false,

        get chipState () {
            return this.selfChip && !!this.dateRangeValue
        },

        get dateRangeString () {
            let start = config.time ? this.dateObjects[0]?.format('DD MMM YYYY hh:mm A') : this.dateObjects[0]?.format('DD MMM YYYY')
            let end = config.time ? this.dateObjects[1]?.format('DD MMM YYYY hh:mm A') : this.dateObjects[1]?.format('DD MMM YYYY')

            if (start || end) {
                start = start || '∞'
                end = end || '∞'
                return `${start} to ${end}`
            }

            return ''
        },

        get calendarElements () {
            return this.$root.querySelectorAll('[data-atom-date-picker-calendar]')
        },

        get dateObjects () {
            let split = (this.dateRangeValue || '').split('to').map(str => str.trim()).filter(Boolean)

            return [
                split[0] ? zone.read(split[0]) : null,
                split[1] ? zone.read(split[1]) : null,
            ]
        },

        // The ISO strings of the model's two ends, as they were stored (never a round trip through the zone's wall clock).
        get isoValues () {
            let split = (this.dateRangeValue || '').split('to').map(str => str.trim()).filter(Boolean)

            return [
                split[0] ? zone.instant(split[0]) : undefined,
                split[1] ? zone.instant(split[1]) : undefined,
            ]
        },

        init () {
            this.selfChip = !!config.add && drawsOwnChip(this.$root)

            this.parse()

            this.$nextTick(() => {
                this.setCalendar()
                this.startValue = this.isoValues[0]
                this.endValue = this.isoValues[1]

                this.$watch('startValue', () => this.updateValue())
                this.$watch('endValue', () => this.updateValue())
            })
        },

        parse () {
            this.startValue = this.isoValues[0]
            this.endValue = this.isoValues[1]
        },

        // The own chip's x: clear the range and put the focus on the add pill that comes back (it is the same button).
        clearChip () {
            this.dateRangeValue = null
            this.parse()
            this.$nextTick(() => this.$root.querySelector('[data-atom-filter-chip-body]')?.focus())
        },

        preset (name) {
            let now = zone.now()
            let lastMonth = now.startOf('month').subtract(1, 'day')
            let lastYear = now.startOf('year').subtract(1, 'day')

            let presets = {
                'today': [now, now],
                'yesterday': [now.subtract(1, 'day'), now.subtract(1, 'day')],
                'last-7-days': [now.subtract(6, 'day'), now],
                'last-30-days': [now.subtract(29, 'day'), now],
                'last-180-days': [now.subtract(179, 'day'), now],
                'this-month': [now.startOf('month'), now.endOf('month')],
                'last-month': [lastMonth.startOf('month'), lastMonth.endOf('month')],
                'this-year': [now.startOf('year'), now.endOf('year')],
                'last-year': [lastYear.startOf('year'), lastYear.endOf('year')],
            }

            if (presets[name]) {
                this.startValue = zone.start(presets[name][0])
                this.endValue = zone.end(presets[name][1])
                this.$nextTick(() => {
                    this.setCalendarDates()
                    this.setCalendarRange()
                })
            }
        },

        // Commit what was typed into the input (on blur / Enter): `from to to`,
        // `from - to`, or one date for a one-day range. An empty input clears
        // the range; anything else (a half-typed date, one end missing, an end
        // before the start) is put back to the last good value, so it never
        // wipes the model. Returns 'unchanged', 'committed' or 'reverted'.
        commitTyped (input) {
            let text = input.value.trim()
            if (text === this.dateRangeString) return 'unchanged'

            if (!text) {
                this.dateRangeValue = null
                this.parse()
                return 'committed'
            }

            let parts = splitTypedRange(text)
            if (parts.length === 1) parts = [parts[0], parts[0]]

            let [start, end] = parts.length === 2
                ? parts.map(part => parseTypedDate(part, { time: config.time }))
                : [null, null]

            if (!start || !end) {
                this.revertTyped(input)
                return 'reverted'
            }

            // Like a calendar pick: a date-only end covers its whole day.
            let startValue = start.hasTime ? zone.write(start.date) : zone.start(start.date)
            let endValue = end.hasTime ? zone.write(end.date) : zone.end(end.date)

            if (new Date(endValue) < new Date(startValue)) {
                this.revertTyped(input)
                return 'reverted'
            }

            this.startValue = startValue
            this.endValue = endValue

            this.$nextTick(() => {
                // The display re-renders only if the formatted string differs,
                // so a re-spelling of the same range is tidied by hand.
                input.value = this.dateRangeString
                if (this.pikaday[0] && this.pikaday[1]) {
                    this.setCalendarDates()
                    this.setCalendarRange()
                }
            })

            return 'committed'
        },

        revertTyped (input) {
            input.value = this.dateRangeString
        },

        typedEnter (event) {
            // An edited entry is committed (or put back) and the form is not
            // submitted; an untouched one lets Enter submit as it always has.
            if (this.commitTyped(event.target) !== 'unchanged') event.preventDefault()
        },

        updateValue () {
            let start = this.startValue || ''
            let end = this.endValue || ''
            if (!start || !end) return

            this.dateRangeValue = `${start} to ${end}`
            this.setCalendarRange()
        },

        destroy () {
            this.destroyCalendar()
        },

        destroyCalendar () {
            this.pikaday.forEach(p => p?.destroy())
            this.pikaday = []
            this.calendarElements.forEach(el => el.innerHTML = '')
        },

        setCalendar () {
            this.destroyCalendar()

            // Pikaday hands back a local date whose year, month and day are the ones clicked (the zone's, since it was given the wall clock).
            this.pikaday[0] = new Pikaday({ keyboardInput: false, onSelect: (value) => {
                this.startValue = zone.start(dayjs(value))
            }})

            this.pikaday[1] = new Pikaday({ keyboardInput: false, onSelect: (value) => {
                this.endValue = zone.end(dayjs(value))
            }})

            this.calendarElements[0].prepend(this.pikaday[0].el)
            this.calendarElements[1].prepend(this.pikaday[1].el)

            this.setCalendarDates()
            this.setCalendarRange()
        },

        setCalendarDates () {
            if (this.dateObjects[0]) this.pikaday[0].setDate(this.dateObjects[0].toDate(), true)
            if (this.dateObjects[1]) this.pikaday[1].setDate(this.dateObjects[1].toDate(), true)

            if (this.dateObjects[0] && !this.dateObjects[1]) this.pikaday[1].setMinDate(this.dateObjects[0].toDate())
            if (!this.dateObjects[0] && this.dateObjects[1]) this.pikaday[0].setMaxDate(this.dateObjects[1].toDate())
        },

        setCalendarRange () {
            if (!this.dateObjects[0] || !this.dateObjects[1]) return
            if (!this.pikaday[0] || !this.pikaday[1]) return

            this.pikaday[0].hide()
            this.pikaday[0].setStartRange(this.pikaday[0].getDate())
            this.pikaday[0].setEndRange(this.pikaday[1].getDate())

            this.pikaday[1].hide()
            this.pikaday[1].setStartRange(this.pikaday[0].getDate())
            this.pikaday[1].setEndRange(this.pikaday[1].getDate())

            this.pikaday[0].show()
            this.pikaday[1].show()
        },
    }
}