# <PROJECT_NAME> — Project AI Notes

This directory holds **your** project-specific AI guidance. The AI kit installs these
three files once and never overwrites them on upgrade (ownership: `template`). Edit them
freely — they are yours.

## Files

- `README.md` — this overview (what lives here and how the AI uses it).
- `project-interaction.md` — how you want the AI to collaborate on this repo.
- `conventions.md` — durable code, naming, and review conventions for this repo.

## How the AI uses this

Reference these files from `.ai/project.yml` under `context.extraDocs:` so every rendered
instruction surface points back to them. They survive re-render because they are listed in
`project.yml`, not embedded in generated files.

## What you can edit

These three files (and `.ai/project.yml`, `docs/ai/project-stack.md`) are **user-owned**: edit
them freely. Kit-managed files — anything with a `GENERATED — DO NOT EDIT` or `Managed by ai-kit`
header — are re-rendered on upgrade, so edit the template or `.ai/project.yml` instead. For the full
editable-vs-generated breakdown see `docs/ai/source-of-truth.md`.

## Keeping this current

Update these when your stack, workflow, or conventions change. Prefer concrete, verifiable
statements over aspirations. Say `unknown` when the repo does not prove something.
