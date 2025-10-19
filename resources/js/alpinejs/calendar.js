import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc'

dayjs.extend(utc)

export default (config) => {
    return {
        date: dayjs().toISOString(),
        mode: config.mode,
        period: config.period,
        calendar: null,

        get day () {
            return dayjs(this.date).format('DD');
        },

        get month () {
            return dayjs(this.date).format('MMM');
        },

        get year () {
            return dayjs(this.date).format('YYYY');
        },

        init () {
            this.$nextTick(() => this.setupCalendar())
            this.$watch('mode', () => this.calendar?.setOption('view', this.getView()))
            this.$watch('period', () => this.calendar?.setOption('view', this.getView()))
            this.$watch('date', () => this.calendar?.setOption('date', this.date))
        },

        setupCalendar () {
            import('@event-calendar/core').then(({
                DayGrid,
                List,
                ResourceTimeline,
                ResourceTimeGrid,
                TimeGrid,
                Interaction,
                createCalendar,
                destroyCalendar,
            }) => {
                this.calendar = createCalendar(
                    this.$refs.calendar,
                    [
                        DayGrid,
                        List,
                        ResourceTimeline,
                        ResourceTimeGrid,
                        TimeGrid,
                        Interaction,
                    ],
                    {
                        view: this.getView(),
                        date: this.date,
                        selectable: true,
                        headerToolbar: {},
                        select: (info) => this.emit('calendar-date-selected', info),
                        dateClick: (info) => this.emit('calendar-date-clicked', info),
                        datesSet: (info) => this.emit('calendar-changed', info),
                        eventClick: (info) => this.emit('calendar-event-clicked', info),
                        eventDrop: (info) => this.emit('calendar-event-dropped', info),
                        eventResize: (info) => this.emit('calendar-event-resized', info),
                        viewDidMount: (info) => this.emit('calendar-mounted', info),
                    },
                )
            })
        },

        getView () {
            let period = this.period || 'month'
            let mode = this.mode || 'calendar'

            if (mode === 'calendar') {
                return {
                    month: 'dayGridMonth',
                    week: 'timeGridWeek',
                    day: 'timeGridDay',
                }[period]
            }
            else if (mode === 'timeline') {
                return {
                    month: 'resourceTimelineMonth',
                    week: 'resourceTimelineWeek',
                    day: 'resourceTimelineDay',
                }[period]
            }
        },

        next () {
            if (this.period === 'month') this.date = dayjs(this.date).add(1, 'month').toISOString()
            else if (this.period === 'week') this.date = dayjs(this.date).add(1, 'week').toISOString()
            else if (this.period === 'day') this.date = dayjs(this.date).add(1, 'day').toISOString()
        },

        prev () {
            if (this.period === 'month') this.date = dayjs(this.date).subtract(1, 'month').toISOString()
            else if (this.period === 'week') this.date = dayjs(this.date).subtract(1, 'week').toISOString()
            else if (this.period === 'day') this.date = dayjs(this.date).subtract(1, 'day').toISOString()
        },

        today () {
            this.date = dayjs().toISOString()
        },

        addEvents (e) {
            if (config.name && config.name !== e.name) return

            let events = Array.isArray(e.events) ? e.events : [e.events]

            events.forEach(event => {
                let idx = this.calendar.getEvents().findIndex(item => item.id === event.id)
                if (idx > -1) this.calendar.updateEvent(this.getEventResource(event))
                else this.calendar.addEvent(this.getEventResource(event))
            })
        },

        updateEvents (e) {
            if (config.name && config.name !== e.name) return

            let events = Array.isArray(e.events) ? e.events : [e.events]

            events.forEach(event => {
                let idx = this.calendar.getEvents().findIndex(item => item.id === event.id)
                if (idx > -1) this.calendar.updateEvent(this.getEventResource(event))
            })
        },

        removeEvents (e) {
            if (config.name && config.name !== e.name) return

            let ids = Array.isArray(e.id) ? e.id : [e.id]

            ids.forEach(id => {
                let idx = this.calendar.getEvents().findIndex(item => item.id === id)
                if (idx > -1) this.calendar.removeEventById(id)
            })
        },

        getEventResource (data) {
            let event = {
                id: data.id,
                allDay: data.all_day,
                start: data.start_at ? dayjs(data.start_at).toDate() : null,
                end: data.end_at ? dayjs(data.end_at).toDate() : null,
                title: data.title,
                resourceIds: data.resources,
                editable: typeof data.editable === 'boolean' ? data.editable : true,
                startEditable: typeof data.draggable === 'boolean' ? data.draggable : true,
                durationEditable: typeof data.resizable === 'boolean' ? data.resizable : true,
                backgroundColor: data.bg_color || data.background_color,
                textColor: data.text_color,
                classNames: Array.isArray(data.class) ? data.class : data.class,
                styles: Array.isArray(data.style) ? data.style : data.style,
                extendedProps: data.meta,
            }

            Object.keys(event).forEach(key => (event[key] === null || event[key] === undefined) && delete event[key])

            return event
        },

        emit (event, info) {
            if (info.date) info.dateUtc = dayjs(info.date).utc().toISOString()
            if (info.start) info.startUtc = dayjs(info.start).utc().toISOString()
            if (info.end) info.endUtc = dayjs(info.end).utc().toISOString()

            this.$dispatch(event, {
                info,
                mode: this.mode,
                period: this.period,
                calendar: this.calendar,
            })
        },
    }
}