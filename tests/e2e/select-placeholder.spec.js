import { test, expect } from '@playwright/test'

// Drives /atom/e2e/select-placeholder (tests/Fixtures/SelectPlaceholderFixture.php).
//
// #265: picking a real option and then the placeholder ("Please select...", value "") in a native select wrote '' to the
// bound value, where the x clear button and the listbox write null. Livewire does not convert '' to null for its own
// requests, so '' reached PHP and an integer foreign key failed on it. The server values are printed as JSON, so null
// and "" are told apart.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const select = (page, name) => probe(page, name).locator('select')
const server = (page, name) => page.locator(`[data-server="${name}"]`)
const sync = async page => {
  await page.locator('[data-refresh]').click()
  await page.waitForTimeout(0)
}

test('a plain wire:model gets null from the placeholder, not an empty string', async ({ page }) => {
  await page.goto('/atom/e2e/select-placeholder')

  await select(page, 'plain').selectOption('30')
  await sync(page)
  await expect(server(page, 'plain')).toHaveText('"30"')

  await select(page, 'plain').selectOption('')
  await sync(page)
  await expect(server(page, 'plain')).toHaveText('null')
})

test('wire:model.live gets null from the placeholder, not an empty string', async ({ page }) => {
  await page.goto('/atom/e2e/select-placeholder')

  await select(page, 'live').selectOption('31')
  await expect(server(page, 'live')).toHaveText('"31"')

  await select(page, 'live').selectOption('')
  await expect(server(page, 'live')).toHaveText('null')
  // and the clear button agrees
  await select(page, 'live').selectOption('30')
  await expect(server(page, 'live')).toHaveText('"30"')
  await probe(page, 'live').locator('[data-atom-select-clear]').click()
  await expect(server(page, 'live')).toHaveText('null')
})

test('an x-model gets null from the placeholder', async ({ page }) => {
  await page.goto('/atom/e2e/select-placeholder')
  const model = page.locator('[data-alpine-model]')

  await select(page, 'alpine').selectOption('30')
  await expect(model).toHaveText('"30"')

  await select(page, 'alpine').selectOption('')
  await expect(model).toHaveText('null')
})

test('a multiple select ignores the placeholder', async ({ page }) => {
  await page.goto('/atom/e2e/select-placeholder')

  await select(page, 'multi').selectOption('30')
  await expect(server(page, 'multi')).toHaveText('["30"]')

  await select(page, 'multi').selectOption('')
  await select(page, 'multi').selectOption('31')
  await expect(server(page, 'multi')).toHaveText('["30","31"]')
})

test("a host's own value=\"\" option still writes an empty string (the \"All\" of a non-null default)", async ({ page }) => {
  await page.goto('/atom/e2e/select-placeholder')
  await expect(server(page, 'region')).toHaveText('"north"')

  await select(page, 'region').selectOption('')
  await expect(server(page, 'region')).toHaveText('""')

  await select(page, 'region').selectOption('south')
  await expect(server(page, 'region')).toHaveText('"south"')
})
