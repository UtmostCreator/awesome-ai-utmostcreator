# verify-config-example

A copy-paste starting point for `scripts/ai/ai-verify-{php,js,ts,vue,html}.sh`.

This folder is **isolated** from the kit repo's own root: it has its own `composer.json`,
`package.json`, and lockfiles, installed only inside this directory (`vendor/`,
`node_modules/` — both gitignored here). It is config + tiny sample source, not a real app.

## How to use this in YOUR project

1. Copy the **config files** below into your project root (not `vendor/`/`node_modules/`):
   `phpstan.neon`, `psalm.xml`, `rector.php`, `deptrac.yaml`, `eslint.config.js`,
   `biome.json`, `vitest.config.ts`, `playwright.config.ts`, `tsconfig.json`.
2. Add the same dev tools to your own project:
   `composer require --dev laravel/pint phpstan/phpstan vimeo/psalm rector/rector deptrac/deptrac maglnet/composer-require-checker icanhazstring/composer-unused`
   `pnpm add -D eslint @biomejs/biome typescript vue-tsc vitest @playwright/test htmlhint`
3. Run a per-language wrapper from your project root:
   `bash scripts/ai/ai-verify-php.sh .`
   `bash scripts/ai/ai-verify-js.sh .`
   `bash scripts/ai/ai-verify-ts.sh .`
   `bash scripts/ai/ai-verify-vue.sh .`
   `bash scripts/ai/ai-verify-html.sh .`
   (equivalently: `scripts/ai/ai-verify.sh --language <lang> .`)

**These wrappers are MANUAL-ONLY.** No agent, permission layer, capability, command,
skill, or hook ever invokes them automatically — a human runs them by hand.

## Corrections made to the plan's tool list (verified against Packagist/npm, 2026-07-06)

- `qossmic/deptrac` is stale (last release 2.0.4, Nov 2024). The actively maintained
  package is **`deptrac/deptrac`** (4.6.2, its own README recommends this name) — used here.
- `vue` (^3.5) was added as a plain (non-dev) dependency, not part of the originally
  requested devDependency list. `vue-tsc` cannot type-check `src/GreetingCard.vue`
  (which imports `computed` from `vue`) without the `vue` package itself resolvable.

## Known limitations (honestly reported)

- **ESLint scope:** `eslint.config.js` only lints plain `src/**/*.js`. Linting `.ts`/`.vue`
  syntax would need `typescript-eslint` / `eslint-plugin-vue`, which are intentionally NOT
  added here (outside the approved dependency list). Type safety for `.ts`/`.vue` is
  covered by `tsc --noEmit` / `vue-tsc --noEmit`; style linting for those files is covered
  by `biome check` (Biome fully supports TypeScript and has experimental Vue SFC support).
  `.ts`/`.vue` files are explicitly globally `ignores`-listed in `eslint.config.js` so the
  wrapper's eslint step never throws a parse error — expect benign
  "File ignored because of a matching ignore pattern" warnings when eslint is run directly
  against `.ts`/`.vue` paths; these are warnings, not errors, and never fail the check.
- **Nuxt not included:** `nuxi typecheck` only fires when `nuxt`/`nuxi` is a dependency.
  This example intentionally omits it to keep `pnpm install` fast in a sandbox — if your
  project is a Nuxt app, add `nuxt` as a dependency and `nuxi typecheck` activates
  automatically (see `53-language-dispatch.sh`).
- **Playwright browsers not installed:** this example never ran `playwright install`
  (browser binaries can be large/slow to fetch in a sandboxed environment). The included
  `tests/example.spec.ts` is a plain assertion with no `page`/browser fixture, so
  `pnpm exec playwright test` and `pnpm exec playwright test --list` both work without a
  browser download — a project needing real browser automation must run
  `pnpm exec playwright install` itself.
- **`biome.json`'s `linter.rules.recommended`** field prints a non-fatal "deprecated,
  use `preset` instead" info notice on this Biome version; `preset` expects a string
  (e.g. `"recommended"`) rather than the documented boolean shorthand as of 2.5.2, so this
  file keeps the working `recommended: true` form rather than a config that errors out.

## Full-gate tools (`VERIFY_FULL=1`, not part of the default per-language run)

`deptrac analyse`, `composer-require-checker`, `composer-unused` (advisory-only),
`playwright test`, and `vitest run` (full, unscoped) only run when `VERIFY_FULL=1` is set
on the full `ai-verify.sh` pipeline (no `--language` flag) — never in the default
per-language wrapper invocation. See `docs/ai/verification-matrix.md`.
