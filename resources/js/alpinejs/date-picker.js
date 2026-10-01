import Pikaday from 'pikaday'
import dayjs from 'dayjs'
import { parseTypedDate } from '../helpers/typed-date'

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

        // Commit what was typed into the input (on blur / Enter). A whole date
        // becomes the value; an empty input clears it; anything else is put back
        // to the last good value, so a half-typed date never wipes the model.
        // Returns 'unchanged', 'committed' or 'reverted'.
        commitTyped (input) {
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

            // Like a calendar pick: a date-only entry keeps the time already set.
            let value = this.datePickerObject && !parsed.hasTime
                ? this.datePickerObject
                    .set('year', parsed.date.get('year'))
                    .set('month', parsed.date.get('month'))
                    .set('date', parsed.date.get('date'))
                : parsed.date

            this.setTypedValue(value.toISOString())
            // The display re-renders only if the formatted string differs, so a
            // re-spelling of the same date (`1/10/2024`) is tidied by hand.
            this.$nextTick(() => input.value = this.datePickerString ?? '')

            return 'committed'
        },

        revertTyped (input) {
            input.value = this.datePickerString ?? ''
        },

        setTypedValue (value) {
            this.datePickerValue = value
            this.$nextTick(() => this.$dispatch('input', this.datePickerValue))

            if (this.pikaday && this.datePickerObject) {
                this.pikaday.setDate(this.datePickerObject.format('YYYY-MM-DD HH:mm:ss'), true)
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
