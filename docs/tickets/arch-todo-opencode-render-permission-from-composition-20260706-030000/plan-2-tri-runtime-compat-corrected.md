# Architecture Plan (Corrected) — Tri-Runtime-Safe OpenCode `permission:` render from composition

- Ticket: arch-todo-opencode-render-permission-from-composition-20260706-030000
- Supersedes-context-for: `plan.md` (original 5-slice plan, 183 lines) — DO NOT delete the original; this file adds a ground-truth-corrected Copilot/Claude Compatibility Contract on top of it.
- Status: corrected for tri-runtime (OpenCode + Copilot + Claude) shipping safety
- Read-only design; no code edited to produce this plan.

## Why this corrected plan exists

The original plan is architecturally sound but under-specifies the Copilot and Claude side of a tri-runtime shipping story. `packages/ai-universal-rules/templates/core/agents` is the SINGLE shared source for all three adapters:
- `packs.php:163` → `copilot-agents` → `.github/agents`
- `packs.php:171` → `opencode-agents` → `.opencode/agents`
- `packs.php:183` → `claude-agents` → `.claude/agents`
(Dispatched to three renderers.) Stripping the `permission:` block from that shared source therefore touches the input of all three renderers at once, even though only OpenCode consumes the block. This plan adds the explicit preservation proof that shipping to OTHER repos is safe for all three.

## Ground-truth safety basis (verified read-only against code)

1. Copilot renderer (`tools/ai/install/copilot-agent-renderer.php`) and Claude renderer (`tools/ai/install/claude-agent-renderer.php`) BUILD their own frontmatter from scratch (copilot lines 59-68; claude lines 54-65). Neither copies the OpenCode `permission:` block.
2. Both emit `name:` and `description:` — Copilot `name:` at copilot-agent-renderer.php:61, Claude `name:` at claude-agent-renderer.php:56; both `description:` immediately after. They deliberately DROP `id:` and `mode:`.
3. For COMPOSED agents, `allowedBash` comes from `aiPermissionResolveAllowedBash()` (the composition MODEL) — copilot:38, claude:41 — NOT from the template's baked permission block.
4. Parser `aiInstallerParseCanonicalAgentFrontmatter()` (canonical-agent-frontmatter.php): top-level scalar parsing (lines 25-29, `id`/`description`/etc.) is INDEPENDENT of the `bash:` block parse (lines 31-41). The template `allowedBash` regex-parse is a FALLBACK only, dead for composed agents. => Stripping `permission:`/`bash:` from templates leaves `id`/`description`/body intact and does NOT change Copilot/Claude composed output. THIS IS THE CORE TRI-RUNTIME SAFETY PROOF.
5. Existing negative coverage ALREADY asserts no `permission:` in installed Copilot/Claude agents: CopilotAgentRendererTest.php:286, ClaudeAgentRendererTest.php:250. Existing installed-agent fixture tests exist: CopilotAgentRendererTest::testInstalledAgentFilesAreVsCodeNativeFormat (:274; asserts name:+tools: present, id:+permission: absent) and the Claude installed-agents test (~:240; markTestSkipped when `.claude/agents` absent, :242).

## ORIGINAL PLAN (unchanged — reproduce the 5 slices)

- [ ] P0: Slice 1 — Installer render seam. Add `aiPermissionInsertBlock()` (insert when no `permission:` key exists; before `agent_assessment:` if present, else before closing `---`). Wire `aiInstallerCopyDirAsOpenCodeAgents()` to render+insert for composed stems, verbatim otherwise. Add OpenCode-install test. Prove seam WHILE templates still carry blocks (insert acts as replace → no-op). Verify: new test + `composer test:fast`.
- [ ] P0: Slice 2 — Scope the generator. Remove the two template dirs from `generate-agent-permissions.php` `$dirs`; keep the `.opencode` dirs. Verify `php tools/ai/generate-agent-permissions.php --check` green.
- [ ] P0: Slice 3 (APPROVAL-GATED, 13+ files — confirm scope before applying) — Strip the `permission:` block from all 13 core + any composed optional template files, preserving `agent_assessment:` and all other frontmatter. Remove the key entirely (no placeholder).
- [ ] P1: Slice 4 — Repoint tests. Update `AgentPermissionPolicyTest.php` and `AgentPermissionDriftTest.php` from template-file permission assertions to `.opencode/agents/*.md` or compose-model assertions; add a guard test that every shipped template agent stem has a composition entry.
- [ ] P1: Slice 5 — Byte-identical fixture proof + docs. Fresh install before/after; assert byte-identical `.opencode/agents/*.md` permission blocks. Update `docs/ai/adapter-contract.md` "Permission Projection Seam" prose.

## NEW SECTION — Copilot/Claude Compatibility Contract (tri-runtime preservation)

Rationale: The shared template source feeds all three renderers, but only OpenCode consumes `permission:`. Copilot and Claude re-derive their own frontmatter and resolve composed permission data through their OWN adapter (`aiPermissionResolveAllowedBash()`), so removing the OpenCode `permission:` block from the shared source MUST leave Copilot/Claude installed output unchanged. This section makes that invariant an explicit, tested contract before Slice 3 strips the source.

### Required Copilot checks (installed `.github/agents/*.agent.md`)
- No top-level `permission:` block (already asserted CopilotAgentRendererTest:286 — keep green).
- Required non-permission frontmatter SURVIVES: `name:` present, `description:` present, `tools:` present (already asserted :283-284 — keep green).
- Composed permission data resolves ONLY via the Copilot adapter (`aiPermissionResolveAllowedBash()`), never by copying OpenCode YAML: Shell Boundary body list for a composed execute agent equals the composed allowlist, independent of any template `permission:` block.
- Body preserved (e.g. architect body sentinel string).

### Required Claude checks (installed `.claude/agents/*.md`)
- Same shape: no top-level `permission:` (already ClaudeAgentRendererTest:250), `name:`+`description:` survive (:247-248), Bash Command Policy body reflects the composed allowlist via the Claude adapter, body preserved.

### CORRECTED assertion binding (id: → name:) — MANDATORY
Any positive assertion that "required metadata survives" MUST bind to `name:` and `description:` — NOT `id:`. Copilot AND Claude INTENTIONALLY DROP `id:` (Copilot uses `name:` at copilot-agent-renderer.php:61; Claude uses `name:` at claude-agent-renderer.php:56), and both test suites explicitly `assertStringNotContainsString("\nid:", ...)` (CopilotAgentRendererTest:285, ClaudeAgentRendererTest:249) and `permission:` absent. Therefore any `assertStringContainsString("id:", ...)` for Copilot/Claude installed output is WRONG and WOULD FAIL. Use `assertStringContainsString('name:', ...)` and `assertStringContainsString('description:', ...)` instead. Reason recorded in-plan so implementers do not re-introduce the `id:` assertion.

### NEW cross-adapter fixture test (net-new coverage)
`testComposedTemplateWithoutPermissionRendersSafelyForOpenCodeCopilotAndClaude`:
- Build a single composed-agent template fixture WITH `permission:` stripped (the post-Slice-3 shape) but retaining `id:`/`description:`/body/optional `agent_assessment:`.
- Render it through all three adapters.
- Assert OpenCode output HAS a top-level `permission:` equal to `aiPermissionRenderOpenCodeBlock($model, $render)` for that stem's composition.
- Assert Copilot output has NO top-level `permission:`, HAS `name:` and `description:`, preserves body, and (if execute agent) its Shell Boundary equals the composed allowlist.
- Assert Claude output has NO top-level `permission:`, HAS `name:` and `description:`, preserves body, and (if execute agent) its Bash Command Policy equals the composed allowlist.
This is genuinely net-new: no existing test renders the SAME stripped composed template through all three adapters in one assertion group.

### NEW shipped-repository / pack-path proof (net-new coverage)
Install the SAME composed template through all three `install_type`s via the pack path (`copilot-agents` → `.github/agents`, `opencode-agents` → `.opencode/agents`, `claude-agents` → `.claude/agents`; packs.php:163/171/183) into a throwaway fixture target repo, then assert the cross-adapter invariants above on the INSTALLED files — not only on local dogfood `.opencode/agents/*.md`. This proves the invariant holds for OTHER repos receiving the pack, closing the gap that the existing installed-file tests `markTestSkipped` when an adapter dir is absent.

### Regression-risk table (shipping to other repos)

| Risk | Trigger | Detection | Mitigation |
|---|---|---|---|
| Copilot/Claude lose required metadata after strip | shared template source stripped too aggressively (removes `id:`/`description:`) | cross-adapter fixture + pack-path proof assert `name:`+`description:` present | strip ONLY `permission:`/`bash:` block; preserve all other frontmatter |
| Copilot/Claude accidentally gain a `permission:` block | future renderer edit copies OpenCode YAML | existing :286/:250 negative asserts + cross-adapter fixture | keep negative assertions; adapter builds frontmatter from scratch |
| Composed allowlist drift between adapters | composition changed but one adapter not re-resolved | pack-path proof compares each adapter's rendered allowlist to composed model | all three resolve via `aiPermissionResolveAllowedBash()` / composed model |
| OpenCode loses its block on ship | installer seam not wired / stem keyed by `id` | byte-identical fixture (AC-04) + cross-adapter fixture OpenCode-has-block assert | key by filename stem; insert-then-render |
| Silent empty Shell Boundary for a future uncomposed agent | new template stem lacks composition entry | Slice 4 guard test (every stem has composition entry) | fail loudly on missing composition entry |

## Corrected NAC (replaces original weak NAC line)

- [ ] NAC-01 (CORRECTED): Installed Copilot AND Claude agents preserve their required non-permission metadata (`name:` present, `description:` present, Copilot also `tools:`), their original body, and their composed-permission adapter path (`aiPermissionResolveAllowedBash()`), AND the pack-path installed output for all three `install_type`s reflects this — while containing NO top-level `permission:` block. (Supersedes original: "Keep installed Copilot/Claude agents WITHOUT any permission block.") Existing green baselines: CopilotAgentRendererTest:283-286, ClaudeAgentRendererTest:247-250.

Retain original NAC-02 (templates/core/agents still exists) and NAC-03/04 (no composed-model byte changes; PermissionRenderAdaptersTest round-trips unchanged).

## Added Acceptance Criteria (CAC-01..CAC-07)

- [ ] CAC-01: Cross-adapter fixture renders one stripped composed template through OpenCode/Copilot/Claude in a single test group.
- [ ] CAC-02: OpenCode output HAS top-level `permission:` byte-equal to `aiPermissionRenderOpenCodeBlock($model, $render)` for that stem.
- [ ] CAC-03: Copilot output has NO top-level `permission:`, HAS `name:`+`description:`, preserves body.
- [ ] CAC-04: Claude output has NO top-level `permission:`, HAS `name:`+`description:`, preserves body.
- [ ] CAC-05: For a composed execute agent, Copilot Shell Boundary and Claude Bash Command Policy each equal the composed allowlist (adapter-resolved, not copied from OpenCode YAML).
- [ ] CAC-06: Pack-path proof installs the SAME composed template via all three install_types and asserts CAC-02..CAC-05 on INSTALLED files (not only dogfood `.opencode/agents/*.md`).
- [ ] CAC-07: No positive assertion uses `id:` for Copilot/Claude installed output; positive metadata assertions bind to `name:`/`description:` (per ground truth: both adapters drop `id:`).

## Minimum-5-test gate (must pass before implementation is "safe")

Implementation is NOT "tri-runtime safe to ship" until ALL of these pass:
1. Byte-identical `.opencode/agents/*.md` fresh-install fixture (original AC-04).
2. `testComposedTemplateWithoutPermissionRendersSafelyForOpenCodeCopilotAndClaude` (CAC-01..CAC-05).
3. Pack-path shipped-repo proof across all three install_types (CAC-06).
4. Existing Copilot installed-file test still green (CopilotAgentRendererTest:274 — name:+tools: present, id:+permission: absent).
5. Existing Claude installed-file test still green (ClaudeAgentRendererTest ~:240 — name:+description: present, id:+permission: absent; must NOT remain skipped in the pack-path fixture where `.claude/agents` IS installed).

## Coverage delta (net-new vs already-covered)

- Already covered (keep green, do NOT duplicate): no-`permission:` on installed Copilot/Claude (:286/:250); `name:`/`description:`/`tools:` present on installed files (:283-284/:247-248); no-`id:` on installed files (:285/:249); OpenCode block render in isolation (PermissionRenderAdaptersTest).
- Net-new value: (a) cross-adapter fixture rendering ONE stripped composed template through all three adapters in one assertion group; (b) pack-path proof across all three install_types on INSTALLED files (defeats the `markTestSkipped`-when-absent gap); (c) explicit composed-allowlist equality per adapter (CAC-05).

## Verification commands

php tools/ai/generate-agent-permissions.php --check
composer test:fast
composer test
php tools/ai/validate-adapter-drift.php --fail-on-warn

## Risks And Rollback

- Risk level: MEDIUM (install path for security-relevant permission frontmatter; shared source feeds three adapters).
- Rollback: revert slice commit(s); `.opencode/agents/*.md` remain baked-and-committed throughout, so revert restores prior on-disk state. Slice 3 template strip is the only source-deletion and is git-recoverable.
- Success signal: minimum-5-test gate green + generator `--check` green + `validate-adapter-drift --fail-on-warn` green + full `composer test` green.

## Handoff Notes

- Slice 3 remains APPROVAL-GATED (13+ template files stripped): confirm scope before applying.
- Do NOT bind positive Copilot/Claude metadata assertions to `id:`; use `name:`/`description:` (ground truth: both adapters drop `id:`; :285/:249 assert its absence).
- Key composed agents by filename stem, never frontmatter `id`, in the installer seam.
- Cross-reference: this file adds the tri-runtime Compatibility Contract on top of the original `plan.md`; do not delete the original.
- Recommended next step: implementer means implementer agent handoff using OpenCode command: /implement
