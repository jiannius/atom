import { test, expect, devices } from '@playwright/test'

// Density: compact, comfortable, and a touch device. The page opts in to the consumer Tailwind build the way a host does
// (`node tests/consumer/build.mjs` first). Chromium's iPad descriptor reports `pointer: coarse` (asserted below, so a change
// in the browser shows up here rather than as a silent pass), so it stands in for a touch device.

const { defaultBrowserType, ...ipad } = devices['iPad (gen 7)']

const PROFILES = {
  compact: { control: 28, row: 40, xs: '12px', sm: '13px', base: '14px', body: '13px' },
  comfortable: { control: 32, row: 44, xs: '13px', sm: '14px', base: '15px', body: '14px' },
}

async function measure(page, density, tw = 'latest') {
  await page.goto(`/atom/e2e/tokens?tw=${tw}&density=${density}`)
  await page.evaluate(() => document.fonts.ready)

  return page.evaluate(() => {
    const el = (id) => document.querySelector(`[data-testid="${id}"]`)
    return {
      attr: document.body.dataset.atomDensity,
      pointerCoarse: matchMedia('(pointer: coarse)').matches,
      control: el('control-button').getBoundingClientRect().height,
      input: el('control-input').getBoundingClientRect().height,
      row: el('row-one').getBoundingClientRect().height,
      xs: getComputedStyle(el('size-xs')).fontSize,
      sm: getComputedStyle(el('size-sm')).fontSize,
      base: getComputedStyle(el('size-base')).fontSize,
      body: getComputedStyle(document.body).fontSize,
      nestedControl: el('nested-button').getBoundingClientRect().height,
      nestedXs: getComputedStyle(el('nested-xs')).fontSize,
    }
  })
}

for (const tw of ['floor', 'latest']) {
  test.describe(`density, ${tw} consumer build`, () => {
    for (const [density, want] of Object.entries(PROFILES)) {
      test(`${density}: controls, rows and the three smallest type sizes`, async ({ page }) => {
        const got = await measure(page, density, tw)

        expect(got.pointerCoarse, 'a desktop mouse is not a coarse pointer').toBe(false)
        expect(got.attr).toBe(density)
        expect(got.control).toBe(want.control)
        expect(got.input).toBe(want.control)
        expect(got.row).toBe(want.row)
        expect({ xs: got.xs, sm: got.sm, base: got.base, body: got.body }).toEqual({ xs: want.xs, sm: want.sm, base: want.base, body: want.body })
      })
    }

    test('a subtree can ask for the other density', async ({ page }) => {
      const got = await measure(page, 'compact', tw)

      // the page is compact; the nested section is comfortable
      expect(got.control).toBe(28)
      expect(got.nestedControl).toBe(32)
      expect(got.nestedXs).toBe('13px')
    })

    for (const density of ['compact', 'comfortable']) {
      test.describe(`touch (iPad profile), ${density}`, () => {
        test.use({ ...ipad })

        test('Chromium reports a coarse pointer for the iPad descriptor', async ({ page }) => {
          const got = await measure(page, density, tw)

          expect(got.pointerCoarse).toBe(true)
        })

        test('controls are 40px and rows 48px whatever the density; the type is not touched', async ({ page }) => {
          const got = await measure(page, density, tw)

          expect(got.control).toBe(40)
          expect(got.input).toBe(40)
          expect(got.row).toBe(48)
          expect(got.nestedControl, 'a nested density does not undo the touch size').toBe(40)
          expect({ xs: got.xs, sm: got.sm, base: got.base }).toEqual({ xs: PROFILES[density].xs, sm: PROFILES[density].sm, base: PROFILES[density].base })
        })
      })
    }
  })
}

test('a bare <atom:html> is comfortable, and its body text is 14px', async ({ page }) => {
  await page.goto('/atom/e2e/consumer-tailwind?tw=latest')

  expect(await page.evaluate(() => ({ attr: document.body.dataset.atomDensity, size: getComputedStyle(document.body).fontSize }))).toEqual({ attr: 'comfortable', size: '14px' })
})

test('the sidebar layout is compact', async ({ page }) => {
  await page.goto('/atom/docs')

  expect(await page.evaluate(() => document.body.dataset.atomDensity)).toBe('compact')
})
