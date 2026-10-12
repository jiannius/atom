<atom:html title="E2E: Calendar (look)" :vite="[]" :dark="false" class="min-h-screen bg-white dark:bg-zinc-900">
{{-- The calendar as it looks (tests/Fixtures/CalendarLookFixture.php), for the screenshots and the contrast check: every state in one
     fixed set of items, the clock frozen at 2026-10-09 10:30. The page ships no Tailwind and no stand-in for it, so what it shows
     on its own is the structure atom.css owns; the look is read with Tailwind injected (the contact sheet, tests/e2e/calendar-look.spec.js).

     A move, a resize, a selection and a pending item are set by hand here, to be looked at without a pointer: a state is set on the engine:
     ?state=select|move|resize|pending|popover, or window.atomLook.apply(state) once the page is up. ?view=week|day|month,
     ?locale=zh and ?scroll=HH:MM as in the other calendar pages. --}}
<livewire:atom-e2e-calendar-look />

<script>
    // what each gesture draws, by view; times are the viewer's wall clock
    const states = {
        month: {
            select: { kind: 'select', start: '2026-10-12', end: '2026-10-14' },
            move: { kind: 'move', id: 'conference', start: '2026-10-13', end: '2026-10-17' },
            resize: { kind: 'resize', id: 'launch', start: '2026-10-05', end: '2026-10-12' },
            pending: { kind: 'pending', start: '2026-10-20', end: '2026-10-21', title: 'New task' },
        },
        week: {
            select: { kind: 'select', start: '2026-10-08T11:00', end: '2026-10-08T12:30' },
            move: { kind: 'move', id: 'design', start: '2026-10-08T13:30', end: '2026-10-08T15:00' },
            resize: { kind: 'resize', id: 'client', start: '2026-10-09T10:00', end: '2026-10-09T12:15' },
            pending: { kind: 'pending', start: '2026-10-10T10:00', end: '2026-10-10T11:00', title: 'New task' },
        },
        day: {
            select: { kind: 'select', start: '2026-10-09T13:30', end: '2026-10-09T14:45' },
            move: { kind: 'move', id: 'design', start: '2026-10-09T13:15', end: '2026-10-09T14:45' },
            resize: { kind: 'resize', id: 'client', start: '2026-10-09T10:00', end: '2026-10-09T12:15' },
            pending: { kind: 'pending', start: '2026-10-09T13:30', end: '2026-10-09T14:30', title: 'New task' },
        },
    }

    window.atomLook = {
        engine: () => window.Alpine.$data(document.querySelector('[data-atom-calendar]')),

        apply (state = 'none') {
            const engine = this.engine()

            engine.closeDay(false)
            engine.preview = null

            if (state === 'popover') {
                // a busy month day, or the all-day row's "+N more" in the week
                const trigger = engine.view === 'month'
                    ? document.querySelector('[data-atom-calendar-day][data-date="2026-10-14"]')
                    : document.querySelector('[data-atom-calendar-grid] [data-atom-calendar-more]')

                if (trigger) engine.openDay(engine.view === 'month' ? '2026-10-14' : '2026-10-07', trigger)
            } else if (states[engine.view]?.[state]) {
                engine.preview = states[engine.view][state]
            }
        },
    }

    document.addEventListener('alpine:initialized', () => {
        const query = new URLSearchParams(location.search)

        if (query.has('state')) window.atomLook.apply(query.get('state'))
    })
</script>

@livewireScripts
</atom:html>
