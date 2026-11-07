import { defineConfig, devices } from '@playwright/test';

// The port of the e2e dev server. A second checkout (a git worktree) runs with its own
// `E2E_PORT`: with the default, `reuseExistingServer` would hand the tests the OTHER checkout's
// server on 5173 (e2e/RULES.md).
const port = Number(process.env.E2E_PORT ?? 5173);

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  reporter: 'html',

  use: {
    baseURL: process.env.E2E_BASE_URL ?? `http://localhost:${port}`,
    trace: 'on-first-retry',
    // Route-intercept all API calls — tests never need a real backend.
    extraHTTPHeaders: { Accept: 'application/json' },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Run Vite in --mode test so isLocal=false → no SSL cert reads.
  // Set E2E_NO_WEBSERVER=1 to skip (e.g. server already running).
  webServer: process.env.E2E_NO_WEBSERVER
    ? undefined
    : {
        command: `npx vite --mode test --port ${port} --strictPort`,
        url: `http://localhost:${port}`,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
