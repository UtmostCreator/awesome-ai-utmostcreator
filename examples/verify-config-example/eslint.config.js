// Minimal flat-config ESLint setup for the copy-paste example.
//
// Scope: plain JavaScript only (`src/**/*.js`). TypeScript (`.ts`) and Vue
// SFC (`.vue`) syntax need a dedicated parser (`typescript-eslint` /
// `eslint-plugin-vue`) that this example intentionally does NOT add, to
// stay inside the exact devDependency list approved for this ticket
// (docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959 §8-P7).
// Those files are covered instead by:
//   - `tsc --noEmit` / `vue-tsc --noEmit` for type safety (already wired via
//     ai-verify-ts.sh / ai-verify-vue.sh).
//   - `biome check` for style/lint (Biome fully supports TypeScript and has
//     experimental Vue SFC support — see biome.json).
// The global-ignores object below (an object with ONLY an `ignores` key)
// tells ESLint to skip `.ts`/`.vue` files everywhere, including when the
// per-language wrapper passes them as explicit CLI arguments, so the eslint
// step never throws a parse error on syntax it cannot understand.
export default [
  {
    ignores: ['**/*.ts', '**/*.vue', 'node_modules/**', 'vendor/**'],
  },
  {
    files: ['src/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
    },
    rules: {
      'no-unused-vars': 'error',
      'no-undef': 'error',
      eqeqeq: 'warn',
      'no-var': 'error',
    },
  },
];
