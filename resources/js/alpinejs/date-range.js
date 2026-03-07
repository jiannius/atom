import Pikaday from 'pikaday'
import dayjs from 'dayjs'

export default (config) => {
    return {
        pikaday: [],
        endValue: null,
        startValue: null,
        dateRangeValue: null,

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
                split[0] ? dayjs(split[0]) : null,
                split[1] ? dayjs(split[1]) : null,
            ]
        },

        init () {
            this.parse()

            this.$nextTick(() => {
                this.setCalendar()
                this.startValue = this.dateObjects[0]?.toISOString()
                this.endValue = this.dateObjects[1]?.toISOString()

                this.$watch('startValue', () => this.updateValue())
                this.$watch('endValue', () => this.updateValue())
            })
        },

        parse () {
            this.startValue = this.dateObjects[0]?.toISOString()
            this.endValue = this.dateObjects[1]?.toISOString()
        },

        preset (name) {
            let presets = {
                'today': [dayjs().startOf('day'), dayjs().endOf('day')],
                'yesterday': [dayjs().subtract(1, 'day').startOf('day'), dayjs().subtract(1, 'day').endOf('day')],
                'last-7-days': [dayjs().subtract(6, 'day').startOf('day'), dayjs().endOf('day')],
                'last-30-days': [dayjs().subtract(29, 'day').startOf('day'), dayjs().endOf('day')],
                'last-180-days': [dayjs().subtract(179, 'day').startOf('day'), dayjs().endOf('day')],
                'this-month': [dayjs().startOf('month').startOf('day'), dayjs().endOf('month').endOf('day')],
                'last-month': [dayjs().startOf('month').subtract(1, 'day').startOf('month').startOf('day'), dayjs().startOf('month').subtract(1, 'day').endOf('month').endOf('day')],
                'this-year': [dayjs().startOf('year').startOf('day'), dayjs().endOf('year').endOf('day')],
                'last-year': [dayjs().startOf('year').subtract(1, 'day').startOf('year').startOf('day'), dayjs().startOf('year').subtract(1, 'day').endOf('year').endOf('day')],
            }

            if (presets[name]) {
                this.startValue = presets[name][0].toISOString()
                this.endValue = presets[name][1].toISOString()
                this.$nextTick(() => {
                    this.setCalendarDates()
                    this.setCalendarRange()
                })
            }
        },

        updateValue () {
            let start = this.startValue || ''
            let end = this.endValue || ''
            if (!start || !end) return

            this.dateRangeValue = `${start} to ${end}`
            this.setCalendarRange()
        },

        setCalendar () {
            this.pikaday[0] = new Pikaday({ onSelect: (value) => {
                let dj = dayjs(value)
                this.startValue = this.dateObjects[0]
                    ? this.dateObjects[0].set('year', dj.get('year')).set('month', dj.get('month')).set('date', dj.get('date')).startOf('day').toISOString()
                    : dj.startOf('day').toISOString()
            }})

            this.pikaday[1] = new Pikaday({ onSelect: (value) => {
                let dj = dayjs(value)
                this.endValue = this.dateObjects[1]
                    ? this.dateObjects[1].set('year', dj.get('year')).set('month', dj.get('month')).set('date', dj.get('date')).endOf('day').toISOString()
                    : dj.endOf('day').toISOString()
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