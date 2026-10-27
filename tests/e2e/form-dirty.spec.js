import { test, expect } from '@playwright/test'

// <atom:form.actions bar dirty>: a muted "Unsaved changes" after the start slot, shown only while the form holds changes that were not
// saved. tests/Fixtures/FormDirtyFixture.php is a Livewire form (a text field, a `.live` field, an atom select and an atom date picker; a
// save that fails validation while the name is empty); ?mode=plain is the same bar on a form with no Livewire. On the consumer's
// Tailwind, at the hosts' minimum (floor) and the newest 4.x (latest).

const open = async (page, query = '', tw) => {
  const css = await page.request.get(`/atom/e2e/consumer-css/${tw}`)
  expect(css.status(), 'run `node tests/consumer/build.mjs` first').toBe(200)

  await page.goto(`/atom/e2e/form-dirty?${query ? query + '&' : ''}tw=${tw}`)
  await page.waitForFunction(() => window.Alpine !== undefined && document.querySelector("[data-atom-save-bar]") !== null)
  await page.evaluate(() => document.fonts.ready)
}

const label = (page) => page.locator('[data-atom-form-dirty]')
const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const listbox = (page, name) => page.locator(`[data-select="${name}"]`)
const save = (page) => page.locator('[data-submit="main"]')

// a Livewire round trip is done when the request has come back and been rendered
const roundTrip = async (page, action) => {
  const response = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST')
  await action()
  await response
  await page.evaluate(() => new Promise((done) => setTimeout(done, 50)))
}

for (const tw of ['floor', 'latest']) {
  test.describe(`the unsaved changes label, ${tw} consumer build`, () => {
    test.use({ viewport: { width: 1280, height: 700 } })

    test.describe('a Livewire form', () => {
      test('is hidden at load, shows after typing in a wire:model input, and hides when the save round trip lands', async ({ page }) => {
        await open(page, '', tw)

        await expect(label(page)).toHaveCount(1)
        await expect(label(page)).toBeHidden()
        await expect(label(page)).toHaveText('Unsaved changes')
        await expect(page.locator('[data-atom-form-actions-start]')).toContainText('Total')

        await probe(page, 'name').fill('Acme Ltd')
        await expect(label(page)).toBeVisible()

        await roundTrip(page, () => save(page).click())
        await expect(page.locator('[data-saves]')).toHaveText('1')
        await expect(label(page)).toBeHidden()

        // and it comes back with the next change
        await probe(page, 'name').fill('Acme Ltd 2')
        await expect(label(page)).toBeVisible()
      })

      test('a save that fails validation leaves it showing', async ({ page }) => {
        await open(page, '', tw)

        await probe(page, 'name').fill('')
        await expect(label(page)).toBeVisible()

        await roundTrip(page, () => save(page).click())
        await expect(page.locator('[data-saves]')).toHaveText('0')
        await expect(label(page)).toBeVisible()

        // fixing it and saving clears it
        await probe(page, 'name').fill('Fixed')
        await roundTrip(page, () => save(page).click())
        await expect(page.locator('[data-saves]')).toHaveText('1')
        await expect(label(page)).toBeHidden()
      })

      test('a wire:model.live field is not "saved" by the server having it: it shows until the form is saved', async ({ page }) => {
        await open(page, '', tw)

        await roundTrip(page, () => probe(page, 'note').fill('hello'))
        await expect(label(page)).toBeVisible()

        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()
      })

      test('an atom select marks it, native and listbox, and a save clears it', async ({ page }) => {
        await open(page, '', tw)

        await probe(page, 'zone').locator('select').selectOption('b')
        await expect(label(page)).toBeVisible()
        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()

        await listbox(page, 'region').locator('[data-atom-dropdown] > button').click()
        await listbox(page, 'region').locator('[data-atom-option]', { hasText: 'South' }).click()
        await expect(label(page)).toBeVisible()
        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()
      })

      test('an atom date picker marks it, typed or set without an event, and a save clears it', async ({ page }) => {
        await open(page, '', tw)

        const input = page.locator('[data-atom-date-picker] input[type=text]')

        await input.fill('15 Oct 2026')
        await input.press('Enter')
        await expect(label(page)).toBeVisible()

        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()

        // a value set on the component with no DOM event at all (what a control without one does): the form's own wire:model values are watched
        await page.evaluate(() => window.Livewire.first().$set('due', '2026-11-20', false))
        await expect(label(page)).toBeVisible()

        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()
      })

      test('a morph while the form is dirty keeps it', async ({ page }) => {
        await open(page, '', tw)

        await probe(page, 'name').fill('Changed')
        await expect(label(page)).toBeVisible()

        for (let n = 1; n <= 2; n++) {
          await roundTrip(page, () => page.locator('[data-touch]').click())
          await expect(page.locator('[data-touches]')).toHaveText(String(n))
          await expect(label(page), `round trip ${n}`).toBeVisible()
        }

        // and a clean form stays clean through one
        await roundTrip(page, () => save(page).click())
        await expect(label(page)).toBeHidden()
        await roundTrip(page, () => page.locator('[data-touch]').click())
        await expect(label(page)).toBeHidden()
      })

      test('without the prop there is never a label, and the bar is still there', async ({ page }) => {
        await open(page, 'dirty=0', tw)

        await probe(page, 'name').fill('Changed')
        await expect(label(page)).toHaveCount(0)
        await expect(page.locator('[data-atom-save-bar]')).toBeVisible()
        await expect(page.locator('[data-atom-form-actions-start]')).toContainText('Total')
      })
    })

    test.describe('a plain form', () => {
      test('shows on input and on a pick, hides on submit', async ({ page }) => {
        await open(page, 'mode=plain', tw)

        await expect(label(page)).toBeHidden()
        // the bar with no start slot: nothing of the group is drawn until there is something to say
        await expect(page.locator('[data-atom-form-actions-start]')).toBeHidden()

        await probe(page, 'name').fill('Acme Ltd')
        await expect(label(page)).toBeVisible()
        await expect(page.locator('[data-atom-form-actions-start]')).toBeVisible()

        await save(page).click()
        await expect(label(page)).toBeHidden()

        await listbox(page, 'region').locator('[data-atom-dropdown] > button').click()
        await listbox(page, 'region').locator('[data-atom-option]', { hasText: 'North' }).click()
        await expect(label(page)).toBeVisible()
      })

      test('without the prop there is no label and no group', async ({ page }) => {
        await open(page, 'mode=plain&dirty=0', tw)

        await probe(page, 'name').fill('Acme Ltd')
        await expect(label(page)).toHaveCount(0)
        await expect(page.locator('[data-atom-form-actions-start]')).toHaveCount(0)
      })
    })
  })
}
