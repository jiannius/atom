import { test, expect } from '@playwright/test'

// Drives the Livewire fixture at /atom/e2e/select-sibling-sync
// (tests/Fixtures/SelectSiblingSyncFixture.php), modelled on humblebear's
// bank-statement import (jiannius/humblebear#378).
//
// The report: picking the Date Column makes the server re-guess the Date Format,
// the `.live` response carries the new value — and the Date Format select kept
// SHOWING the old one, so what the user saw and what Import saved disagreed.
//
// This did NOT reproduce against atom here (every flow below passes on main); it
// stays as a guard on the contract the consumer relies on: a select whose value
// the server changes from a SIBLING select's updated* hook shows that value.
// Assertions read what the user sees — the native <select>'s chosen option and
// the listbox trigger's text — not just $wire.

const probe = (page, name) => page.locator(`[data-probe="${name}"]`)
const nativeSelect = (page, name) => probe(page, name).locator('select')
const listboxTrigger = (page, name) => probe(page, name).locator('[data-atom-dropdown] > button')
const server = page => page.locator('[data-server]')

// what the native select is showing, and what $wire holds, side by side
const formatState = page => page.evaluate(() => {
  const root = document.querySelector('[data-probe="format"]')
  const select = root.querySelector('select')
  const wire = Livewire.find(root.closest('[wire\\:id]').getAttribute('wire:id'))

  return {
    shown: select.value,
    label: select.selectedOptions[0]?.textContent.trim(),
    wire: wire.mapping.date_format,
  }
})

async function openAndUpload (page, url = '/atom/e2e/select-sibling-sync') {
  await page.goto(url)
  await page.locator('[data-open]').click()
  await nativeSelect(page, 'account').selectOption('acc-1')
  await page.locator('[data-load]').click()
  await expect(probe(page, 'format')).toBeAttached()
}

async function pickColumn (page, column) {
  await nativeSelect(page, 'date').selectOption(column)
  await expect(server(page)).toContainText(`"date":"${column}"`)
}

test('the native select shows a value the server set from a sibling select', async ({ page }) => {
  await openAndUpload(page)

  // column 0 holds US dates, so the upload step guessed month-first
  await expect.poll(() => formatState(page)).toEqual({ shown: 'MDY', label: 'MM/DD/YYYY', wire: 'MDY' })

  // an ambiguous column: the server re-guesses day-first, the user touched nothing
  await pickColumn(page, '1')
  await expect(server(page)).toContainText('"date_format":"DMY"')
  await expect.poll(() => formatState(page)).toEqual({ shown: 'DMY', label: 'DD/MM/YYYY', wire: 'DMY' })

  // and back
  await pickColumn(page, '0')
  await expect(server(page)).toContainText('"date_format":"MDY"')
  await expect.poll(() => formatState(page)).toEqual({ shown: 'MDY', label: 'MM/DD/YYYY', wire: 'MDY' })
})

test('the listbox shows a value the server set from a sibling select', async ({ page }) => {
  await openAndUpload(page)
  await expect(listboxTrigger(page, 'format-listbox')).toContainText('MM/DD/YYYY')

  await pickColumn(page, '1')
  await expect(server(page)).toContainText('"listboxFormat":"DMY"')
  await expect(listboxTrigger(page, 'format-listbox')).toContainText('DD/MM/YYYY')
})

test('a server override replaces a deferred pick sent in the same request', async ({ page }) => {
  // the consumer's earlier shape: Date Format on a plain wire:model, so the user's
  // pick is only sent with the next .live request — which the server then overrides
  await openAndUpload(page, '/atom/e2e/select-sibling-sync?deferred=1')

  await nativeSelect(page, 'format').selectOption('YMD')
  await expect.poll(() => formatState(page)).toEqual({ shown: 'YMD', label: 'YYYY-MM-DD', wire: 'YMD' })

  await pickColumn(page, '1')
  await expect(server(page)).toContainText('"date_format":"DMY"')
  await expect.poll(() => formatState(page)).toEqual({ shown: 'DMY', label: 'DD/MM/YYYY', wire: 'DMY' })
})

test('the value follows the server after the form is torn down and inserted again', async ({ page }) => {
  await openAndUpload(page)

  await page.locator('[data-cancel]').click()
  await expect(probe(page, 'account')).toBeAttached()
  await page.locator('[data-load]').click()
  await expect(probe(page, 'format')).toBeAttached()

  await pickColumn(page, '1')
  await expect.poll(() => formatState(page)).toEqual({ shown: 'DMY', label: 'DD/MM/YYYY', wire: 'DMY' })
})
