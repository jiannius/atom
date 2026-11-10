import dayjs from 'dayjs'
import { zoneOf } from '../helpers/zone'

const pad = (n) => String(n).padStart(2, '0')

export default (config = {}) => {
    // A full date-time value is read and written as the wall clock in `config.timezone` (a date-picker's); a bare time never is.
    const zone = zoneOf(config.timezone)

    return {
        hr: null,
        min: null,
        am: null,
        timePickerValue: null,

        /**
         * Whether the server marked this picker disabled (its own `disabled`, or a disabled form's). Read from the DOM at event time, never held in `x-data`:
         * a morph re-renders the marker and does not rebuild the factory. Not reactive, so a binding must not read it.
         */
        get disabled () {
            return this.$root.hasAttribute('data-atom-disabled')
        },

        get format () {
            if (!this.timePickerValue) return null
            return /^\d{2}:\d{2}:\d{2}$/.test(this.timePickerValue) ? 'time' : 'datetime'
        },

        get timePickerObject () {
            if (!this.timePickerValue) return null

            // A bare time is built from its numbers: a date is never parsed from a string here, since WebKit's Date reads far less than Chrome's.
            if (this.format === 'time') {
                const [h, m, s] = this.timePickerValue.split(':').map(Number)

                // Date would roll 25:99:99 over to the next day and show it as 02:40: an out-of-range time is no time, as it was
                if (h > 23 || m > 59 || s > 59) return null

                return dayjs(new Date(1970, 0, 1, h, m, s))
            }

            const obj = zone.read(this.timePickerValue)

            return obj.isValid() ? obj : null
        },

        init () {
            this.parse()
            this.$watch('timePickerValue', () => this.parse())
            this.$watch('hr', () => this.setTime())
            this.$watch('min', () => this.setTime())
            this.$watch('am', () => this.setTime())
        },

        parse () {
            this.hr = this.timePickerObject?.format('hh') || '--'
            this.min = this.timePickerObject?.format('mm') || '--'
            this.am = this.timePickerObject?.format('A') || 'AM'
        },

        // Only digits count: fullwidth digits fold to ASCII, everything else is dropped.
        digits (value) {
            return String(value ?? '').normalize('NFKC').replace(/\D/g, '').slice(0, 2)
        },

        // Runs on `input`, so the field never shows (or holds) anything but digits.
        sanitise (el) {
            let clean = this.digits(el.value)
            if (el.value !== clean) el.value = clean
        },

        // The picker's only write to its model, and the one the hour, minute and AM/PM watchers all end in, so a disabled picker stops here: showing a value
        // (parse) goes through the same watchers and must not turn a value the server sent into another one.
        setTime () {
            if (this.disabled) return

            let hr = this.digits(this.hr)
            let min = this.digits(this.min)

            // '--' (no time yet) and anything non-numeric write nothing, so nothing but
            // a number can ever reach dayjs.
            if (!hr || !min) return

            this.hr = !+hr || hr > 12 ? '12' : hr.padStart(2, '0')
            this.min = !+min || min > 59 ? '00' : min.padStart(2, '0')

            // the 24-hour clock from the numbers on screen (12 AM is 0, 12 PM is 12)
            const hour = (+this.hr % 12) + (this.am === 'PM' ? 12 : 0)

            if (!this.format || this.format === 'time') {
                this.timePickerValue = `${pad(hour)}:${this.min}:00`
            }
            else if (this.timePickerObject) {
                this.timePickerValue = zone.write(this.timePickerObject
                    .set('hour', hour)
                    .set('minute', +this.min)
                    .set('second', 0))
            }
        },

        // The fields read '--' until a time is set, so a non-number counts as 0 rather than
        // turning into NaN on an arrow key. `typed` is the field's current text: the model
        // is lazy (it commits on change), so it is stale until the field is left, and the
        // arrow keys must step from what is on screen.
        up (key, typed) {
            if (this.disabled) return

            let hr = +this.digits(key === 'hr' ? typed ?? this.hr : this.hr) || 0
            let min = +this.digits(key === 'min' ? typed ?? this.min : this.min) || 0

            if (key === 'hr') {
                this.hr = hr >= 12 ? 1 : hr + 1
            } else if (key === 'min') {
                this.min = min >= 59 ? 0 : min + 1
            } else if (key === 'am') {
                this.am = this.am === 'AM' ? 'PM' : 'AM'
            }
        },

        down (key, typed) {
            if (this.disabled) return

            let hr = +this.digits(key === 'hr' ? typed ?? this.hr : this.hr) || 0
            let min = +this.digits(key === 'min' ? typed ?? this.min : this.min) || 0

            if (key === 'hr') {
                this.hr = hr <= 1 ? 12 : hr - 1
            } else if (key === 'min') {
                this.min = min <= 0 ? 59 : min - 1
            } else if (key === 'am') {
                this.am = this.am === 'AM' ? 'PM' : 'AM'
            }
        }
    }
}
