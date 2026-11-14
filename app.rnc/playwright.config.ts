import { defineConfig, devices } from '@playwright/test';

// CI runs on its fixed port. Locally the port is this checkout's own (`make e2e` reads it from
// `scripts/worktree.py port`), so two checkouts' suites never share — or reuse — a server.
const port = process.env.CI ? 5173 : Number(process.env.E2E_PORT);

if (!Number.isInteger(port) || port <= 0) {
  throw new Error("E2E_PORT is not set: run the suite with `make e2e` (it picks this checkout's port)");
}

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  // Locally a test that passed only on its retry fails the run: a flake is a bug, not a pass.
  failOnFlakyTests: !process.env.CI,
  workers: 1,
  // `open: 'never'`: the html reporter otherwise serves the report after a failure and the
  // process never exits. Browse it with `npx playwright show-report`.
  reporter: [['line'], ['html', { open: 'never' }]],

  use: {
    // rnc-4 D31: Franco's host, so the suite runs as a firm tenant and every call carries
    // `X-Firm: franco`. A product-site spec (the platform's root) navigates to
    // `http://localhost:<port>` itself. Chromium resolves `*.localhost` to loopback.
    baseURL: `http://franco.localhost:${port}`,
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

  // Run Vite in --mode test so isLocal=false → no SSL cert reads. Never reuse a running server:
  // whatever already answers on the port is not this checkout's app, so a taken port fails.
  webServer: {
    command: `npx vite --mode test --port ${port} --strictPort`,
    url: `http://localhost:${port}`,
    reuseExistingServer: false,
    timeout: 120_000,
  },
});
