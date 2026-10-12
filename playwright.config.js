import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  use: { baseURL: 'http://127.0.0.1:8000' },
  // Chromium runs everything. WebKit runs only the calendar's keyboard and accessibility spec and the look spec's word-boundary
  // tests (where a title breaks is the engine's own: Safari broke what Chromium did not), not the rest of the look spec: its contrast
  // sweep and its container-rule test fail there for reasons of the harness, with or without a change to the calendar.
  projects: [
    { name: 'chromium' },
    {
      name: 'webkit-a11y',
      // after Chromium, not beside it: the served app is PHP's one-request-at-a-time server, and a page load that waits behind 700 tests times out
      dependencies: ['chromium'],
      use: { ...devices['Desktop Safari'] },
      testMatch: /calendar-a11y\.spec\.js$/,
    },
    {
      // the selection runs on pointer events by hand, and a touch (iOS) is WebKit's
      name: 'webkit-select',
      dependencies: ['chromium'],
      use: { ...devices['Desktop Safari'] },
      testMatch: /calendar-select\.spec\.js$/,
    },
    {
      // moving and resizing run on the same pointer events, and a touch (iOS) is WebKit's
      name: 'webkit-move',
      dependencies: ['chromium'],
      use: { ...devices['Desktop Safari'] },
      testMatch: /calendar-move\.spec\.js$/,
    },
    {
      name: 'webkit-look',
      dependencies: ['chromium'],
      use: { ...devices['Desktop Safari'] },
      testMatch: /calendar-look\.spec\.js$/,
      grep: /a narrow block breaks its title between words|the title of a narrow block is clamped/,
    },
  ],
  webServer: {
    command: 'vendor/bin/testbench serve',
    url: 'http://127.0.0.1:8000/atom/docs',
    reuseExistingServer: true,
    timeout: 60000,
  },
})
