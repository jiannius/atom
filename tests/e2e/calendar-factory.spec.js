import { test, expect } from '@playwright/test'
import calendar from '../../resources/js/alpinejs/calendar.js'

// The calendar factory's own logic, driven in node with no Alpine and no DOM: a payload is put on it
// by hand, and the getters and methods are asked what they answer. The browser behaviour is in
// calendar.spec.js.

const palette = {
    blue: { tint: 'tint-blue', accent: 'accent-blue', dot: 'dot-blue' },
    zinc: { tint: 'tint-zinc', accent: 'accent-zinc', dot: 'dot-zinc' },
    red: { tint: 'tint-red', accent: 'accent-red', dot: 'dot-red' },
}

test.describe('colour classes', () => {
    test('an item with no colour is zinc, whatever a host did to blue', () => {
        const engine = calendar()

        // a host that restyles blue (its brand colour, say) must not recolour the items that never asked for it
        engine.palette = { ...palette, blue: { tint: 'tint-brand', accent: 'accent-brand', dot: 'dot-brand' } }

        expect(engine.colorClass({ color: null }, 'tint')).toBe('tint-zinc')
        expect(engine.colorClass({ color: null }, 'accent')).toBe('accent-zinc')
        expect(engine.colorClass({ color: null }, 'dot')).toBe('dot-zinc')
    })

    test('a colour the palette does not know falls back to zinc as well', () => {
        const engine = calendar()

        engine.palette = palette

        expect(engine.colorClass({ color: 'chartreuse' }, 'dot')).toBe('dot-zinc')
    })

    test('a named colour keeps its own classes, and a hex has none', () => {
        const engine = calendar()

        engine.palette = palette

        expect(engine.colorClass({ color: 'red' }, 'tint')).toBe('tint-red')
        expect(engine.colorClass({ color: '#ff8800' }, 'tint')).toBe('')
    })
})
