import Pikaday from 'pikaday'
import dayjs from 'dayjs'
import { parseTypedDate } from '../helpers/typed-date'
import { zoneOf } from '../helpers/zone'

export default (config) => {
    // With `config.timezone` the picker reads and writes the wall clock in that zone (helpers/zone.js); without one, the browser's.
    const zone = zoneOf(config.timezone)

    return {
        pikaday: null,
        visible: false,
        datePickerValue: null,

        /**
         * Whether the server marked this picker disabled (its own `disabled`, or a disabled form's). Read from the DOM at event time, never held in `x-data`:
         * a morph re-renders the marker and does not rebuild the factory. Not reactive, so a binding must not read it. The dropdown reads the same marker
         * and opens nothing; this keeps the picker from writing through the paths that do not need the panel (typing, the clear x, a Pikaday select).
         */
        get disabled () {
            return this.$root.hasAttribute('data-atom-disabled')
        },

        get datePickerObject () {
            return this.datePickerValue ? zone.read(this.datePickerValue) : null
        },

        // Whether the value holds a time of day. With `time` off, an empty value or a plain YYYY-MM-DD gets a plain date back; a value that
        // already carries a time (an instant, an old bookmark) keeps it, so a pick never drops a time it was given.
        get carriesTime () {
            return !!this.datePickerValue && !/^\d{4}-\d{2}-\d{2}$/.test(String(this.datePickerValue).trim())
        },

        get datePickerString () {
            return config.time
                ? this.datePickerObject?.format('DD MMM YYYY hh:mm A')
                : this.datePickerObject?.format('DD MMM YYYY')
        },

        get popover () {
            return this.$root.querySelector('[popover]')
        },

        get calendar () {
            return this.$root.querySelector('[data-atom-date-picker-calendar]')
        },

        init () {
            this.$watch('visible', () => {
                if (this.visible) this.$nextTick(() => this.setCalendar())
                else this.destroyCalendar()
            })
        },

        keydown () {
            if (this.disabled) return

            if (!this.visible) this.popover.showPopover()
        },

        // Commit what was typed into the input (on blur / Enter). A whole date
        // becomes the value; an empty input clears it; anything else is put back
        // to the last good value, so a half-typed date never wipes the model.
        // Returns 'unchanged', 'committed' or 'reverted'.
        commitTyped (input) {
            // a disabled picker takes nothing typed: the input shows the value again, and the model is left as the server sent it
            if (this.disabled) {
                this.revertTyped(input)
                return 'unchanged'
            }

            let text = input.value.trim()
            if (text === (this.datePickerString ?? '')) return 'unchanged'

            if (!text) {
                this.setTypedValue(null)
                return 'committed'
            }

            let parsed = parseTypedDate(text, { time: config.time })

            if (!parsed) {
                this.revertTyped(input)
                return 'reverted'
            }

            // With no time and nothing but a day set (or nothing), the day is the value. Otherwise a date-only entry keeps the time already set.
            if (!config.time && !parsed.hasTime && !this.carriesTime) {
                this.setTypedValue(zone.date(parsed.date))
            }
            else {
                let value = this.datePickerObject && !parsed.hasTime
                    ? this.datePickerObject
                        .set('year', parsed.date.get('year'))
                        .set('month', parsed.date.get('month'))
                        .set('date', parsed.date.get('date'))
                    : parsed.date

                this.setTypedValue(zone.write(value))
            }
            // The display re-renders only if the formatted string differs, so a
            // re-spelling of the same date (`1/10/2024`) is tidied by hand.
            this.$nextTick(() => input.value = this.datePickerString ?? '')

            return 'committed'
        },

        revertTyped (input) {
            input.value = this.datePickerString ?? ''
        },

        // The clear x. The server draws none for a disabled picker; this keeps a handler called by hand from emptying it.
        clear () {
            if (this.disabled) return

            this.datePickerValue = null
        },

        setTypedValue (value) {
            if (this.disabled) return

            this.datePickerValue = value
            this.$nextTick(() => this.$dispatch('input', this.datePickerValue))

            // a Date, never a string: WebKit's Date cannot parse "YYYY-MM-DD HH:mm:ss" (RangeError: Invalid Date)
            if (this.pikaday && this.datePickerObject) {
                this.pikaday.setDate(this.datePickerObject.toDate(), true)
            }
        },

        typedEnter (event) {
            // An edited entry is committed (or put back) and the form is not
            // submitted; an untouched one lets Enter submit as it always has.
            let result = this.commitTyped(event.target)
            if (result === 'unchanged') return

            event.preventDefault()

            if (result === 'committed' && !config.time && this.popover?.matches(':popover-open')) {
                this.popover.hidePopover()
            }
        },

        setCalendar () {
            this.pikaday?.destroy()
            this.pikaday = null

            // the panel does not open under the marker; a calendar built for one that did (a hand-called open) would let a day be picked
            if (this.disabled) return

            // No keyboardInput: Pikaday listens on the document, so with the input now
            // typeable, a caret arrow would move the picked day and commit it.
            this.pikaday = new Pikaday({
                keyboardInput: false,
                onSelect: value => {
                    if (this.disabled) return

                    let obj = dayjs(value)

                    if (!config.time && !this.carriesTime) {
                        this.datePickerValue = zone.date(obj)
                    }
                    else if (this.datePickerObject) {
                        this.datePickerValue = zone.write(this.datePickerObject
                            .set('year', obj.get('year'))
                            .set('month', obj.get('month'))
                            .set('date', obj.get('date')))
                    }
                    else {
                        this.datePickerValue = zone.write(obj)
                    }

                    this.$nextTick(() => this.$dispatch('input', this.datePickerValue))

                    !config.time && this.popover.hidePopover()
                },
            })

            if (this.datePickerObject) {
                this.pikaday.setDate(this.datePickerObject.toDate(), true)
            }

            this.calendar.prepend(this.pikaday.el)
        },

        destroyCalendar () {
            this.pikaday?.destroy()
            this.pikaday = null
            this.calendar.innerHTML = ''
        },
    }
}
