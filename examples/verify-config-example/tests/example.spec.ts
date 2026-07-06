import { expect, test } from '@playwright/test';

// Trivial config-only sanity test. This repo does not run
// `playwright install` (browser binaries can be large/impractical in a
// sandboxed environment — see README "Playwright browsers" note), so this
// file exists mainly to prove `playwright test --list` resolves the config
// and discovers a test without needing a browser download.
test('trivial arithmetic sanity check', () => {
  expect(1 + 1).toBe(2);
});
