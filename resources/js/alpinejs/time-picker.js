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

        setTime () {
            if (this.hr === '--' || this.min === '--' || !this.hr || !this.min) return

            this.hr = !+this.hr || this.hr > 12 ? '12' : this.hr.toString().padStart(2, '0')
            this.min = !+this.min || this.min > 59 ? '00' : this.min.toString().padStart(2, '0')

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

        up (key) {
            if (key === 'hr') {
                this.hr = +this.hr >= 12 ? 1 : +this.hr + 1
            } else if (key === 'min') {
                this.min = +this.min >= 59 ? 0 : +this.min + 1
            } else if (key === 'am') {
                this.am = this.am === 'AM' ? 'PM' : 'AM'
            }
        },

        down (key) {
            if (key === 'hr') {
                this.hr = +this.hr <= 1 ? 12 : +this.hr - 1
            } else if (key === 'min') {
                this.min = +this.min <= 0 ? 59 : +this.min - 1
            } else if (key === 'am') {
                this.am = this.am === 'AM' ? 'PM' : 'AM'
            }
        }
    }
}