// Trivial, clean sample file — passes `tsc --noEmit` and `biome check` with
// default config. Not linted by ESLint here (see eslint.config.js comment).
export function greet(name: string): string {
  return `Hello, ${name}!`;
}
