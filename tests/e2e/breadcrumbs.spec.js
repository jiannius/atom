import { test, expect } from '@playwright/test'
import breadcrumbs from '../../resources/js/alpinejs/breadcrumbs.js'

// The trail is built entirely from the `_breadcrumbs` payload of the Livewire
// component in [data-atom-main], so the merging logic is driven here with
// stubbed globals rather than a real SPA navigation — atom's docs rig has no
// multi-page Livewire hierarchy to navigate through, and the bug this covers
// only appears across client-side navigations.
//
// SCOPE: merging only. The querySelector stub below answers every selector, so
// nothing here can see how build() finds the Livewire root — that is how the
// v3.18.0 breakage (a page-title <h1> taking the root's place as first child of
// [data-atom-main]) reached three releases. Root resolution is covered against a
// real DOM in breadcrumbs-dom.spec.js; keep it there.

// Navigate the trail to a page, returning the crumb titles it renders.
function navigate (trail, page) {
  globalThis.document = { body: { querySelector: () => ({ getAttribute: () => 'wire-1' }) } }
  globalThis.Livewire = { find: () => ({ _breadcrumbs: page }) }

  trail.build()

  return trail.breadcrumbs.map(item => item.title)
}

const dashboard = {
  home: { title: 'Dashboard', url: '/app' },
  items: [],
  replace: false,
}

const customers = {
  home: { title: 'Dashboard', url: '/app' },
  items: [{ title: 'Customers', url: '/app/customers' }],
  replace: false,
}

const customer = {
  home: { title: 'Dashboard', url: '/app' },
  items: [{ title: 'Customer #5', url: '/app/customers/5' }],
  replace: false,
}

const settings = {
  home: { title: 'Settings', url: '/app/settings' },
  items: [],
  replace: false,
}

const profile = {
  home: { title: 'Settings', url: '/app/settings' },
  items: [{ title: 'Profile', url: '/app/settings/profile' }],
  replace: true,
}

const password = {
  home: { title: 'Settings', url: '/app/settings' },
  items: [{ title: 'Password', url: '/app/settings/password' }],
  replace: true,
}

test('a full page load of a replace() page renders its declared trail', () => {
  const trail = breadcrumbs({})

  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
})

test('replace() keeps the trail root when entering the hierarchy from outside', () => {
  const trail = breadcrumbs({})

  expect(navigate(trail, dashboard)).toEqual(['Dashboard'])
  // the previous page is its own trail root — overwriting it leaves no home
  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
  expect(navigate(trail, password)).toEqual(['Settings', 'Password'])
})

test('replace() keeps the trail root when arriving from its own index page', () => {
  const trail = breadcrumbs({})

  expect(navigate(trail, settings)).toEqual(['Settings'])
  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
})

test('replace() reseeds instead of grafting onto another hierarchy', () => {
  const trail = breadcrumbs({})

  navigate(trail, dashboard)
  navigate(trail, customers)
  expect(navigate(trail, customer)).toEqual(['Dashboard', 'Customers', 'Customer #5'])

  // the trail ends deep inside another hierarchy, so its last crumb is not the
  // sibling `replace` stands in for
  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
})

test('replace() swaps siblings within the hierarchy', () => {
  const trail = breadcrumbs({})

  navigate(trail, profile)
  expect(navigate(trail, password)).toEqual(['Settings', 'Password'])
  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
})

test('replace() truncates back to a page already in the trail', () => {
  const trail = breadcrumbs({})
  const sessions = {
    home: { title: 'Settings', url: '/app/settings' },
    items: [{ title: 'Sessions', url: '/app/settings/profile/sessions' }],
    replace: false,
  }

  navigate(trail, profile)
  expect(navigate(trail, sessions)).toEqual(['Settings', 'Profile', 'Sessions'])
  expect(navigate(trail, profile)).toEqual(['Settings', 'Profile'])
})

test('pushed crumbs still accumulate across navigations', () => {
  const trail = breadcrumbs({})

  navigate(trail, dashboard)
  navigate(trail, customers)

  const titles = navigate(trail, customer)

  expect(titles).toEqual(['Dashboard', 'Customers', 'Customer #5'])
  expect(trail.breadcrumbs.map(item => item.href)).toEqual(['/app', '/app/customers', '/app/customers/5'])
})

// $_breadcrumbs defaults to [] on the AtomComponent trait, so a page that renders
// <atom:breadcrumbs> over a component with no breadcrumbs() method reaches build()
// with an empty payload. Before the root selector was fixed, build() bailed earlier
// on such pages and never got here.
test('build does nothing when the component declares no breadcrumbs', () => {
  globalThis.document = { body: { querySelector: () => ({ getAttribute: () => 'wire-1' }) } }
  globalThis.Livewire = { find: () => ({ _breadcrumbs: [] }) }

  const trail = breadcrumbs({})

  expect(() => trail.build()).not.toThrow()
  expect(trail.breadcrumbs).toEqual([])
})

// getLatestHref is bound to x-on:livewire:navigate.window, so it runs on every
// client-side navigation whether or not a trail was ever built. An empty trail is
// a legitimate state — first paint, or a page whose component has no breadcrumbs()
// method — and throwing from a public handler takes out whatever Livewire's
// navigate lifecycle was going to run after it.
test('getLatestHref does nothing when the trail is empty', () => {
  globalThis.window = { location: { href: 'https://example.test/app' } }

  const trail = breadcrumbs({})

  expect(() => trail.getLatestHref({})).not.toThrow()
})

test('getLatestHref still records the resolved href when a trail exists', () => {
  globalThis.window = { location: { href: 'https://example.test/app?tab=open' } }

  const trail = breadcrumbs({})
  trail.trails = [{ title: 'Dashboard', url: 'https://example.test/app' }]

  trail.getLatestHref({})

  expect(trail.trails[0].href).toBe('https://example.test/app?tab=open')
})
