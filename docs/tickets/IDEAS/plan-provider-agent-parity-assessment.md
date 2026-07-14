# Architecture + Assessment — Cross-Provider Agent Generation Parity

- Ticket: none (investigation requested via `/remote-control`)
- Question answered: "How do we generate agents into OpenCode / Copilot / Claude? Why does each
  provider have a different number of lines if we're using adapters? Should only the frontmatter
  differ — do we need to fix this at all?"
- Generated: 2026-07-09
- Plan file: docs/tickets/IDEAS/plan-provider-agent-parity-assessment.md
- Type: assessment + implemented fix + gate
- Verdict: **Adapters are correct in principle, BUT real cross-provider drift was found and fixed.**
  The initial 4-agent sample (architect/reviewer/researcher/implementer) was clean, but a full
  sweep found the `.opencode/agents/` dogfood tree was **stale** for several agents — the canonical
  templates were updated (v0.6) and `.claude`/`.github` re-rendered, but OpenCode had no re-render
  gate and silently lagged. Root cause: `render-adapters.php` (the dogfood regen + parity gate)
  never covered `.opencode/`. Fixed by regenerating the 6 cleanly-fixable stale files and adding an
  OpenCode parity gate (`tests/php/OpencodeAgentBodyParityTest.php`). See §6 (Outcome).

## 1. How Generation Works (the pipeline)

There is one canonical source and three renderers. The body is authored once; each provider gets a
per-format transform.

```
packages/ai-universal-rules/templates/core/agents/<id>.md      ← single source of truth
packages/ai-universal-rules/templates/optional/agents/<id>.md    (OpenCode-format markdown:
        │                                                         id/mode/capabilities/permission.bash
        │                                                         frontmatter + shared prose body)
        ▼  aiInstallerParseCanonicalAgentFrontmatter()   tools/ai/install/canonical-agent-frontmatter.php:13
        │      → { frontMatter, body, allowedBash }
        │
 ┌──────┴───────────────────────────┬──────────────────────────────┐
 ▼                                   ▼                              ▼
CLAUDE renderer                 COPILOT renderer               OPENCODE writer
aiInstallerRenderClaudeAgent    aiInstallerRenderCopilotAgent  aiInstallerCopyDirAsOpenCodeAgents
claude-agent-renderer.php:38    copilot-agent-renderer.php:25  copilot-agent-renderer.php:314
 │  rebuild frontmatter          │  rebuild frontmatter          │  keep canonical frontmatter
 │  + inject ## Bash Command      │  + inject ## Enforcement       │  + generated header only
 │    Policy body section         │    Boundary + ## Shell Boundary │  (near-verbatim passthrough)
 ▼                                ▼                              ▼
.claude/agents/<id>.md          .github/agents/<id>.agent.md    .opencode/agents/<id>.md
        │                                   │                              │
        └──── executor.php:111-141 dispatches by install_type ────────────┘
```

Key code:
- Shared parser: `tools/ai/install/canonical-agent-frontmatter.php:13`
- Claude renderer: `tools/ai/install/claude-agent-renderer.php:38` (frontmatter `:64-74`, Bash Command Policy `:76-169`)
- Copilot renderer: `tools/ai/install/copilot-agent-renderer.php:25` (frontmatter `:60-68`, Enforcement `:84-95`, Shell Boundary `:97-124`)
- OpenCode writer: `tools/ai/install/copilot-agent-renderer.php:314` (canonical is already OpenCode format → passthrough + header)
- Tool maps: `claude-agent-tool-registry.php:75`, `copilot-agent-tool-registry.php:69`
- Claude capability rewrites: `claude-runtime-capabilities.php:38-247`
- Dispatch: `tools/ai/install/executor.php:111-141`

So **yes — we are using adapters.** The body is shared; the adapters only rebuild frontmatter and
add the provider's own permission/policy expression.

## 2. Why Line Counts Differ (the actual answer)

Measured across a representative 4-agent sample (`architect`, `reviewer`, `researcher`,
`implementer`), the shared body is essentially identical; the total-line spread comes almost
entirely from the **preamble** (frontmatter + the provider-specific policy block), not from the
shared instructions.

| Agent | Total lines C/Cop/OC | Preamble before `## Core Mission` C/Cop/OC | Shared-body lines C/Cop/OC | Body lines that actually differ C↔Cop / C↔OC / Cop↔OC |
|---|---|---|---|---|
| reviewer | 279 / 298 / 303 | 125 / 144 / 151 | 154 / 154 / 152 | 6 / 10 / 4 |
| researcher | 291 / 299 / 313 | 125 / 133 / 147 | 166 / 166 / 166 | 8 / 8 / **0** |
| implementer | 230 / 245 / 313 | 139 / 154 / 222 | 91 / 91 / 91 | 6 / 6 / **0** |
| architect | 259 / 187 / 281 | 107 / 35 / 129 | 152 / 152 / 152 | 6 / 6 / **0** |

(C = Claude, Cop = Copilot, OC = OpenCode. `architect` numbers are momentarily skewed by an
uncommitted edit to its canonical template.)

Where each provider's line delta comes from:

- **Claude** carries a `## Bash Command Policy` body section (~111 lines for an execute-capable
  agent) because Claude sub-agent frontmatter *cannot* express a per-command bash allowlist — only
  tool-level `tools`/`disallowedTools`/`permissionMode`. The allowlist therefore moves into prose.
- **Copilot** carries `## Enforcement Boundary` + `## Shell Boundary` sections for the same reason,
  plus a `handoffs:` frontmatter list.
- **OpenCode** carries the allowlist natively in its `permission.bash{}` frontmatter map, so its
  *preamble* is the largest (e.g. implementer 222 lines) while its body adds nothing.

Every difference falls into exactly three buckets, and all three are intended:

| Bucket | Example | Verdict |
|---|---|---|
| (a) Provider frontmatter/metadata | Claude `tools`/`permissionMode` vs Copilot `tools[]`/`handoffs[]` vs OpenCode `permission.bash{}` | Expected — different schemas |
| (b) Provider policy body sections | Claude Bash Command Policy / Copilot Shell Boundary / OpenCode frontmatter map | Expected — dominant cause of line spread; each is the same allowlist in the format its runtime can enforce |
| (c) Shared-body text deltas (0–10 lines) | Claude "not runnable on Claude Code" vs OpenCode `(ask)`; "Bash Command Policy above" vs "in frontmatter"; external-directory prompt phrasing | Intentional per-runtime adaptation of the *same* sentence — not accidental drift |

This holds for the 4 sampled agents. **A full sweep of all agents told a different story** (see §3):
the `.opencode/agents/` dogfood copies were stale for several agents — the (c)-bucket "adaptations"
the sample saw were partly just the OpenCode copy lagging behind a canonical update.

## 3. Verdict — Real Drift Found And Fixed

**A functional fix WAS required.** The clean 4-agent sample was misleading. A full sweep comparing
every `.opencode/agents/*.md` to its canonical template (full-file byte comparison, not just body)
found **6 stale agents**: `agent-critic` (missing a whole `## WebFetch Access` section, 23 body
lines behind), `agent-fleet-assessor`, `config-maintainer`, `repository-researcher`, `reviewer`, and
`post-install` (stale GENERATED-header format). Their canonical templates had been updated in v0.6
and `.claude`/`.github` were re-rendered, but the OpenCode copies were not.

Root cause: `tools/ai/render-adapters.php` — the dogfood re-render + byte-parity gate — only ever
covered `.claude/agents` and `.github/agents`. **OpenCode had no re-render path or gate**, so it was
the one surface that could (and did) silently drift while the other two stayed in sync. The existing
`AgentPermissionDriftTest` only checks the OpenCode *permission block*, not the full body, so body
drift like the missing WebFetch section went uncaught.

Not fixed here (out of bounded scope, entangled with unrelated in-flight WIP): `architect` and
`architecture-plan-writer`, whose `.claude`/`.github` renders `render-adapters.php --check` already
flags as drifted. Their OpenCode copies are left to that holistic re-render and are documented
exceptions in the new gate (`RECONCILIATION_PENDING`).

The one real observation worth acting on is a **test-coverage gap, not a content bug**:

- Today's parity contract is **vertical**: `tools/ai/render-adapters.php --check`
  (`tests/php/AdapterRenderDriftTest.php:49-58`) proves each shipped `.claude`/`.github` file is
  byte-identical to a fresh render of its canonical template. `AgentPermissionDriftTest.php` covers
  the `.opencode` copy.
- There is **no horizontal** assertion: nothing extracts the post-`## Core Mission` body per
  provider and asserts cross-provider equality (modulo the known category-(c) adaptation lines).
  Cross-provider body consistency is guaranteed only *transitively*, because all three render from
  the same template. A future edit to a renderer's body-transform logic could diverge the shared
  body without tripping any test.

That gap is exactly what `docs/tickets/IDEAS/plan-provider-content-parity-validator.md` already
proposes to close (broadly, across agents + commands + skills + workflows). This assessment adds
nothing new to that plan except confirming, with measurements, that the agent slice of it is
low-urgency: the drift it would catch does not exist today.

## 4. Fix + Gate (implemented)

Rather than the originally-proposed fragile "compare the three rendered trees, normalize the known
adaptations" test, the gate uses the fact that **OpenCode is a verbatim passthrough**: the shipped
`.opencode/agents/<id>.md` is exactly its canonical template with one GENERATED header comment
inserted after the frontmatter (`aiInstallerInsertGeneratedHeaderAfterFrontmatter()`). So parity is
plain byte-equality against `canonical + header` — no normalization map, no fragility. Combined with
the existing `render-adapters.php --check` (Claude/Copilot == fresh render of canonical), all three
providers are then transitively guaranteed to derive from the same canonical.

### What was done
- **Regenerated 6 stale OpenCode files** in place from their canonical templates using the same
  header helper the installer uses (byte-verified): `agent-critic`, `agent-fleet-assessor`,
  `config-maintainer`, `repository-researcher`, `reviewer`, `post-install`.
- **Added `tests/php/OpencodeAgentBodyParityTest.php`** — asserts every canonical-derived
  `.opencode/agents/*.md` byte-matches `canonical + header`. Skips (by construction) opencode-only
  agents with no canonical (`script-runner`, `super-implementer`) and hidden internal-only agents
  the installer preserves verbatim (`bootstrapper`). `architect` + `architecture-plan-writer` are a
  documented temporary `RECONCILIATION_PENDING` exception (their Claude/GitHub renders are already
  flagged by `render-adapters.php --check`; their OpenCode copy is left to that holistic re-render).

### Deliberately NOT done (bounded scope, WIP-respecting)
- Did **not** touch the 43-file pre-existing uncommitted WIP (all `.claude/agents/*.md`, the
  renderer, tests), nor the WIP-flagged `architect`/`architecture-plan-writer` renders.
- Did **not** extend `render-adapters.php` to cover OpenCode: the dogfood `.opencode/` layout is
  inconsistent (10 non-hidden agents live in `.opencode/agents-optional/`, yet optional `agent-critic`
  sits in `.opencode/agents/`), so a clean tool extension needs the layout normalized first. Tracked
  as follow-up (see Handoff).
- Did **not** regenerate `packages/ai-universal-rules/catalog.json` / `docs/ai/catalog.md`, which
  index `.opencode/agents/*.md` — they are already WIP-modified and out of date; the WIP owner's
  pending catalog regen will absorb these 6 OpenCode changes.

## 5. Verification (actual results)
- `OpencodeAgentBodyParityTest`: **green** (positive + negative confirmed).
- `AgentPermissionDriftTest`: green (68 assertions) — the regen preserved every permission block.
- `AgentAssessment*`: green (25 tests).
- `AdapterRenderDriftTest`: **red on exactly `architect` + `architecture-plan-writer`** — identical
  to the pre-change baseline; this change introduces no new render drift.
- Full suite: 8 failures, **all pre-existing WIP** (2 AdapterRenderDrift + settings-projection +
  ai-config + catalog/generated-artifacts). Proven by reverting the 6 OpenCode files and re-running:
  the catalog/config/settings checks still fail without my changes.

## 6. Risks And Rollback
- **Low.** Content-sync of 6 stale files to their own canonical source + one new test. No runtime,
  renderer, or template change.
- **Rollback**: `git checkout` the 6 `.opencode/agents/*.md` and delete the test.

## Handoff Notes
- Read together with `plan-provider-content-parity-validator.md` (broad version) and
  `plan-provider-wiring-reconciliation.md` (one-time gap audit).
- Follow-ups for the WIP owner / a future `architect` slice:
  1. After the WIP lands, run `php tools/ai/render-adapters.php --write` to reconcile
     `architect`/`architecture-plan-writer`, then remove them from `RECONCILIATION_PENDING`.
  2. Regenerate the catalog (`generate-ai-catalog.php --write`) so it indexes the 6 synced OpenCode
     files.
  3. Normalize the `.opencode/` core-vs-optional layout, then extend `render-adapters.php` to cover
     OpenCode as a `--write`-capable dogfood regen path (closes the root-cause gap permanently and
     lets `OpencodeAgentBodyParityTest` drop its skip logic).
