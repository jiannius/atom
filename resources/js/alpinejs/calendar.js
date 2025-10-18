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

        updateEvents (e) {
            if (config.name && config.name !== e.name) return

            let events = e.events || []

            events.forEach(event => {
                event = {
                    id: event.id,
                    allDay: event.all_day,
                    start: event.start_at ? dayjs(event.start_at).toDate() : null,
                    end: event.end_at ? dayjs(event.end_at).toDate() : null,
                    title: event.title,
                    resourceIds: event.resources,
                    editable: typeof event.editable === 'boolean' ? event.editable : true,
                    startEditable: typeof event.draggable === 'boolean' ? event.draggable : true,
                    durationEditable: typeof event.resizable === 'boolean' ? event.resizable : true,
                    backgroundColor: event.bg_color || event.background_color,
                    textColor: event.text_color,
                    classNames: Array.isArray(event.class) ? event.class : event.class,
                    styles: Array.isArray(event.style) ? event.style : event.style,
                    extendedProps: event.meta,
                }
                
                Object.keys(event).forEach(key => (event[key] === null || event[key] === undefined) && delete event[key])
                
                let idx = this.calendar.getEvents().findIndex(item => item.id === event.id)

                if (idx > -1) this.calendar.updateEvent(event)
                else this.calendar.addEvent(event)
            })
        },

        removeEvents (ids) {
            if (config.name && config.name !== e.name) return

            ids = Array.isArray(ids) ? ids : [ids]

            ids.forEach(id => {
                let idx = this.calendar.getEvents().findIndex(item => item.id === id)
                if (idx > -1) this.calendar.removeEventById(id)
            })
        },

        emit (event, data) {
            if (data.start) data.startUtc = dayjs(data.start).utc().toISOString()
            if (data.end) data.endUtc = dayjs(data.end).utc().toISOString()
            this.$dispatch(event, { ...data, calendar: this.calendar })
        },
    }
}