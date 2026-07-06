import { defineConfig } from '@playwright/test';

// Minimal example config. Browser binaries are NOT installed by this example
// (see README "Playwright browsers" note) — `playwright test --list` proves
// the config loads without requiring a browser download.
export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  reporter: 'list',
});
