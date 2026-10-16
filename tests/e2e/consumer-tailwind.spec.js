import { test, expect } from '@playwright/test'

// The e2e rig has no Tailwind, so utilities such as `flex` are absent from every other page (see the
// testing notes). This page opts in to the stylesheet that tests/consumer/build.mjs compiles the way a
// consuming app does, once with the hosts' minimum Tailwind (floor) and once with the newest 4.x
// (latest). Run `node tests/consumer/build.mjs` first.

const display = (page, testid) => page.getByTestId(testid).evaluate((el) => getComputedStyle(el).display)

test('without the opt-in the page has no Tailwind utilities (negative control)', async ({ page }) => {
  await page.goto('/atom/e2e/consumer-tailwind')

  expect(await display(page, 'utility')).not.toBe('flex')
  expect(await display(page, 'hidden')).not.toBe('none')
})

for (const tw of ['floor', 'latest']) {
  test(`with the ${tw} consumer build the utilities apply`, async ({ page }) => {
    const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
    expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

    await page.goto(`/atom/e2e/consumer-tailwind?tw=${tw}`)

    await expect.poll(() => display(page, 'utility')).toBe('flex')
    expect(await display(page, 'hidden')).toBe('none')

    // `flex` and `hidden` above are written in the e2e view itself, so they say nothing about the scan of
    // components/. This class is written only in components/button/index.blade.php (no view, no src file),
    // so the build holds it only if that scan worked.
    expect((await css.text()).includes('group-\\[\\.is-loading\\]\\/button\\:flex'), 'the build has no utility that only components/ declares').toBe(true)
  })
}
