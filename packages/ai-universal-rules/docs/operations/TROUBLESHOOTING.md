# Troubleshooting

## Common Failure Modes

- unresolved placeholders
- duplicated rules across policy and capabilities
- prompt files expected on unsupported surfaces
- nested instruction conflicts in monorepos
- hooks described but not actually enabled
- stale sessions with context pollution
- environment/setup errors mistaken for model errors

## First Checks

1. confirm repo root and working directory
2. confirm runtime and surface
3. inspect what actually loaded
4. check placeholder leaks
5. rerun the task in a fresh narrow session if drift is obvious

## See Also

- `../workflows/RUNTIME-OBSERVABILITY.md` — runtime inspection as a first-class workflow step
- `../../PLACEHOLDERS.md` — placeholder reference to check for leaks
- `../foundations/COMPATIBILITY.md` — surface differences that cause unexpected behavior
- `EVAL-SCENARIOS.md` — scenarios for testing after fixing failure modes
