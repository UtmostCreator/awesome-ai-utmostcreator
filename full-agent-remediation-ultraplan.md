# ULTRAPLAN — Claude Agent Fleet Remediation (`.claude/agents/`)

Planning run only (produced by a remote Claude Code / web agent). No edits applied.
All fixes target template sources, the renderer/registry, `docs/ai/agent-scores.yaml`,
or the Claude settings template — never the generated `.claude/agents/*.md` copies.
Companion to `full-agent-critic.md` (the 24-agent audit this plan remediates).

## 0. Pipeline confirmed (evidence)

1. **Source of truth**: `packages/ai-universal-rules/templates/{core,optional}/agents/<id>.md` — canonical OpenCode-format templates.
2. **Renderer**: `tools/ai/install/claude-agent-renderer.php` → `aiInstallerRenderClaudeAgent()`. Builds Claude frontmatter + a "Bash Command Policy" section, then appends the template body **verbatim** (L91-92).
3. **Frontmatter tool grants** come from `tools/ai/install/claude-agent-tool-registry.php` → `aiClaudeAgentToolRegistry()`, keyed by agent id. **Unknown ids hit a read-only, plan-mode fallback** (L62-66).
4. **Bash allowlist** = `allow`-effect bash patterns only, via `aiPermissionResolveAllowedBash()` (`render-adapters.php:137-151`). **`ask`-effect scripts are structurally excluded** — they can never appear in a Claude allowlist.
5. **`agent_assessment` block** sourced from `docs/ai/agent-scores.yaml` (`approved: true`), projected by `agent-assessment-template-writer.php`, carried into Claude by `aiCopilotExtractAssessmentBlock()`.
6. **`.claude/settings.json`** rendered from `packages/ai-universal-rules/templates/claude/settings.json` via `claude-settings-merge.php`. Enforced surface; currently **no `Edit`/`Write` deny rules**.
7. **Pack selection** (`packs.php`): `adapter-claude` renders `core/agents` (13) into `.claude/agents` (L183); `optional-agents-claude-pack` merges ALL `optional/agents` with **skip-if-exists** (L336-342).
8. **Re-render**: `php tools/ai/install-ai-kit.php` (or `php tools/ai/ai.php install --apply`).

**CRITICAL re-render trap:** the optional pack is `skip-if-exists` and the render loop never deletes the destination. A plain reinstall will NOT overwrite existing optional agent copies. To regenerate them you must delete the target `.claude/agents/<id>.md` first. Any verification that skips the delete silently validates stale copies.

## 1. Where the findings actually live (critic said "template", reality differs)

- **Write-denied blockers (docs/build-config/upgrade/bugfix)** → defect is in the **tool registry** (these 4 ids are absent → read-only fallback), NOT the template body. Templates already grant `edit:`.
- **Wrong GENERATED-header tier** (`core/agents` on optional agents) → **renderer bug** (hardcoded string, `claude-agent-renderer.php:96`).
- **Stale `agent_assessment.decision`** → source is **`docs/ai/agent-scores.yaml`** (human-`approved` gated).
- **Phantom "native path-scoped `edit:`" + garbled handoffs + OpenCode `/implement`/`/review-diff`** → literal **shared template-body** strings (reproduce).
- **Script Access ↔ allowlist mismatch** → inherent: shared body describes an OpenCode `ask` tier Claude cannot express (reproduces on ~14 agents).
- **settings.json no Edit/Write denies** → confirmed.

**Nuance corrections:** architecture-plan-writer / refactorer / config-maintainer ARE in the write registry — write works; only the *path-scoping* claim is false (scoping-overstatement, not a capability bug). implementer's Script Access scripts are `ask` in canonical frontmatter — body prose is the only defect (Batch 3 class).

## 2. Five batches (+ special case)

### BATCH 1 — Write-role rendering (unblocks docs, build-config, upgrade, bugfix)
File: `tools/ai/install/claude-agent-tool-registry.php`. Add the 4 ids to the write-capable block (`tools => $writeTools`, `disallowedTools => []`, `permissionMode => 'default'`). Switches render from read-only fallback to write-capable. **Must land with Batch 4** (settings.json denies) so unscoped Write doesn't expose `.env`/`*.pem`/lockfiles/`docs/ai/generated/**`. (upgrade: see §5 H2 — may be plan-only instead.)

### BATCH 2 — Purge OpenCode grammar (Strategy A: fix shared body to runtime-neutral)
- 2a Garbled handoffs: `architect.md:246`, `architecture-plan-writer.md:256` → prose "Recommend the X agent next".
- 2b Phantom `edit:` prose (docs/build-config/upgrade/bugfix/implementer/refactorer/config-maintainer/architecture-plan-writer) → "Edits use the runtime's native file-edit tool; scope enforced by the runtime permission surface (OpenCode `edit:` map / `.claude/settings.json` deny rules / Copilot workspace)." Grep-confirm exact lines first.
- 2c OpenCode slash-commands (`/implement`, `/review-diff`) → prose handoff sentences.
- **Regression guard:** these bytes feed Copilot + OpenCode too — run `CopilotAgentRendererTest` (incl. `testArchitectOutputPreservesOriginalBody`) + OpenCode round-trip after each file.

### BATCH 3 — Script Access ↔ Bash allowlist reconciliation (~14 agents)
- Body wording: change `ask`-tier script annotations to "on OpenCode `ask`; on Claude unavailable — use native tool / allow-listed equivalents." Do NOT flip `ask`→`allow` (widens OpenCode capability; ai-edit/rollback/checkpoint are mutating).
- Single high-leverage renderer edit: extend Bash Command Policy preamble (`claude-agent-renderer.php:78`) with a note that `ask`-tier scripts aren't runnable on Claude.
- Real hole: **infra-auditor `php tools/ai/validate-*.php *` wildcard** → narrow to the 4 explicit read-only validators in canonical frontmatter (matches a mutating `--apply` validator today).

### BATCH 4 — settings.json write-denies (enforced hard floor)
File: `packages/ai-universal-rules/templates/claude/settings.json`. Add to `permissions.deny`: `Edit/Write(docs/ai/generated/**)`, `docs/generated/**`, `**/*.lock`, `**/*.pem`, `**/*.key`, `**/*.crt`, `.env`, `.env.*`, `**/secrets.*`, `**/credentials.*`, `**/auth.json`. Global (also constrains implementer/refactorer/config-maintainer/apw/post-install — matches their canonical deny maps). **Verify `claude-settings-merge.php` union/idempotency.** Limitation: settings.json can't express *positive* path scoping — docs can still edit source (disclose in body, don't paper over).

### BATCH 5 — Metadata cleanup
- 5a **Renderer header tier** (`claude-agent-renderer.php`): thread the real source dir through `aiInstallerRenderClaudeAgent` (loop at L143/165 knows `$src`) → correct `core` vs `optional` label. Extend `GeneratedHeaderTest`.
- 5b **Decision values** (`docs/ai/agent-scores.yaml`, human-`approved` gated): reviewer/repository-reviewer → `approve`; encode docs/build-config/bugfix/upgrade decisions to the **post-remediation** state (after Batches 1-4), not the blocked snapshot. Never hand-edit template `agent_assessment` blocks (overwritten by projection).

### SPECIAL CASE — agent-fleet-assessor
Mission needs Task delegation; absent from registry → read-only fallback → no `Agent` tool; and Claude subagents cannot spawn subagents (structurally unperformable). **Option A (recommended):** exclude from `optional-agents-claude-pack` (OpenCode-only). Option B: ship a descoped Claude variant that aggregates pre-existing agent-critic outputs without delegating.

## 3. Per-agent write-role recommendations

| Agent | Canonical `edit:` | Label | Recommendation |
|---|---|---|---|
| docs | `docs/**`,`*.md`,README/AGENTS/CLAUDE; generated/lock/secret deny | GitHub-only | **Apply-capable** (`default`) |
| build-config | broad code+config edit | GitHub-only | **Apply-capable**, flag broad surface (leans on Batch-4 floor) |
| bugfix | broad code edit | GitHub-only | **Apply-capable**; also add reviewer handoff |
| upgrade | broad code edit; desc "Plan **or apply**"; risk critical | GitHub-only | **HUMAN DECISION** — default apply-capable (highest-risk) or plan-only. Don't ship half-and-half. |

All four also need a prose "Recommended next step" → `reviewer` (per docs/ai/agents.md routing).

## 4. Re-render + verification

Pre-step: `rm .claude/agents/{docs,build-config,upgrade,bugfix,infra-auditor,agent-critic,agent-creator,agent-creator-runtime-guardian,agent-creator-semantic-verifier,agent-creator-static-validator,agent-creator-supervisor,agent-fleet-assessor}.md` then re-run installer. Diff against git to confirm files actually changed (no-op diff = skip-if-exists trap bit you).

Validators (in order): `validate-agent-assessment-values.php`, `validate-agent-assessment.php`, `validate-agent-assessment-frontmatter-drift.php`, `validate-agent-spec.php`, `validate-adapter-drift.php`, `validate-generated-artifacts.php`.

PHP tests: `ClaudeAgentRendererTest`, `CopilotAgentRendererTest`, `AgentPermissionPolicyTest`, `GeneratedHeaderTest`, `ProjectTemplatesTest`. Add a registry assertion: docs/build-config/bugfix/upgrade render with Write,Edit + empty disallowedTools + `default` mode.

Re-run agent-critic on all 24; gate = all 6 validators pass + PHP suite passes + second re-render is a no-op + 0 blocked agents (or only deliberately-excluded ones).

## 5. Sequencing + human decisions

Order: **5a** (renderer header, independent) → **4** (settings floor, before/with 1) → **1** (write registry, needs H1/H2) → **2**+**3** (parallel, shared-body regression risk) → **5b** (decisions, last — depends on 1-4) → **special case** (needs net-new pack-exclusion mechanism).

Human decisions (block execution):
- **H1** — Ship-vs-exclude the four "GitHub-only" writers on Claude (docs/ai/agents.md labels them GitHub-only, yet the pack ships them). Governs whether Batch 1 is even needed.
- **H2** — upgrade posture: apply-capable vs plan-only.
- **H3** — agent-fleet-assessor: exclude from Claude (A) vs descoped aggregator (B).
- **H4** — agent-scores.yaml decision changes (`approved: true` re-sign-off).
- **H5** — infra-auditor validator-wildcard narrowing touches all 3 runtimes; confirm no OpenCode workflow needs a mutating `validate-*.php --apply` there.

Risk flags: R1 skip-if-exists re-render trap (highest); R2 shared-body edits regress OpenCode/Copilot; R3 global settings.json can't per-path-scope writers; R4 `claude-settings-merge.php` merge strategy (replace vs union).
