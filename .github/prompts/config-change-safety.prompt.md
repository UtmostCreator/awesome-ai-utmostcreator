---
name: config-change-safety
description: Use when changing editor, shell, runtime, or tool configuration and you must preserve current behavior with the closest verification.
argument-hint: 'Describe the config change and the runtime or tool it affects'
---

## What I Do

I change editor, shell, runtime, or tool configuration with the smallest safe edit and prove it with the closest available verification, preserving current behavior. Config changes fail quietly — a mis-parsed key or a dropped default breaks a runtime with no obvious error — so I scope narrowly and validate against the nearest parser, linter, or tool sanity check.

## When To Use Me

- when editing JSON, YAML, TOML, INI, or shell/runtime configuration
- when changing tool, editor, or runtime settings that affect behavior
- when a schema, default, or compatibility posture could shift silently
- before claiming a config change is safe

## Read Alongside

- `docs/ai/capabilities/config-change-safety/CAPABILITY.md`
- `docs/ai/capabilities/verify-change/CAPABILITY.md`
- `docs/ai/capabilities/verify-change/checklist.md`

## Steps

1. Scope narrowly: identify the exact keys or files that must change and the behavior that must stay identical; leave everything else untouched.
2. Capture current behavior first — the existing value, default, or output — so you can prove nothing regressed.
3. Make the smallest edit that achieves the change; prefer in-place edits over rewriting whole config files.
4. Verify with the closest check: the format's own parser or schema validator, then the tool's own lint or dry-run, then a focused behavior check — narrowest first, escalating only if needed.
5. If a runtime cannot support a verification or a workflow step, document the fallback explicitly instead of claiming parity.

## Output

- the scoped list of keys/files changed and the behavior held constant
- the verification command run and its result (parser, linter, or tool check)
- confirmation that prior behavior is preserved, or the regression found
- any documented fallback where a runtime could not support a step

## Gotchas

- a file that parses is not proof of correct behavior — validate the tool actually accepts it
- do not silently widen compatibility or drop a default while making an unrelated change
- ask before changing secrets, credentials, or broad compatibility posture
- never claim a verification you did not run; separate executed checks from recommended ones
