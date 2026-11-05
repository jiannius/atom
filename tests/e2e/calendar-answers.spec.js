import { test, expect } from '@playwright/test'
import calendar from '../../resources/js/alpinejs/calendar.js'

// What the calendar listens to in Livewire to know that the server has answered a move or a resize (watchAnswers() in calendar.js), driven in node
// against a fake `Livewire.interceptMessage`, as calendar-factory.spec.js drives the factory. It must hold with the versions of Livewire the package allows
// (^4.0: `onMorphed` came with 4.4.2, so before it the callback is not given), and it must never break Livewire's own message flow. The browser behaviour,
// with the real Livewire, is in calendar-move.spec.js.

const PAYLOAD = 'data-atom-calendar-payload'

test.beforeEach(() => {
    globalThis.cancelAnimationFrame = () => {}
})

test.afterEach(() => {
    delete globalThis.window
})

// A calendar on a root that says what its payload is, and a Livewire that hands its message interceptor back to the test. `send()` is a request of the
// component: it is sent, and `succeed()` is its response, with the payload on the root as it is after the morph.
function harness ({ livewire = true, component = true, root = {} } = {}) {
    const interceptors = []
    const attributes = { [PAYLOAD]: 'one' }
    const el = { getAttribute: (name) => attributes[name] ?? null, closest: () => (component ? {} : null), querySelector: () => null, ...root }

    globalThis.window = livewire === true ? { Livewire: { interceptMessage: (callback) => { interceptors.push(callback); return () => interceptors.splice(0) } } } : (livewire || {})

    const engine = calendar()

    engine.rootEl = el
    engine.overrides = {}

    const stop = engine.watchAnswers()

    const send = ({ actions = ['reschedule'], inside = true, shape = null } = {}) => {
        const message = { component: { el: { contains: () => inside } }, actions: new Set(actions.map((name) => ({ name }))), ...shape }
        const hooks = {}

        interceptors.forEach((callback) => callback({ message, onSend: (fn) => { hooks.send = fn }, onSuccess: (fn) => { hooks.success = fn } }))
        hooks.send?.()

        return {
            // the response: `render` is whether it has html, `morph` which of Livewire's hooks it offers, `payload` what the root says once it is morphed
            succeed ({ render = true, morph = 'onMorphed', payload = null } = {}) {
                const registered = {}
                const offered = { onMorphed: (fn) => { registered.onMorphed = fn }, onRender: (fn) => { registered.onRender = fn } }

                hooks.success?.({ payload: { effects: render ? { html: '<div></div>' } : {} }, ...(morph === 'both' ? offered : (morph ? { [morph]: offered[morph] } : {})) })

                if (payload !== null) attributes[PAYLOAD] = payload

                registered.onMorphed?.()
                registered.onRender?.()

                return registered
            },
        }
    }

    // an item that was dropped now: what commitChange() records
    const drop = (id, extra = {}) => { engine.overrides = { ...engine.overrides, [id]: { range: { start: 'a', end: 'b' }, token: Symbol(), since: engine.sent, held: false, stale: false, ...extra } } }

    return { engine, stop, send, drop, attributes }
}

test.describe('without what it listens to', () => {
    test('a Livewire with no interceptMessage, no Livewire at all and a calendar outside a component hear nothing, and throw nothing', () => {
        for (const options of [{ livewire: { Livewire: {} } }, { livewire: {} }, { component: false }]) {
            const { engine, stop } = harness(options)

            expect(typeof stop).toBe('function')
            expect(() => stop()).not.toThrow()
            expect(engine.sent).toBe(0)
        }
    })

    test('a moved item stays where it was put when nothing is heard', () => {
        const { engine, drop } = harness({ livewire: {} })

        drop('10')
        engine.answer(5)

        // an answer that is handed in by hand still releases: it is only the hearing that is missing
        expect(Object.keys(engine.overrides)).toEqual([])
    })
})

test.describe('a Livewire that is older than onMorphed', () => {
    test('is released on the render, and the render is the one of a request sent after the drop', () => {
        const { engine, send, drop } = harness()

        drop('10')

        const request = send()

        // 4.0.0 to 4.4.1: the callback is given `onRender` and no `onMorphed`
        request.succeed({ morph: 'onRender' })

        expect(Object.keys(engine.overrides)).toEqual([])
    })

    test('a response that offers neither hook is not heard, and does not throw', () => {
        const { engine, send, drop } = harness()

        drop('10')

        expect(() => send().succeed({ morph: null })).not.toThrow()
        expect(Object.keys(engine.overrides)).toEqual(['10'])
    })

    test('a throw in what it does can never reach Livewire: not in the interceptor, not in a hook', () => {
        const { engine, send, drop } = harness()

        drop('10')

        // a message of a shape that is not expected, an action list that throws, a root that cannot be read
        const warn = console.warn
        const warned = []

        console.warn = (...args) => warned.push(args[0])

        try {
            expect(() => send({ shape: { component: undefined } }).succeed()).not.toThrow()
            expect(() => send({ shape: { component: { el: { contains: () => { throw new Error('no') } } } } }).succeed()).not.toThrow()

            const request = send()

            engine.rootEl.getAttribute = () => { throw new Error('gone') }

            expect(() => request.succeed()).not.toThrow()
        } finally {
            console.warn = warn
        }

        expect(engine.overrides['10']).toBeTruthy()
        expect(warned.length).toBeGreaterThan(0)
    })
})

test.describe('which render is an answer', () => {
    test('one that came from a request sent before the drop is not, and one sent after it is', () => {
        const { engine, send, drop } = harness()

        // on its way when the item is dropped
        const early = send({ actions: ['bump'] })

        drop('10')

        expect(engine.overrides['10'].since).toBe(1)

        early.succeed()

        expect(Object.keys(engine.overrides)).toEqual(['10'])

        // sent after: the host's call, with a render
        send().succeed()

        expect(Object.keys(engine.overrides)).toEqual([])
    })

    test('a response with no render, and a request with only Livewire\'s own calls and the same payload, are not', () => {
        const { engine, send, drop } = harness()

        drop('10')

        send().succeed({ render: false })
        send({ actions: ['$commit'] }).succeed()
        send({ actions: ['$refresh'] }).succeed()

        expect(Object.keys(engine.overrides)).toEqual(['10'])
    })

    test('a render that changes the payload is, whatever it asked for', () => {
        const { engine, send, drop } = harness()

        drop('10')

        // a poll or a refresh that shows the server's own state
        send({ actions: ['$refresh'] }).succeed({ payload: 'two' })

        expect(Object.keys(engine.overrides)).toEqual([])

        // and the payload is compared with what the root said before the render, not with what a payload was once
        drop('11')
        send({ actions: ['$commit'] }).succeed({ payload: 'two' })

        expect(Object.keys(engine.overrides)).toEqual(['11'])
    })

    test('a request of a component that does not hold the calendar is not heard', () => {
        const { engine, send, drop } = harness()

        drop('10')
        send({ inside: false }).succeed({ payload: 'two' })

        expect(Object.keys(engine.overrides)).toEqual(['10'])
    })

    test('a held item is marked stale, so that its promise puts it where the server has it; an item that is not held goes', () => {
        const { engine, send, drop } = harness()

        drop('10', { held: true })
        drop('11')
        send().succeed()

        expect(Object.keys(engine.overrides)).toEqual(['10'])
        expect(engine.overrides['10'].stale).toBe(true)
    })
})

test.describe('an answer that comes while a gesture is on', () => {
    const gesture = () => ({ kind: 'move', active: false, timer: null, frame: null, abort: new AbortController() })

    test('waits for the gesture to end, and the highest one is the one that counts', () => {
        const { engine, send, drop } = harness()

        drop('10')
        engine.gesture = gesture()

        const first = send()
        const second = send()

        second.succeed()
        first.succeed()

        // nothing under the pointer moved
        expect(Object.keys(engine.overrides)).toEqual(['10'])
        expect(engine.answered).toBe(2)

        engine.endGesture()

        expect(Object.keys(engine.overrides)).toEqual([])
        expect(engine.answered).toBe(0)
    })

    test('does not release what the gesture that ended put down: that drop is newer than the answer', () => {
        const { engine, send, drop } = harness()

        drop('10')
        engine.gesture = gesture()
        send().succeed()

        // the drop of the item that was being moved: commitChange() records the requests sent so far
        drop('15')

        engine.endGesture()

        expect(Object.keys(engine.overrides)).toEqual(['15'])
    })

    test('is let go of when the calendar is torn down, not applied', () => {
        const { engine, send, drop } = harness()

        drop('10')
        engine.gesture = gesture()
        send().succeed()
        engine.endGesture(false)

        expect(Object.keys(engine.overrides)).toEqual(['10'])
        expect(engine.answered).toBe(0)
    })
})

test.describe('the count in a line', () => {
    test('is filled in whatever the case of its placeholder: Laravel keeps :COUNT when it is asked with :count, and writes :Count as :count', () => {
        const engine = calendar()

        expect(engine.countText(':count more', 4)).toBe('4 more')
        expect(engine.countText(':COUNT MORE', 4)).toBe('4 MORE')
        expect(engine.countText('+:Count more (:count)', 12)).toBe('+12 more (12)')
        expect(engine.countText([':COUNT one', ':COUNT many'], 150)).toBe('150 many')
    })
})
