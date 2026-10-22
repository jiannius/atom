import { test, expect } from '@playwright/test'

// V4-4c: atom no longer extends Array, Number or String, and sets no window.dd / window.empty.
// The helpers live under window.atom with the receiver as the first argument.

test.beforeEach(async ({ page }) => {
  await page.goto('/atom/docs/button')
  await page.waitForFunction(() => window.atom && window.Alpine)
})

test('no prototype extension and no window.dd / window.empty is left', async ({ page }) => {
  const left = await page.evaluate(() => ({
    array: ['pluck', 'unique', 'sum', 'where', 'firstWhere', 'toggle'].filter(name => name in Array.prototype),
    number: ['currency'].filter(name => name in Number.prototype),
    string: ['toDateFormat', 'toDateString', 'toDatetimeString', 'toTimeString', 'fromNow', 'camel', 'slug', 'headline', 'limit', 'nl2br', 'striptags'].filter(name => name in String.prototype),
    dd: typeof window.dd,
    empty: typeof window.empty,
  }))

  expect(left).toEqual({ array: [], number: [], string: [], dd: 'undefined', empty: 'undefined' })
  expect(await page.evaluate(() => typeof [].pluck + typeof (1).currency + typeof ''.headline)).toBe('undefinedundefinedundefined')
})

test('every replacement exists on atom', async ({ page }) => {
  const shape = await page.evaluate(() => ({
    empty: typeof atom.empty,
    currency: typeof atom.currency,
    arr: Object.keys(atom.arr).sort(),
    str: Object.keys(atom.str).sort(),
  }))

  expect(shape.empty).toBe('function')
  expect(shape.currency).toBe('function')
  expect(shape.arr).toEqual(['firstWhere', 'pluck', 'sum', 'toggle', 'unique', 'where'])
  expect(shape.str).toEqual(['camel', 'headline', 'limit', 'nl2br', 'slug', 'striptags', 'toDateFormat', 'toDateString', 'toDatetimeString', 'toTimeString'])
})

test('atom.empty keeps the rules window.empty had', async ({ page }) => {
  const results = await page.evaluate(() => [
    undefined, null, '', '  ', [], {}, 0, false, 'a', [0], { a: 1 }, new Date(),
  ].map(value => atom.empty(value)))

  expect(results).toEqual([true, true, true, true, true, true, false, false, false, false, false, false])
})

test('atom.currency formats like Number.prototype.currency did', async ({ page }) => {
  const results = await page.evaluate(() => [
    atom.currency(1234.5),
    atom.currency(0.065),
    atom.currency(0.065, null, false, 6),
    atom.currency(10.185, null, false, 2),
    atom.currency(10.12, 'MYR', true, 6),
    atom.currency('12.5', 'RM'),
  ])

  expect(results).toEqual(['1,234.50', '0.065', '0.065', '10.19', 'MYR 10.10', 'RM 12.50'])
})

test('atom.arr keeps the behaviour the Array helpers had', async ({ page }) => {
  const rows = await page.evaluate(() => {
    const data = [{ id: 1, kind: 'a', n: '2' }, { id: 2, kind: 'b', n: 3 }, { id: 3, kind: 'a', n: 4 }, { id: 1, kind: 'a', n: '2' }]
    const list = [1, 2, 3]
    const toggled = atom.arr.toggle(list, 2)
    const toggledBack = atom.arr.toggle(list, 5)

    return {
      pluck: atom.arr.pluck(data, 'id'),
      uniquePlain: atom.arr.unique([1, 1, 2, 3, 3]),
      uniqueAttr: atom.arr.unique(data, 'id').map(row => row.id),
      uniqueFn: atom.arr.unique(data, row => row.kind).map(row => row.id),
      sum: atom.arr.sum([1, '2', 3]),
      sumAttr: atom.arr.sum(data, 'n'),
      sumFn: atom.arr.sum(data, row => row.id * 2),
      where: atom.arr.where(data, 'kind', 'b').map(row => row.id),
      whereStrict: atom.arr.where(data, 'id', '2').length,
      firstWhere: atom.arr.firstWhere(data, 'kind', 'a').id,
      firstWhereMiss: atom.arr.firstWhere(data, 'kind', 'z') ?? null,
      toggleSameArray: toggled === list && toggledBack === list,
      toggled: [...list],
      notArray: [atom.arr.pluck(null, 'x'), atom.arr.sum('abc'), atom.arr.toggle(undefined, 1) ?? null],
    }
  })

  expect(rows).toEqual({
    pluck: [1, 2, 3, 1],
    uniquePlain: [1, 2, 3],
    uniqueAttr: [1, 2, 3],
    uniqueFn: [1, 2],
    sum: 6,
    sumAttr: 11,
    sumFn: 14,
    where: [2],
    whereStrict: 0,
    firstWhere: 1,
    firstWhereMiss: null,
    toggleSameArray: true,
    toggled: [1, 3, 5],
    notArray: [null, 'abc', null],
  })
})

test('atom.str keeps the behaviour the String helpers had', async ({ page }) => {
  const out = await page.evaluate(() => ({
    headline: [atom.str.headline('order_status'), atom.str.headline('in-progress ITEM')],
    camel: [atom.str.camel('order_status'), atom.str.camel('Hello World-foo.bar')],
    slug: [atom.str.slug('Crème Brûlée & Co.'), atom.str.slug('  A  B_c  ')],
    limit: [atom.str.limit('abcdef', 3), atom.str.limit('abc', 3), atom.str.limit('', 3)],
    nl2br: [atom.str.nl2br('a\nb\r\nc'), atom.str.nl2br('a\nb', false), atom.str.nl2br(null), atom.str.nl2br(undefined)],
    striptags: [atom.str.striptags('<p>Hi <b>there</b></p>'), atom.str.striptags('no tags')],
    date: [
      atom.str.toDateString('2024-10-01 09:30:00'),
      atom.str.toDatetimeString('2024-10-01 21:05:00'),
      atom.str.toTimeString('2024-10-01 21:05:00'),
      atom.str.toDateFormat('2024-10-01 09:30:00', 'YYYY/MM/DD'),
      atom.str.toDateString('not a date') ?? null,
    ],
  }))

  expect(out).toEqual({
    headline: ['Order Status', 'In Progress Item'],
    camel: ['orderStatus', 'helloWorldFooBar'],
    slug: ['creme-brulee-and-co', 'a-b-c'],
    limit: ['abc...', 'abc', ''],
    nl2br: ['a<br />\nb<br />\r\nc', 'a<br>\nb', '', ''],
    striptags: ['Hi there', 'no tags'],
    date: ['01 Oct 2024', '01 Oct 2024 9:05 PM', '9:05 PM', '2024/10/01', null],
  })
})

test('null and undefined no longer throw where a method call did (UPGRADE.md lists these)', async ({ page }) => {
  const out = await page.evaluate(() => {
    const throws = (fn) => { try { fn(); return false } catch (e) { return e instanceof TypeError } }

    return {
      dateNull: [atom.str.toDateString(null) ?? null, atom.str.toDateFormat(null, 'YYYY') ?? null],
      dateUndefinedIsNow: atom.str.toDateFormat(undefined, 'YYYY') === String(new Date().getFullYear()),
      nl2br: [atom.str.nl2br(null), atom.str.nl2br(undefined)],
      stillThrow: ['camel', 'slug', 'headline', 'striptags'].map(name => [throws(() => atom.str[name](null)), throws(() => atom.str[name](undefined))]).flat().concat(throws(() => atom.str.limit(null, 3))),
      currency: [atom.currency(null), atom.currency(undefined)],
    }
  })

  expect(out).toEqual({
    dateNull: [null, null],
    dateUndefinedIsNow: true,
    nl2br: ['', ''],
    stillThrow: [true, true, true, true, true, true, true, true, true],
    currency: ['0.00', 'NaN'],
  })
})
