import dayjs from 'dayjs'

export default () => {
    return {
        hr: null,
        min: null,
        am: null,
        timePickerValue: null,

        get format () {
            if (!this.timePickerValue) return null
            return /^\d{2}:\d{2}:\d{2}$/.test(this.timePickerValue) ? 'time' : 'datetime'
        },

        get timePickerObject () {
            if (!this.timePickerValue) return null
            let obj = this.format === 'time' ? dayjs('1970-01-01 '+this.timePickerValue) : dayjs(this.timePickerValue)
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

        setTime () {
            let hr = this.digits(this.hr)
            let min = this.digits(this.min)

            // '--' (no time yet) and anything non-numeric write nothing, so nothing but
            // a number can ever reach dayjs.
            if (!hr || !min) return

            this.hr = !+hr || hr > 12 ? '12' : hr.padStart(2, '0')
            this.min = !+min || min > 59 ? '00' : min.padStart(2, '0')

            let obj = dayjs(`1970-01-01 ${this.hr}:${this.min} ${this.am}`)

            if (!this.format || this.format === 'time') {
                this.timePickerValue = obj.format('HH:mm:ss')
            }
            else if (this.timePickerObject) {
                this.timePickerValue = this.timePickerObject
                    .set('hour', obj.get('hour'))
                    .set('minute', obj.get('minute'))
                    .set('second', obj.get('second'))
                    .toISOString()
            }
        },

        // The fields read '--' until a time is set, so a non-number counts as 0 rather than
        // turning into NaN on an arrow key. `typed` is the field's current text: the model
        // is lazy (it commits on change), so it is stale until the field is left, and the
        // arrow keys must step from what is on screen.
        up (key, typed) {
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
