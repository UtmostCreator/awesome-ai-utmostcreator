# Full Agent-Critic Fleet Assessment — `.claude/agents/`

Generated: 2026-07-07. Each of the 24 agent files in `.claude/agents/` was audited by a
separate `agent-critic` run (schema fit, role/permission fit, contradictions, handoffs,
token economy). All defects target **template sources** under
`packages/ai-universal-rules/templates/**/agents/` or `.claude/settings.json` — the
`.claude/agents/*.md` files are GENERATED and must not be hand-edited.

## Fleet Statistics

- Agents assessed: **24 / 24**
- Average score: **72.4** · Median: **78** · Min: **34** (docs) · Max: **92** (repository-reviewer)
- Ready-with-fixes: **17** · Blocked: **7**
- Blocked: docs (34), build-config (35), upgrade (38), bugfix (41), agent-fleet-assessor (60), architecture-plan-writer (62), config-maintainer (65)

## Re-Audit Updates (post-remediation)

Agents re-audited by `agent-critic` against the shippable `.claude/agents/` copy after remediation:

| Agent | Original | Re-audit | Δ | New readiness | New rank |
|---|---:|---:|---:|---|---|
| architecture-plan-writer.md | 62 (blocked) | **92** | +30 | ready (no findings) | bottom-tier → **top-tier** |
| implementer.md | 84 (ready-with-fixes) | **92** | +8 | approve | mid → **top-tier** |

`architecture-plan-writer` was the fleet's lowest non-trivial score and is now tied at the top (92), decision bumped `blocked`/`approve_with_minor_fixes` → **approve**. `implementer` cleared all findings → **approve**. Both verified against the installed Claude copy; full PHP suite green (902/902).

## Summary Table (one row per agent)

| Agent | Score | Readiness | Key Strengths | Key Weaknesses | Top Fix Priority |
|---|---:|---|---|---|---|
| repository-reviewer.md | 92 | ready-with-fixes | Clean read-only posture; ai-verify ask/scoped split matches allowlist; full guardrail set | Duplicated query-usage caveat; "next step" names no roster agent | Remove duplicated caveat (line 113) |
| reviewer.md | 91 | ready-with-fixes | Enforced read-only; duplicate-screening + unknown discipline; valid handoffs | ai-verify absent from allowlist; stale needs_refactor decision; 3 registries cited | Fix stale agent_assessment.decision → approve |
| agent-critic.md | 91 | ready-with-fixes | Correct reviewer archetype; all 8 handoff targets exist; validator allowlist parity | Bash unbounded at frontmatter; very dense | Verify settings.json enforces Bash policy |
| repository-researcher.md | 89 | ready-with-fixes | Enforced read-only; all 17 scripts exist; conditioned single-target handoffs | pack-context.sh prose vs allowlist gap; no stop/ask condition; duplicated caveat | Reconcile pack-context.sh prose vs allowlist |
| architect.md | 88 | ready-with-fixes | Clean deny-by-default; mandatory plan-writer handoff; strong AC discipline | Garbled routing sentence; dead risk-taxonomy.md ref; no non-interactive fallback | Fix garbled routing sentence + broken doc ref |
| agent-creator-static-validator.md | 86 | ready-with-fixes | Deterministic gate discipline; clean validator posture; exit-code semantics | No route for exit-2 / non-tool agent; broad raw readers; wrong template-tier header | Add exit-2 + non-tool-agent handoff branches |
| implementer.md | 84 → **92** ⬆ | ready-with-fixes → **approve** | Strong evidence discipline; complete handoff routing; full safety guards | _Re-audited: all findings resolved (Script Access reconciliation, agnostic prose, external_directory neutralized)_ | — (approve) |
| release-auditor.md | 84 | ready-with-fixes | Coherent read-only posture; valid handoffs; strong unknown discipline | ai-verify allowlist gap; broad head/tail/bat readers; prose-only secret guard | Fix ai-verify prose/allowlist mismatch |
| agent-creator.md | 82 | ready-with-fixes | Deny-by-default write posture; anti-fabrication guards; contract fields match schema | Raw readers expose secrets (head/tail/jq/yq); ai-task.sh allowlist gap; wrong header | Remove secret-exposing raw readers; use preview-file.sh |
| researcher.md | 81 | ready-with-fixes | Production-grade evidence tiers; enforced read-only; resolving handoffs | Body grants append-write frontmatter denies; no non-interactive fallback; OpenCode leak | Reconcile append-evidence rule vs disallowedTools |
| post-install.md | 79 | ready-with-fixes | Strict placeholder gate w/ proof commands; evidence-backed delegation; structured ledger | Hard write-boundaries unenforced; frontmatter-authority contradiction | Add Edit/Write deny rules to settings.json |
| agent-creator-semantic-verifier.md | 78 | ready-with-fixes | Enforced read-only reviewer; resolving handoffs; testable verdict output | ai-verify contradiction; wrong template-tier header; inert OpenCode tool names | Resolve ai-verify allowlist contradiction |
| agent-creator-runtime-guardian.md | 78 | ready-with-fixes | Correct critical-risk auditor posture; enforceable Hard Rules; resolving handoffs | Script Access contradicts Bash policy; wrong header; broad readers; no self-stop | Reconcile Script Access vs Bash policy |
| workflow-auditor.md | 78 | ready-with-fixes | Correct read-only posture; hookless fallback clause; testable verdict output | ai-verify mismatch; empty Recommended Next Step heading; broad readers | Fix ai-verify contradiction |
| agent-creator-supervisor.md | 77 | ready-with-fixes | Coherent router posture; concrete testable pipeline gates; explicit failure routing | Claims frontmatter tier Claude lacks; ask-scripts absent from allowlist; no non-interactive gate | Fix Script Access permission-surface mismatch |
| refactorer.md | 73 | ready-with-fixes | Mandatory test gate w/ counts; rename/delete stop tokens; roster-valid handoffs | Unenforceable path-scoped edit claim; overpowered; duplication | Reconcile write-scope claim vs Claude enforcement |
| infra-auditor.md | 71 | ready-with-fixes | Clean read-only auditor; sharp anti-overreach gotchas; query-usage clarity | validate-*.php * wildcard permits mutation; no handoff/failure path; ask-scripts gap | Narrow validate-*.php * wildcard to read-only set |
| config-maintainer.md | 65 | blocked | Layered secret/destructive guards; testable Final Output; correct non-interactive fallback | Script Access vs allowlist conflict; false path-scoped-edit claim; enforced capability narrower than body | Reconcile Script Access + drop false edit claim |
| architecture-plan-writer.md | 62 → **92** ⬆ | blocked → **ready** | Bounded docs/tickets mission; strong failure/loop routing; testable output; clean multi-target handoff routing | _Re-audited: all findings resolved (agnostic write posture, mkdir fallback, non-interactive fallback, handoff routing)_ | — (approve) |
| agent-fleet-assessor.md | 60 | blocked | Machine-readable aggregation; clean stop conditions; dynamic roster derivation | Core mission needs task delegation Claude subagents lack; OpenCode-path probe → false-blocked | Descope to OpenCode-only OR grant/verify delegation |
| bugfix.md | 41 | blocked | Strong evidence discipline; deny-by-default Bash; clear minimal-fix framing | Fix mission but Write/Edit denied; no next-agent handoff; false edit claim; ask-scripts gap | Resolve edit-capability vs mission contradiction |
| upgrade.md | 38 | blocked | Deny-by-default allowlist; rename/delete stop-reports; correct critical risk | Apply mission but read-only/edit-denied; no handoff; ask-scripts gap; wrong header; false edit | Pick plan-only vs apply posture; align frontmatter+body |
| build-config.md | 35 | blocked | Deny-by-default Bash w/ destructive denylist; secret-scoped verify; rename/delete gates | Write mission but Write/Edit denied; body claims non-existent edit:; no handoff; understated decision | Resolve write-mission vs write-denied contradiction |
| docs.md | 34 | blocked | Deny-by-default Bash; four grounding rules + drift checks; scoped read tooling | Writer mission but edit fully denied (adapter drift); false edit claim; ask-scripts gap; no handoff | Grant scoped Edit + leave plan mode to match writer role |

---

# Full Per-Agent Critiques

## repository-reviewer.md — 92 / ready-with-fixes

Score table: Frontmatter 95, Role 95, Permission 90, Instruction 95, Handoff 85,
Evidence 95, Brevity 85, Runtime 95. Total 92.00. Schema validator passed; no cap.

Findings (no BLOCKERs, no MAJORs):
- [MINOR] Brevity — query-usage.sh caveat stated twice (line 100 and line 113). Fix: drop the parenthetical on line 113.
- [MINOR] Handoff — "Recommended next step" (line 125) names no roster candidate. Fix: append typical targets (implementer/architect/workflow-auditor).

Strengths: reviewer archetype cleanly enforced (disallowedTools: Write, Edit + permissionMode: plan);
ai-verify.sh correctly split (plain ask, scoped AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 allow) matching Bash policy;
full guardrail set (injection defense, secret-read denial line 86, generated carve-out line 118, destructive denial line 78).

Top fix: remove duplicated query-usage.sh caveat (line 113).
Proposed: risk_level: high, decision: approve (upgrades from current needs_refactor).
Template: packages/ai-universal-rules/templates/core/agents/repository-reviewer.md.

## reviewer.md — 91 / ready-with-fixes

Score table: Frontmatter 95, Role 95, Permission 85, Instruction 90, Handoff 95,
Evidence 95, Brevity 85, Runtime 95. Total 91.25 → 91. No BLOCKER, no cap.

Findings:
- [MINOR] Permission — body references ai-verify.sh (ask) at line 168 but it is absent from approved-scripts list (lines 19-113). Fix: add to list w/ ask note or drop reference.
- [MINOR] Instruction — agent_assessment.decision: needs_refactor (line 10) is stale given no MAJOR/BLOCKER. Fix: set decision: approve.
- [MINOR] Brevity — three registries cited for one concept (line 136). Fix: cite single canonical registry.
- [OBSERVATION] Line budget 275 lines; broad readers head/tail/bat coexist with preview-file.sh.

Strengths: read-only enforced; mandatory duplicate screening before PASS (line 144); unknown-on-no-evidence (line 145);
handoff routing valid (implementer/refactorer/release-auditor line 275).
Top fix: reconcile agent_assessment.decision → approve.
Proposed: risk_level: high, decision: approve. Template: core/agents/reviewer.md.

## agent-critic.md — 91 / ready-with-fixes

Score table: Frontmatter 95, Role 95, Permission 88, Instruction 95, Handoff 95,
Evidence 92, Brevity 82, Runtime 90. Total 91.75 → 91. 314 lines total.

Findings:
- [MINOR] Permission — Bash granted without frontmatter-level allowlist (line 4); enforcement depends on .claude/settings.json permissions.deny (out of single-file scope, unknown). Fix: none in-file; confirm settings.json denies non-allowlisted commands.
- [OBSERVATION] Evidence — validate-agent-assessment-values.php exits 1 (targets values manifest, not per-agent file; not applicable). validate-agent-assessment.php returns OK.
- [OBSERVATION] Brevity — 303 body lines, dense.

Strengths: correct reviewer archetype (Write/Edit denied + plan mode); all 8 handoff targets exist in roster;
internally consistent validator allowlist (4 validators appear in Bash policy, Script Access, Static Validation Gate).
Top fix: verify .claude/settings.json permissions.deny enforces the body's Bash Command Policy.
Proposed: risk_level: medium, decision: approve. Template: optional/agents/agent-critic.md.

## repository-researcher.md — 89 / ready-with-fixes

Score table: Frontmatter 92, Role 93, Permission 85, Instruction 92, Handoff 80,
Evidence 92, Brevity 85, Runtime 93. Total 88.9 → 89. 95 lines.

Findings:
- [MINOR] Permission — pack-context.sh named in prose (line 76) but absent from approved list (lines 21-54) and not in deny list. Fix: add to approved list or drop the reference.
- [MINOR] Handoff — no explicit stop/ask condition for missing/ambiguous evidence; no non-interactive fallback on Claude. Fix: add stop/handoff-to-architect line.
- [MINOR] Brevity — query-usage.sh caveat stated twice (lines 73 and 87). Fix: shorten line 87.

Strengths: deny-by-default read-only enforced (disallowedTools: Write, Edit + plan mode);
all 17 body-referenced scripts exist; all three handoff targets exist (implementer/architect/reviewer).
Top fix: reconcile pack-context.sh prose-vs-approved-list mismatch.
Proposed: risk_level: low, decision: approve_with_minor_fixes. Template: core/agents/repository-researcher.md.

## architect.md — 88 / ready-with-fixes

Score table: Frontmatter 92, Role 95, Permission 88, Instruction 85, Handoff 90,
Evidence 90, Brevity 82, Runtime 82. Total 88.7 → 88. validate-agent-assessment.php PASSED.

Findings (all MINOR):
- [MINOR] Instruction — garbled routing sentence "architecture-plan-writer means architecture-plan-writer agent handoff to persist the plan under docs/tickets/" (line 244), template substitution artifact. Fix: rewrite as prose.
- [MINOR] Evidence/permission — referenced canonical doc docs/ai/risk-taxonomy.md (line 143) does not exist (repo has docs/ai/command-risk-taxonomy.md). Fix: replace ref.
- [MINOR] Runtime/provider — non-interactive clarification fallback not stated in-file (lines 162, 184). Fix: append Claude fallback wording.
- [Observation] tools grants Agent (subagent spawn) but all handoffs are prose; Agent unused.

Strengths: clean deny-by-default (Write/Edit denied + plan + "Do not edit files");
mandatory architecture-plan-writer handoff (line 200) w/ structured payload; AC discipline (lines 164-170).
Top fix: fix garbled routing sentence (line 244) + broken doc ref (line 143).
Proposed: risk_level: high, decision: approve_with_minor_fixes. Template: core/agents/architect.md.

## agent-creator-static-validator.md — 86 / ready-with-fixes

Score table: Frontmatter 85, Role 92, Permission 80, Instruction 92, Handoff 78,
Evidence 95, Brevity 85, Runtime 85. Total 86. No BLOCKERs.

Findings:
- [MINOR] Handoff — no route for exit 2 (IO error) or PASS-with-non-tool-agent (line 125). Fix: add exit-2 → agent-creator, non-tool PASS → agent-creator-supervisor.
- [MINOR] Permission — broad raw readers head/tail/sed/jq (lines 25-28) alongside bounded preview-file.sh. Fix: route file reads through preview-file.sh.
- [MINOR] Frontmatter — GENERATED header cites wrong tier "templates/core/agents" (line 12); real source is optional/. Fix: emit correct tier.
- [MINOR] Brevity — Script Access restates Bash policy scripts (line 68 dup of 32-49).

Strengths: deterministic gate discipline (paste exact command + exit code, verbatim ERROR/WARN);
clean read-only posture; exit-code semantics fully defined (0/1/2, line 79).
Top fix: add missing exit-2 and non-tool-agent handoff branches.
Proposed: risk_level: medium, decision: approve_with_minor_fixes. Template: optional/agents/agent-creator-static-validator.md.

## implementer.md — 84 / ready-with-fixes

Score table: Frontmatter 90, Role 88, Permission 72, Instruction 78, Handoff 90,
Evidence 92, Brevity 85, Runtime 82. Total 83.6 → 84. 221 lines.

Findings:
- [MAJOR] Permission — Script Access prose (line 174) grants ai-edit.sh / ai-rollback.sh / session-checkpoint.sh (ask) but approved list (lines 20-121) omits them; body says "Do not run commands not in this list" (line 123). ask-tier has no Claude gate. Fix: add to Bash policy w/ ask note OR state unavailable on Claude, native Edit only.
- [MINOR] Role — Claude-rendered file instructs OpenCode-only command /review-diff (line 220) and external_directory: ask prompt (line 163). Fix: runtime-neutral text.
- [MINOR] Runtime — "path-scoped edit:" claim (line 177) not expressible in Claude frontmatter. Fix: reword to "native Edit, scoped by .claude/settings.json".

Strengths: strong evidence discipline (Not run/Recommended split, unknown rule, never claim unexecuted verification);
complete handoff/failure routing (researcher/architect/reviewer verified); full safety guard set (secret, injection, generated-file, delete/rename, destructive-git, external-mutation).
Top fix: resolve Script Access / Bash Command Policy contradiction (line 174 vs 123-124).
Proposed: risk_level: high, decision: needs_refactor. Template: core/agents/implementer.md.

## release-auditor.md — 84 / ready-with-fixes

Score table: Frontmatter 92, Role 95, Permission 72, Instruction 78, Handoff 82,
Evidence 90, Brevity 88, Runtime 85. Total 84.4 → 84. 207 lines.

Findings:
- [MAJOR] Permission — Script Access recommends ai-verify.sh (ask) at line 127 but enforced Bash allowlist (lines 55-77) omits it; "Do not run commands not in this list" (line 95). Script exists → listing gap. Fix: add to allowlist OR delete the clause (safer given "Do not run broad CI").
- [MINOR] Permission — broad raw file readers head/tail/bat (lines 33,34,41) alongside secret-read ban (line 115). Fix: prefer preview-file.sh.
- [MINOR] Runtime — secret and generated-file guards prose-only (lines 112, 115). Fix: pair with preview-file.sh redirect.

Strengths: coherent read-only posture (disallowedTools + plan + Hard Rules agree);
handoff targets exist (implementer/architect/reviewer line 207); strong evidence discipline (unknown usage).
Top fix: resolve ai-verify.sh prose/allowlist mismatch (line 127).
Proposed: risk_level: critical, decision: needs_refactor. Template: core/agents/release-auditor.md.

## agent-creator.md — 82 / ready-with-fixes

Score table: Frontmatter 85, Role 92, Permission 55, Instruction 90, Handoff 85,
Evidence 92, Brevity 88, Runtime 90. Total 82.05 → 82. validate-agent-assessment.php OK.

Findings:
- [MAJOR] Permission — raw file readers head/tail/jq/yq (lines 27-30) can expose secrets (.env, *.pem, credentials). preview-file.sh already allowlisted (line 41). Fix: remove raw readers; ground file content only through preview-file.sh.
- [MAJOR] Permission — Script Access references ai-task.sh (ask) at line 71 but Bash policy (lines 21-53) omits it; .claude cannot express ask. Fix: add to Bash policy or delete line.
- [MINOR] Frontmatter — GENERATED header names wrong template root "core/agents" (line 12); real is optional/. Fix: emit optional/.
- [MINOR] Handoff — no route for downstream validator rejection (line 108). Fix: add "if Static Validator rejects, revise and re-emit".

Strengths: deny-by-default write posture; anti-fabrication guards (requires_human_approval: true, self_modification: false);
contract required-field list (line 80) matches agent-spec.schema.json required array.
Top fix: remove secret-exposing raw readers; route all file inspection through preview-file.sh.
Proposed: risk_level: high, decision: needs_refactor. Template: optional/agents/agent-creator.md.

## researcher.md — 81 / ready-with-fixes

Score table: Frontmatter 88, Role 80, Permission 78, Instruction 68, Handoff 85,
Evidence 92, Brevity 78, Runtime 85. Total 80.75 → 81. 291 lines. No BLOCKERs.

Findings:
- [MAJOR] Instruction — body grants a write the runtime denies: "May append only research evidence notes to approved evidence paths" (line 136) vs disallowedTools: Write, Edit + plan (lines 5,7). Fabrication risk. Fix: drop the append-write allowance; persistence is post-tool-use.sh hook's job (line 130).
- [MAJOR] Provider fallback — interactive clarification (line 213) w/ no non-interactive path in-file. Fix: add "On Claude, state assumption, mark unknown, stop only on high-impact ambiguity."
- [MINOR] Provider leak — OpenCode command /review-diff hardcoded (line 290). Fix: runtime-neutral reviewer handoff.

Strengths: production-grade evidence discipline (ranked tiers, per-claim confidence, unknown fallback, path:line format lines 219-245);
read-only enforced; handoffs resolve (implementer/architect/reviewer).
Top fix: reconcile append-evidence Hard Rule (line 136) with disallowedTools (line 5).
Proposed: risk_level: medium, decision: needs_refactor. Template: core/agents/researcher.md.

## post-install.md — 79 / ready-with-fixes

Score table: Frontmatter 90, Role 88, Permission 62, Instruction 70, Handoff 90,
Evidence 92, Brevity 75, Runtime 70. Total 78.8 → 79. validate-agent-assessment.php passed.

Findings:
- [MAJOR] Permission/enforceability — Hard Boundaries "Do not edit docs/ai/generated/**, vendored deps, lock files, secret/key/env" (line 162) are INSTRUCTION_ONLY; .claude/settings.json deny has NO Edit/Write path deny while frontmatter grants unscoped Write+Edit. Fix (settings.json): add Edit/Write denies for docs/ai/generated/**, **/*.lock, .env*, **/*.pem, **/*.key.
- [MAJOR] Instruction — Script Access says "Full per-script allow/ask/deny is in frontmatter" (line 108) but Bash policy says Claude frontmatter cannot express per-command allowlists and settings.json wins (lines 15-16, 98-100). Fix (template): replace "in frontmatter" with "in Bash Command Policy / .claude/settings.json".
- [MINOR] Permission prose vs allowlist — ai-verify.sh (ask), repomix/pack-context.sh (ask) (lines 112-113) vs scoped-only variant (lines 67-68). Fix: drop ask-only refs or add to allowlist.
- [MINOR] Contradiction — line 96 bans install commands, yet lines 148/173 offer install re-render. Fix: clarify scoped re-render exempt.

Strengths: strict Placeholder Resolution Gate w/ two proof commands; evidence-backed research delegation (reject value w/o path/output); structured Final Output w/ approval ledger.
Top fix: add Edit/Write path deny rules to .claude/settings.json.
Proposed: risk_level: high, decision: needs_refactor. Template: core/agents/post-install.md.

## agent-creator-semantic-verifier.md — 78 / ready-with-fixes

Score table: Frontmatter 85, Role 90, Permission 60, Instruction 70, Handoff 85,
Evidence 85, Brevity 85, Runtime 85. Total 78. No BLOCKER.

Findings:
- [MAJOR] Permission/contradiction — Script Access grants ai-verify.sh (ask) at line 74 but allowlist (lines 21-55) omits it; "Do not run commands not in this list" (line 57). Script exists → live ambiguity. Fix: add to Bash policy (ask) or delete bullet.
- [MINOR] Frontmatter — GENERATED header cites wrong tier "core/agents" (line 12); real is optional/. Fix: correct renderer header.
- [MINOR] Instruction — denies OpenCode-style tool names ai-edit/ai-task (line 76) absent from Claude runtime. Fix: replace with Claude-relevant statement.

Strengths: read-only reviewer posture enforced; handoffs resolve (MISMATCH→agent-creator, MATCHES→supervisor, send-back→static-validator);
structured MATCHES/NOTES/MISMATCH verdict + fixed output template + unknown discipline.
Top fix: resolve ai-verify.sh contradiction.
Proposed: risk_level: medium, decision: needs_refactor. Template: optional/agents/agent-creator-semantic-verifier.md.

## agent-creator-runtime-guardian.md — 78 / ready-with-fixes

Score table: Frontmatter 80, Role 78, Permission 70, Instruction 75, Handoff 80,
Evidence 85, Brevity 85, Runtime 80. Total 77.95 → 78.

Findings:
- [MAJOR] Instruction/Permission — Script Access "Full per-script allow/ask/deny is in frontmatter" (line 70) + lists pre/post-tool-use.sh, session-checkpoint.sh, ai-rollback.sh, ai-verify.sh (lines 72-74); only ai-diff-context.sh is in the actual allowlist (lines 21-55). rollback/checkpoint are mutating, unfit for a plan-mode auditor. Fix: add to allowlist or rewrite line 70 as advisory and drop mutating scripts.
- [MINOR] Frontmatter — GENERATED header names wrong dir "templates/core/agents" (line 12); real is optional/. Fix: correct.
- [MINOR] Permission — broad readers head/tail/sed/jq/yq (lines 27-30) vs secret ban (line 94). Fix: route through preview-file.sh.
- [MINOR] Runtime — no self stop/input boundary for guardian's own run. Fix: add "Stop and hand to supervisor on ambiguous spec / missing evidence / scope growth."
- [MINOR] Handoff — "next step is implement" (line 120) is a verb, not roster id. Fix: "the implementer".

Strengths: correct auditor posture (Write/Edit denied + plan + risk_level: critical); enforceable Hard Rules (narrow-not-widen, derive ceilings, secret non-disclosure); handoffs resolve (supervisor, implementer).
Top fix: reconcile Script Access with Bash allowlist (line 70 + 72-74).
Proposed: risk_level: critical, decision: needs_refactor. Template: optional/agents/agent-creator-runtime-guardian.md.

## workflow-auditor.md — 78 / ready-with-fixes

Score table: Frontmatter 92, Role 88, Permission 74, Instruction 74, Handoff 55,
Evidence 82, Brevity 80, Runtime 72. Total 78.2 → 78.

Findings:
- [MAJOR] Instruction — Script Access promises ai-verify.sh (ask) at line 123 but Bash allowlist (lines 21-85) omits it; "Do not run commands not in this list" (line 87); settings.json has no ai-verify entry. Fix: add to allowlist + settings.json, or delete clause.
- [MAJOR] Handoff — "## Recommended Next Step" (line 163) empty template heading, no candidates, no failure path. Fix: add candidate routing + blocked-surface stop.
- [MINOR] Permission — broad readers head/tail/bat (lines 31,32,38) duplicate preview-file.sh (mitigated by settings.json deny of .env/secrets/pem/key).
- [MINOR] Brevity — vague "Load only what is relevant" (line 129).
- [MINOR] Runtime — no output/sensitive-data boundary in body. Fix: add "report secret path + owner action, never the value".

Strengths: correct read-only posture; hookless-runtime fallback clause (line 106); Audit Checklist + Verdict/Findings-table output.
Top fix: resolve ai-verify.sh contradiction (line 123).
Proposed: risk_level: medium, decision: needs_refactor. Template: core/agents/workflow-auditor.md.

## agent-creator-supervisor.md — 77 / ready-with-fixes

Score table: Frontmatter 90, Role 80, Permission 62, Instruction 65, Handoff 85,
Evidence 85, Brevity 90, Runtime 65. Total 76.9 → 77. 140 lines. No BLOCKERs.

Findings:
- [MAJOR] Permission — Script Access claims "Full per-script allow/ask/deny is in frontmatter" (line 70), contradicting Bash policy (line 16: Claude frontmatter cannot express per-command allowlists). Fix: name settings.json as enforced surface.
- [MAJOR] Permission — ask-gated scripts ai-task.sh, session-checkpoint.sh, pre/post-tool-use.sh (lines 73-74) absent from allowlist (lines 21-55). Fix: add or drop.
- [MAJOR] Runtime — interactive gates (line 94 clarifying questions, line 113 hard human-approval) lack Claude non-interactive fallback. Fix: add pending-human fallback.
- [MINOR] Handoff — "If approved, next step is implement" (line 140) names no roster agent. Fix: name created agent's runtime execution.

Strengths: deny-by-default router posture; concrete testable pipeline gates (lines 78-114); explicit failure routing (line 140).
Top fix: fix Script Access permission-surface mismatch (lines 70-74).
Proposed: risk_level: high, decision: needs_refactor. Template: optional/agents/agent-creator-supervisor.md.

## refactorer.md — 73 / ready-with-fixes

Score table: Frontmatter 85, Role 80, Permission 55, Instruction 68, Handoff 92,
Evidence 90, Brevity 65, Runtime 55. Total 73.4 → 73. validate-agent-assessment.php OK.

Findings:
- [MAJOR] Permission/enforceability — body claims "native path-scoped edit: permission" (lines 172, 175) that does not exist in Claude render; frontmatter grants Edit, Write with no disallowedTools; settings.json has no Edit/Write entry. All write guards INSTRUCTION_ONLY. Fix: drop edit: language + relabel prohibitions advisory, OR add enforceable scoping.
- [MAJOR] Role vs power — refactorer expects bounded edits; Claude surface unbounded (OVERPOWERED).
- [MINOR] Governance — body approves git branch* (line 45) but settings.json denies git branch -d/-D/etc (self-mitigated by precedence note lines 113-115).
- [MINOR] Duplication — reuse-check stated 3x (148, 212-218, 242); test-gate 2x.
- [Observation] 258 lines; ~90 are generated bash-allowlist block.

Strengths: mandatory test gate w/ pass/fail counts before handoff (lines 154-164); rename/delete stop tokens (lines 220-229); roster-valid handoffs (reviewer, architect).
Top fix: reconcile write-scope claim with Claude enforcement surface.
Proposed: risk_level: high, decision: needs_refactor. Template: core/agents/refactorer.md.

## infra-auditor.md — 71 / ready-with-fixes

Score table: Frontmatter 88, Role 85, Permission 62, Instruction 80, Handoff 35,
Evidence 65, Brevity 75, Runtime 70. Total 71.35 → 71. 103 lines. No BLOCKER.

Findings:
- [MAJOR] Permission — validator wildcard "php tools/ai/validate-*.php *" (line 64) matches mutation-capable validators (e.g. validate-generated-artifacts.php --apply), breaking read-only guarantee. Fix: replace with explicit read-only set (validate-adapter-drift/ai-config/agent-assessment/agent-assessment-values).
- [MAJOR] Handoff — no next agent, payload, stop, or failure path. Fix: add prose next-step (release-auditor/upgrade) + failure line.
- [MAJOR] Permission — Script Access references ai-verify.sh (ask), repomix, pack-context.sh (ask) (line 82) absent from allowlist (lines 21-64). Fix: add or drop.
- [MINOR] Permission — broad readers head/tail (lines 27-28) vs preview-file.sh (line 46).

Strengths: clean read-only auditor posture; anti-overreach gotchas (lines 101-102); query-usage.sh clarification (line 79).
Top fix: narrow validate-*.php * wildcard to four read-only validators.
Proposed: risk_level: high, decision: needs_refactor. Template: optional/agents/infra-auditor.md.

## config-maintainer.md — 65 / blocked

Score table: Frontmatter 85, Role 60, Permission 58, Instruction 52, Handoff 70,
Evidence 70, Brevity 70, Runtime 65. Total 65.4 → 65. No hard BLOCKER; sub-70 w/ MAJOR enforceability mismatches → needs_refactor before ship.

Findings:
- [MAJOR] Instruction — Script Access lists ai-edit.sh/ai-rollback.sh (line 147), session-checkpoint.sh (line 148) as usable (ask) but Bash policy (lines 20-100) omits all three; "Do not run commands not in this list" (line 102). Near-contradiction. Fix: remove or add to allowlist.
- [MAJOR] Role/enforceability — line 147 claims "native path-scoped edit: permission" (leaked OpenCode). Claude has no path-scoped edit; tools grant repo-wide Write/Edit (line 4); settings.json permissions.allow has no Edit/Write entry. Config-paths-only scope UNENFORCEABLE. Fix: describe actual mechanism (Edit gated by settings.json approval, no path scoping).
- [MAJOR] Mission vs posture — body advertises Write/build tier (line 142) + syntax/lint check (line 171) but settings.json allow enforces only 6 read scripts + git-read. Enforced capability far narrower.
- [MINOR] broad readers head/tail/bat (lines 31,32,38) vs preview-file.sh (line 57).
- [MINOR] rename/delete rules duplicated (129-132 vs 176-179).
- [MINOR] handoff (line 194) names no roster candidate.
- [MINOR] vague "Load only what is relevant" (line 154).

Strengths: layered secret+destructive guards (settings.json deny rm -rf, git reset --hard, git clean -f, secret Read globs); testable Final Output (lines 183-195); correct Claude non-interactive fallback (line 138).
Top fix: reconcile Script Access with Bash policy + drop false path-scoped-edit claim.
Proposed: risk_level: high, decision: needs_refactor. Template: core/agents/config-maintainer.md.

## architecture-plan-writer.md — 62 / blocked

Score table: Frontmatter 80, Role 70, Permission 48, Instruction 60, Handoff 62,
Evidence 85, Brevity 60, Runtime 42. Total 63.9 → 62 (enforceability MAJOR weighting). No BLOCKER; needs_refactor band, permission dim <50.

Findings:
- [MAJOR] Runtime/enforceability — body claims docs/tickets/** "IS approved ... via this agent's edit permission — all other paths denied" (line 73, also 61/79/80). settings.json permissions (lines 3-45) contain NO Edit/Write matcher; frontmatter grants unrestricted Write to every path. Guard is INSTRUCTION_ONLY — false security claim. Fix: reword to advisory-only.
- [MAJOR] Permission — mkdir required by flow (line 114) absent from enforced allowlist; settings.json allow (lines 5-22) omits mkdir/date/ls/rg/fd/git grep/test/git rev-parse. Fix: add to allow or route folder creation through allowlisted path.
- [MAJOR] Handoff — Final Output emits OpenCode command "implementer means implementer agent handoff using OpenCode command: /implement" (line 250). Claude has no /implement. Fix: prose recommendation.
- [MINOR] Brevity — write-is-available reassurance restated 4-5x (73,75,80,115,246).
- [Observation] 250 body lines; frontmatter valid Claude schema.

Strengths: bounded docs/tickets mission; strong failure/loop routing (3-attempt stop, needs-delete/rename-approval, partial-state archive matrix); testable output (mandatory re-read, observable ACs).
Top fix: correct the false write-enforcement claim (line 73 + 61/79/80).
Proposed: risk_level: high, decision: needs_refactor. Template: core/agents/architecture-plan-writer.md + .claude/settings.json.

## agent-fleet-assessor.md — 60 / blocked

Score table: Frontmatter 80, Role 55, Permission 35, Instruction 60, Handoff 85,
Evidence 88, Brevity 70, Runtime 75. Rubric 64.3; blocker cap (core mission unperformable) → final 60.

Findings:
- [BLOCKER] Permission — core mission needs task delegation (line 50 "delegate one file at a time to agent-critic"; line 74 "each agent-critic call is a separate task invocation"); frontmatter tools (line 4) excludes Task, and Claude subagents cannot spawn subagents. Every run hits "task tool is unavailable" stop (line 288). Fix: do not render to .claude/agents (scope OpenCode-only), OR grant Task and verify nested delegation before shipping.
- [MAJOR] Instruction — agent-critic dependency check uses OpenCode paths (ls .opencode/agents/, lines 83-85) inside a Claude copy; produces false "blocked: agent-critic unavailable" though agent-critic IS callable at .claude/agents/. Fix: make callability probe runtime-relative.
- [MAJOR] Role — orchestrator whose only value is delegation, shipped to a runtime blocking delegation → UNDERPOWERED, self-terminates.
- [MINOR] Brevity — formula meta-commentary restates itself (lines 171-172).

Strengths: reliable aggregation off machine-readable SCORE/READINESS/json values (refuses prose parsing); clean stop conditions + no-softening rule (278-293); dynamic core-roster derivation (200-202).
Top fix: resolve the delegation gap (descope to OpenCode-only OR grant/verify Task).
Proposed: risk_level: medium, decision: block. Template: packages/ai-universal-rules/templates/**/agents/agent-fleet-assessor.md.

## bugfix.md — 41 / blocked

Score table: Frontmatter 45, Role 20, Permission 35, Instruction 25, Handoff 30,
Evidence 80, Brevity 65, Runtime 70. Total 41.5 → 41. 113 lines.

Findings:
- [BLOCKER] Role — fix-applying mission but Write/Edit denied (line 5) + plan mode (line 7); "apply the smallest safe fix" (line 104). OpenCode source grants edit on src/app/packages; Claude copy contradicts. UNDERPOWERED. Fix: decide role — restore path-scoped edit + settings.json scoping, OR rewrite plan-only.
- [BLOCKER] Handoff — no next-agent handoff; routing table (docs/ai/agents.md:46) names bugfix → reviewer; CLAUDE.md requires prose Recommended next step. Fix: add reviewer handoff.
- [BLOCKER] Instruction — body asserts non-existent Claude "native path-scoped edit: permission" (line 89). Fix: Claude-accurate mechanism or remove.
- [MAJOR] Permission — ai-edit.sh/ai-rollback.sh (line 87), session-checkpoint.sh (line 88) absent from allowlist (lines 21-65). Fix: add or delete.
- [MINOR] Brevity — rename/delete policy (91-98) inert given no edit path.
- [MINOR] Instruction — verify-change (line 107) not in any roster.

Strengths: strong evidence discipline (line 113); deny-by-default Bash w/ destructive denials (67-68); minimal-fix framing (100-105).
Top fix: reconcile edit capability with mission.
Proposed: risk_level: high, decision: block. Template: optional/agents/bugfix.md.
Fleet note: docs/ai/agents.md:19 labels bugfix "GitHub-only" yet ships to .claude/.github/.opencode — recommend workflow-auditor.

## upgrade.md — 38 / blocked

Score table: Frontmatter 55, Role 25, Permission 40, Instruction 30, Handoff 20,
Evidence 55, Brevity 65, Runtime 55. Rubric 41.25 → capped 38 (flat contradictions + BLOCKERs, band 20-39).

Findings:
- [BLOCKER] Role — frontmatter denies mission: "Plan or apply dependency and platform upgrades" (line 3) vs Write/Edit denied (line 5) + plan (line 7); "Write tier" (line 79); "native path-scoped edit:" (line 84). Cannot "apply". Fix: pick posture — plan-only (change description, delete write prose) OR apply-capable (remove plan, drop Edit from disallowed, add scoped edit grant).
- [BLOCKER] Handoff — no Recommended next step, no next agent named. Fix: add handoff to reviewer/bugfix.
- [MAJOR] Permission — ai-edit.sh/ai-rollback.sh/session-checkpoint.sh (line 84) absent from allowlist (lines 21-66). Fix: add or delete.
- [MAJOR] Frontmatter — GENERATED header cites wrong origin "core/agents" (line 12); real is optional/. Fix: correct.
- [MAJOR] Instruction — references non-existent Claude edit: permission (line 84). Fix: remove/replace.
- [MINOR] Brevity — vague "upgrades carefully" (line 3).

Strengths: deny-by-default allowlist (21-69) + "settings.json wins" note (71-73); rename/delete stop-reports (88-96); risk_level: critical correct.
Top fix: pick plan-only vs apply posture; align frontmatter+body.
Proposed: risk_level: critical, decision: block. Template: optional/agents/upgrade.md.
Fleet note: docs/ai/agents.md:32 marks upgrade "GitHub-only" yet .claude/.opencode copies exist — recommend workflow-auditor.

## build-config.md — 35 / blocked

Score table: Frontmatter 30, Role 20, Permission 40, Instruction 20, Handoff 10,
Evidence 55, Brevity 70, Runtime 55. Total 34.75 → 35. 102 lines.

Findings:
- [BLOCKER] Role — write mission "Update build, packaging, or verification configuration" (line 3) but Write/Edit denied (line 5) + plan (line 7). Cannot perform core task. UNDERPOWERED. Fix: decide archetype — edit allowlist scoped to build paths + remove plan, OR rewrite advisory-only.
- [BLOCKER] Instruction — body claims "native path-scoped edit: permission" (line 87) not granted by Claude adapter; "Write tier. Use:" (line 79). Fix: remove edit: claims or render actual allowlist.
- [BLOCKER] Handoff — no next agent, no structured handoff, no named failure path; "escalate" (line 101) names no roster agent. Fix: add Recommended next step (release-auditor/reviewer) w/ payload.
- [MAJOR] Permission — ai-edit.sh/ai-rollback.sh/session-checkpoint.sh (line 84) absent from allowlist (lines 21-66). Fix: add or delete.
- [MAJOR] Frontmatter — agent_assessment decision: needs_refactor (line 10) understates; should be block.
- [MINOR] Brevity — rename/delete + bash policy blocks inlined verbatim.

Strengths: deny-by-default Bash w/ destructive denylist (68-69); secret-scoped verify (VERIFY_SECRETS=0) + reversibility (58, 99); rename/delete gates w/ stop codes (88-95).
Top fix: resolve write-mission vs write-denied contradiction (lines 3,5,7 vs 79/84/87).
Proposed: risk_level: high, decision: block. Template: core/agents/build-config.md.

## docs.md — 34 / blocked

Score table: Frontmatter 30, Role 15, Permission 30, Instruction 20, Handoff 30,
Evidence 55, Brevity 65, Runtime 60. Total 33.75 → 34. validate-agent-assessment.php OK. Semantic BLOCKER caps below 40.

Findings:
- [BLOCKER] Role/permission — writer mission "Update or align documentation" (line 3), "Write tier (docs only)" (line 70) but Write/Edit denied (lines 5,7). Copilot grants edit/editFiles/createFile; OpenCode grants edit: docs/**: allow; Claude denies all writes → cannot perform core action. Adapter drift. Fix (template + Claude renderer): tools: Read,Grep,Glob,Bash,Edit; drop Edit from disallowedTools; non-plan permissionMode.
- [BLOCKER] Instruction — claims Claude permission that does not exist: "native path-scoped edit:" (line 77); ai-edit.sh/ai-rollback.sh (line 75). Fix: Claude-accurate wording naming Edit tool.
- [MAJOR] Permission — ai-edit.sh/ai-rollback.sh/session-checkpoint.sh (line 75) absent from allowlist (lines 21-57); "Do not run commands not in this list" (line 59). Fix: add or delete.
- [MAJOR] Handoff — no next-agent handoff. Fix: add reviewer / workflow-auditor routing.
- [MINOR] Token economy — verbatim shared policy blocks (79-86 + Bash policy).

Strengths: deny-by-default Bash + explicit Do-not-run destructive list (59-60); four grounding rules (90-93) + doc-drift checks (ai-doc-check.sh, check-file-refs.sh); scoped read tooling.
Top fix: grant scoped Edit (+ leave plan mode) to match writer role.
Proposed: risk_level: medium, decision: block. Template: optional/agents/docs.md.
Fleet note: Claude edit-denied vs Copilot/OpenCode edit-allowed for same docs id — recommend workflow-auditor for cross-runtime drift.

---

# Fleet-Wide Analysis

## Cross-cutting issues
1. **Write-mission vs write-denied contradiction** (dominant blocker): docs, build-config, upgrade, bugfix describe edit/apply missions but ship `disallowedTools: Write, Edit` + `permissionMode: plan`. Claude-render adapter drift from OpenCode/Copilot sources that grant edit.
2. **Leaked OpenCode grammar into Claude copies**: phantom "native path-scoped edit: permission" (build-config, refactorer, config-maintainer, bugfix, upgrade, docs, implementer, architecture-plan-writer) and OpenCode commands /review-diff, /implement (researcher, implementer, architecture-plan-writer).
3. **Script Access ↔ Bash Command Policy mismatch** (~14 agents): ai-verify.sh, ai-edit.sh, ai-rollback.sh, session-checkpoint.sh, pack-context.sh cited as ask-tier in prose but absent from enforced allowlist; Claude has no ask tier.
4. **Unenforced write boundaries + secret-exposing raw readers**: head/tail/jq/yq/bat allowlisted alongside bounded preview-file.sh; write guards asserted "enforced" while .claude/settings.json has no Edit/Write deny rules.
5. **Recurring metadata defects**: wrong GENERATED-header template tier (core/ cited for optional agents) + stale agent_assessment.decision values inconsistent with score.

## Top 3 fleet-wide fix priorities (by impact)
1. **Fix Claude write-role rendering** (unblocks 4-6 agents): decide per-agent posture; grant scoped Edit + drop plan mode for genuine writers, or strip edit/rename/delete prose for plan-only agents. Fix at template sources, then re-render.
2. **Purge OpenCode grammar from Claude renders**: remove phantom edit: language and OpenCode slash-commands fleet-wide; replace with Claude-accurate wording + prose handoff sentences.
3. **Reconcile Script Access with Bash allowlist + enforce write denies in .claude/settings.json**: add ask-tier scripts to allowlists or delete refs; add Edit/Write deny rules for docs/ai/generated/**, lock files, .env/*.pem/*.key.

All fixes target template sources or settings.json — never the generated .claude/agents/ copies.
