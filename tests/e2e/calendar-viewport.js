// Helpers of the calendar's pointer specs (calendar-select, calendar-move) for what engines do differently with a mouse.

// A mouse point outside the viewport is an error, in every engine. Chromium and WebKit take it, but Playwright's Firefox (Juggler) drops the press and the
// release of one outside the window and sends the moves as a pointer with no button at (0,0), which gives the gesture up: a test that drags to a day below
// the fold passed in two engines and failed in the third. Scroll the day into view before measuring it (`reveal()`), don't raise the viewport.
export function guardMouse (page) {
    const inside = (x, y) => {
        const { width, height } = page.viewportSize() ?? { width: Infinity, height: Infinity }

        if (!(x >= 0 && y >= 0 && x < width && y < height)) {
            throw new Error(`the mouse is sent to (${Math.round(x)}, ${Math.round(y)}), outside the ${width}x${height} viewport: scroll the target into view before measuring it`)
        }
    }

    for (const method of ['move', 'click', 'dblclick']) {
        const original = page.mouse[method].bind(page.mouse)

        page.mouse[method] = (x, y, ...rest) => { inside(x, y); return original(x, y, ...rest) }
    }
}

// The pointerId of the page's real mouse, which differs by engine (Chromium and WebKit 1, Firefox 0): a synthetic pointer event that is meant to be
// the mouse's, followed by real moves, has to carry it, or the engine sees two pointers.
export async function mousePointerId (page) {
    await page.evaluate(() => {
        window.__mousePointerId = null
        document.addEventListener('pointermove', (event) => { window.__mousePointerId ??= event.pointerId }, { capture: true })
    })
    await page.mouse.move(3, 3)
    await page.mouse.move(6, 6)

    return page.evaluate(() => window.__mousePointerId)
}
