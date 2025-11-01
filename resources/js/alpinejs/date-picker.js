import Pikaday from 'pikaday'
import dayjs from 'dayjs'

export default (config) => {
    return {
        pikaday: null,
        visible: false,
        datePickerValue: null,

        get datePickerObject () {
            return this.datePickerValue ? dayjs(this.datePickerValue) : null
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
            if (!this.visible) this.popover.showPopover()
        },

        setCalendar () {
            this.pikaday?.destroy()

            this.pikaday = new Pikaday({
                onSelect: value => {
                    let obj = dayjs(value)

                    if (this.datePickerObject) {
                        this.datePickerValue = this.datePickerObject
                            .set('year', obj.get('year'))
                            .set('month', obj.get('month'))
                            .set('date', obj.get('date'))
                            .toISOString()
                    }
                    else {
                        this.datePickerValue = obj.toISOString()
                    }

                    this.$nextTick(() => this.$dispatch('input', this.datePickerValue))

                    !config.time && this.popover.hidePopover()
                },
            })

            if (this.datePickerObject) {
                this.pikaday.setDate(this.datePickerObject.format('YYYY-MM-DD HH:mm:ss'), true)
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
