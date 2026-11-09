import { defineConfig, devices } from '@playwright/test'

// The calendar's engine-dependent specs run again in WebKit and in Firefox, for what the engine decides and Chromium does not: the pointer events that
// moving, resizing and selecting are written on (a touch, in iOS, is WebKit's), and where a title breaks between words (Safari broke what Chromium did not).
// `<browser>-a11y` is the keyboard and accessibility spec; `-select` and `-move` are the gestures (WebKit's `-move` has the phone's touch spec too:
// a touch on an iPhone is WebKit's, and Firefox has no mobile emulation to run it in); `-look` is only the look spec's two word-boundary tests,
// not the rest of it: its contrast sweep and its container-rule test fail in WebKit for reasons of the harness, with or without a change to the calendar,
// and Firefox runs the same two and no more, until a run says the rest holds there.
//
// Each depends on Chromium, so a local run of one is after Chromium, not beside it: the served app is PHP's one-request-at-a-time server, and a page load
// that waits behind 700 tests times out. CI runs them in a job of their own, with `--no-deps` (see .github/workflows/ci.yml), since Chromium has its shards there.
//
// Firefox is in the config only on CI, or with `PW_FIREFOX=1`: Playwright's Firefox does not launch on a Mac on macOS 27 (a run of it hangs, then fails
// every test), so a plain `npm run test:e2e` leaves it out and runs Chromium and WebKit.
const engines = [
  { browser: 'webkit', device: 'Desktop Safari' },
  ...(process.env.CI || process.env.PW_FIREFOX ? [{ browser: 'firefox', device: 'Desktop Firefox' }] : []),
]

const calendarProjects = engines.flatMap(({ browser, device }) => [
  { name: `${browser}-a11y`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /calendar-a11y\.spec\.js$/ },
  // the selection runs on pointer events by hand
  { name: `${browser}-select`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /calendar-select\.spec\.js$/ },
  // moving and resizing run on the same pointer events
  { name: `${browser}-move`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: browser === 'webkit' ? /calendar-(move|touch)\.spec\.js$/ : /calendar-move\.spec\.js$/ },
  {
    name: `${browser}-look`,
    dependencies: ['chromium'],
    use: { ...devices[device] },
    testMatch: /calendar-look\.spec\.js$/,
    grep: /a narrow block breaks its title between words|the title of a narrow block is clamped/,
  },
  // The disabled-state rig (tests/e2e/disabled-behaviour.spec.js and disabled-morph.spec.js), for what an engine decides: a click on a child of a disabled
  // button (it still reaches the child's own listener), the controls Tab reaches (Safari's Tab skips buttons, so the rig walks with Alt+Tab there), a file
  // chooser, a drop, a selection. CI runs them in a job of their own (`e2e-disabled`), and Firefox is in it too: its disabled-button children were never probed.
  { name: `${browser}-disabled`, dependencies: ['chromium'], use: { ...devices[device] }, testMatch: /disabled-(behaviour|morph)\.spec\.js$/ },
])

// The shells' keyboard and focus specs run again in WebKit, for what Safari does and Chromium does not: it does not focus a button on click
// (so an Escape heard only inside the header was dead after a click on More), its Tab skips links (Alt+Tab visits every control), and its
// `inert` and resize events are its own. `modal-escape` is here for `closedby`, which WebKit does not have (its `cancel` stays cancelable
// on every press, where Chromium stops allowing a prevented one after two), and for WebKit closing a popover and its dialog on one Escape (#177; `modal-escape-layers`
// too); `command-escape` is the palette's native-close path (#176). WebKit only: Firefox has no project here. After Chromium, as the calendar's, and `--no-deps` on CI.
const shellProject = {
  name: 'webkit-shell',
  dependencies: ['chromium'],
  use: { ...devices['Desktop Safari'] },
  testMatch: /(shells|shell-escape|shell-escape-focus|shell-drawer|shell-menu-panel|modal-escape|modal-escape-layers|command-escape)\.spec\.js$/,
}

export default defineConfig({
  testDir: './tests/e2e',
  // trace on the retry, so a failure that only happens on CI leaves its trace in test-results/ (the workflow uploads that folder)
  use: { baseURL: 'http://127.0.0.1:8000', ...(process.env.CI ? { trace: 'on-first-retry' } : {}) },
  // The served app is PHP's one-request-at-a-time server: on CI two workers queue behind each other and a drag
  // or a page load can run out of time. One worker there, and one retry for what still flakes.
  ...(process.env.CI ? { workers: 1, retries: 1 } : {}),
  projects: [{ name: 'chromium' }, ...calendarProjects, shellProject],
  webServer: {
    command: 'vendor/bin/testbench serve',
    url: 'http://127.0.0.1:8000/atom/docs',
    reuseExistingServer: true,
    timeout: 60000,
  },
})
