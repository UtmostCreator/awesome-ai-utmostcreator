# Architecture Plan — Model the awesome-ai-utmostcreator Internal Lifecycle in YAML

- Status: Done (three models authored + validated; diagrams generated)
- Type: architecture plan (documentation / model extension)
- Owner: utmostcreator
- Created: 2026-07-11
- New model root: `___ARCHITECTURE_2.0/internal_architecture/lifecycle-model/lifecycle/`
- Reference model (proven pattern): `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/model/opencode.lifecycle.yaml`
- Reference generator (reused as-is): `___ARCHITECTURE_2.0/opencode_architecture/lifecycle-model/lifecycle/scripts/gen_mermaid.py`
- Reference schema (reused as-is): `.../lifecycle/model/lifecycle.schema.json`

## Goal

Produce a single-source-of-truth YAML model of THIS repository's architecture —
how we ship, how generation works, tests/verification, and how the custom AI
workflow (agents, skills, instructions, capabilities, tools, custom tools,
scripts, docs, shared docs, providers copilot/claude/opencode, commands, repomix,
security, configs, extra automation, and cross-repo runs) actually operates —
using the same YAML `machines` model and generator already proven for OpenCode.

## Core Decision — How We Separate Concerns

**One schema + one generator, but three model files (one per architectural
axis). Not one giant file; not per-concern micro-files.**

Within each file, the **`machine` is the concern-separation unit** (exactly as the
reference model does it). Install is a machine, generation is a machine,
provider-parity is a machine.

| Model file | Axis | Reader / owner | Analogous reference |
|---|---|---|---|
| `model/repo.lifecycle.yaml` | Runtime/build machinery: ship, generate, verify, hooks, security, config | build/release engineers | `opencode.lifecycle.yaml` |
| `model/repo.providers.yaml` | Provider/adapter parity across copilot/claude/opencode for all primitives | adapter maintainers | (new; highest-value new axis) |
| `model/repo.workflow.yaml` | Agent governance workflow: routing, handoffs, approval, verification ladder, artifact progression | agent authors | `agent/repo-workflow.md` (promoted to model-driven) |

### Why this split

- **Not one giant file:** the reference already hits readability limits at 751
  lines / 24 machines. This repo has more surface (install packs, 3 providers x 5
  primitives, generators, hooks, security). One file would exceed ~1500 lines and
  mix three genuinely different reader intents. Hard to diff, hard to load selectively.
- **Not per-concern micro-files:** `cross_links` are the only place machines
  touch and are validated against both endpoints; the generator loads ONE
  `--model`. Ten files would break cross-linking or require a merge feature that
  does not exist, and would fragment the single-source-of-truth property.
- **Three axis-files:** each maps to a distinct reader, owner, and
  source-of-truth cluster. Proven granularity, reused infra.

## Concern -> Machine Mapping

### `model/repo.lifecycle.yaml` (ship / generate / verify machinery)

| Concern | machine id | kind | provenance anchor |
|---|---|---|---|
| Shipping / install pipeline | `install_pipeline` | pipeline | `tools/ai/install/executor.php`, `planner.php`, `ai.php` |
| Pack selection | `pack_selection` | decision | `tools/ai/install/packs.php`, `selection-engine.php` |
| Profile -> pack precedence | `profile_precedence` | precedence | `tools/ai/install/profiles.php` |
| Merge strategy / drift protection | `merge_strategy` | statechart | `.ai-install-manifest.json`, `fs-writers.php`, `migrations.php` |
| Generation / render | `generation_pipeline` | pipeline | `generate-*.php`, `render-adapters.php` |
| Render source-of-truth | `render_source` | dataflow | `templates/**`, `.ai/project.yml`, `docs/ai/**` |
| Verification ladder | `verification_ladder` | pipeline | `composer.json`, `run-repo-tests.sh`, `ai-verify.sh` |
| Permission compose | `permission_compose` | pipeline | `permission-layers/compose.php` |
| Command-policy tiers | `command_policy` | decision | `command-policy.tiers.yaml`, `pre-tool-use.sh` |
| Hook lifecycle (pre/post-tool-use) | `hook_lifecycle` | statechart | `pre-tool-use.sh`, `post-tool-use.sh` |
| Config source-of-truth | `config_precedence` | precedence | `.ai/project.yml`, `opencode.jsonc` |
| Kit inventory | `kit_inventory` | inventory | packs/primitive counts |
| CI / automation / external-repo | `ci_automation` | pipeline | `.github/workflows/**`, `test-external-install.yml` |

### `model/repo.providers.yaml` (provider parity)

| machine id | kind | anchor |
|---|---|---|
| `provider_pipeline` | pipeline | canonical -> registry -> renderer -> output -> drift |
| `primitive_matrix` | inventory | agents/skills/commands/capabilities/instructions x 3 providers |
| `permission_projection` | pipeline | `permission-layers/render-adapters.php` |
| `drift_check` | decision | `validate-adapter-drift.php`, `generate-agent-permissions.php --check` |

### `model/repo.workflow.yaml` (governance)

Promote `___ARCHITECTURE_2.0/agent/repo-workflow.md`: `routing`,
`handoff_progression`, `execution_sequence`, `approval_boundaries`,
`verification_ladder`, `terminal_signal`, `artifact_progression`,
`source_of_truth`.

## Layout (reuses model-agnostic infra)

```
___ARCHITECTURE_2.0/internal_architecture/lifecycle-model/lifecycle/
  model/
    repo.lifecycle.yaml     # NEW — ship / generate / verify
    repo.providers.yaml     # NEW — provider/adapter parity
    repo.workflow.yaml      # NEW — agent governance
    lifecycle.schema.json   # REUSE (copied from opencode model)
  generated/                # build artifacts (gitignored *.mmd)
  README.md                 # NEW — documents the three-axis split
```

Generator is reused unchanged:
`python3 <ref>/scripts/gen_mermaid.py --model model/repo.lifecycle.yaml --schema model/lifecycle.schema.json --out generated --check`

## Steps

1. Persist this plan (done).
2. Scaffold `lifecycle-model/lifecycle/` (copy schema, add README + `.gitignore`).
3. Author `model/repo.lifecycle.yaml` (ship/generate/verify axis) — machine by machine.
4. Validate with `gen_mermaid.py --check` after each machine block.
5. Author `model/repo.providers.yaml`.
6. Author `model/repo.workflow.yaml`.
7. Validate all three + generate diagrams.

## Things To Avoid

- Do NOT invent one monolithic `repo.everything.yaml`.
- Do NOT fragment into per-concern files (breaks single-model cross-linking).
- Do NOT hand-edit `generated/*.mmd`.
- Do NOT duplicate the schema/generator — reuse the OpenCode model's `--model` path.
- Keep `provenance` pointed at REAL `tools/ai/**` / `scripts/ai/**` / `docs/ai/**`
  paths + symbols (structured data, not comments), enabling a future CI drift-check.
- Do NOT modify any live repo code (`tools/ai/**`, `scripts/ai/**`) in this slice —
  this is a modeling/documentation slice under `___ARCHITECTURE_2.0/`.

## Acceptance Criteria

1. Three model files exist under the new lifecycle dir, each passing
   `gen_mermaid.py --check` (schema + semantic).
2. Every machine carries `kind` + at least one real `provenance` anchor into
   `tools/ai/**`, `scripts/ai/**`, or `docs/ai/**`.
3. `cross_links` resolve on both endpoints within each file.
4. `README.md` documents the three-axis split and the reused generator command.
5. No new generator/schema code; existing `--model` path used.
