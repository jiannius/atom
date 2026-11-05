import { defineConfig, devices } from '@playwright/test'

// The calendar's engine-dependent specs run again in WebKit and in Firefox, for what the engine decides and Chromium does not: the pointer events that
// moving, resizing and selecting are written on (a touch, in iOS, is WebKit's), and where a title breaks between words (Safari broke what Chromium did not).
// `<browser>-a11y` is the keyboard and accessibility spec; `-select` and `-move` are the gestures; `-look` is only the look spec's two word-boundary tests,
// not the rest of it: its contrast sweep and its container-rule test fail in WebKit for reasons of the harness, with or without a change to the calendar,
// and Firefox runs the same two and no more, until a run says the rest holds there.
//
// Each depends on Chromium, so a local run of one is after Chromium, not beside it: the served app is PHP's one-request-at-a-time server, and a page load
// that waits behind 700 tests times out. CI runs them in a job of their own, with `--no-deps` (see .github/workflows/ci.yml), since Chromium has its shards there.
const engines = [
  { browser: 'webkit', device: 'Desktop Safari' },
  { browser: 'firefox', device: 'Desktop Firefox' },
]

const calendarProjects = engines.flatMap(({ browser, device }) => [
  { name: `${browser}-a11y`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /calendar-a11y\.spec\.js$/ },
  // the selection runs on pointer events by hand
  { name: `${browser}-select`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /calendar-select\.spec\.js$/ },
  // moving and resizing run on the same pointer events
  { name: `${browser}-move`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /calendar-move\.spec\.js$/ },
  {
    name: `${browser}-look`,
    dependencies: ['chromium'],
    use: { ...devices[device] },
    testMatch: /calendar-look\.spec\.js$/,
    grep: /a narrow block breaks its title between words|the title of a narrow block is clamped/,
  },
])

export default defineConfig({
  testDir: './tests/e2e',
  // trace on the retry, so a failure that only happens on CI leaves its trace in test-results/ (the workflow uploads that folder)
  use: { baseURL: 'http://127.0.0.1:8000', ...(process.env.CI ? { trace: 'on-first-retry' } : {}) },
  // The served app is PHP's one-request-at-a-time server: on CI two workers queue behind each other and a drag
  // or a page load can run out of time. One worker there, and one retry for what still flakes.
  ...(process.env.CI ? { workers: 1, retries: 1 } : {}),
  projects: [{ name: 'chromium' }, ...calendarProjects],
  webServer: {
    command: 'vendor/bin/testbench serve',
    url: 'http://127.0.0.1:8000/atom/docs',
    reuseExistingServer: true,
    timeout: 60000,
  },
})
