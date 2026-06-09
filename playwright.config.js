import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  use: { baseURL: 'http://127.0.0.1:8000' },
  webServer: {
    command: 'vendor/bin/testbench serve',
    url: 'http://127.0.0.1:8000/atom/docs',
    reuseExistingServer: true,
    timeout: 60000,
  },
})
