# Arch Todo: Code-Quality Gate + Agent Rule Injection

- Ticket slug: `arch-todo-code-quality-gate-and-agent-rules-20260705T131645Z`
- Status: `PLAN ONLY — not implemented`
- Author: audit handoff (implementer agent, read-only)
- Risk: `medium` (touches shipped agent surfaces + adds a validator; no runtime/data change)
- Source evidence: `/home/utmostcreator/Projects/copy-paste/ai/agents with write perm.md`,
  `/home/utmostcreator/Projects/copy-paste/ai/default instruction bugs.md`,
  `tools/ai/validate-context-budgets.php`, `policies/ai-file-standards.json`

---

## 1) Problem Statement

Two governance rules the user expects are **not currently enforced** in this repo:

1. A **source-code file-size gate** with tiers: new file → error; existing file
   `350` → info, `450` → warning, `550+` → warning + refactor flag.
2. **Write-capable-agent code-quality rules** (LOC>500 → new class + refactor flag,
   max 3 returns/method, ≤2 nested ifs, complexity < 15, params < 5, "show remaining work").

Audit findings (grounded):

- `tools/ai/validate-context-budgets.php` only has **two tiers** (`warn_above` FAIL-less WARN,
  `fail_above` FAIL) and scans **AI workflow markdown only**, not source code. No `info` tier,
  no `refactor` flag, no new-file branch. `grep 550|450|350|500` across `*.php` = 0 hits.
- `policies/ai-file-standards.json` thresholds are **per-file-type**, not the universal
  350/450/550 the user described.
- No editing agent (`implementer`, `refactorer`, `super-implementer`, `bugfix`) carries the
  LOC/returns/nesting/complexity/params rules.
- Regression-test-first, stash-before-adding-tests, and reference-integrity **are** already
  shipped (in `docs/ai/execution-protocol.md` + agent verification gates) — not part of the gap.

---

## 2) Scope

In scope:

- New source-code size validator (or extension) implementing the 3-tier + new-file behavior.
- Injecting quantitative code-quality rules into write-capable agent templates + re-rendered adapters.
- A supporting policy block (thresholds) so numbers are data, not hardcoded.

Out of scope (separate tickets already in flight — do not fold in here):

- Agent permission rethink → `arch-todo-agent-permission-rethink-20260613T154104Z`
- Copilot hook / policy failures (`default instruction bugs.md` items 2, and hook denial in
  `failing copilot ai.md`)
- Infinite MD-rewrite loop root cause (baseline "never re-apply blocked edit" exists; deeper
  guard is a distinct investigation)

---

## 3) Workstreams

### P0 — Source-code size gate (validator + policy)

- Add a `source_line_limits` block to `policies/ai-file-standards.json` (or a new
  `policies/code-quality.json`) with: `info_above: 350`, `warn_above: 450`,
  `fail_above: 550`, `refactor_flag_above: 550`, plus code globs (`**/*.php`, etc.) and an
  allowlist for known-large generated/vendored files.
- New script `tools/ai/validate-code-size.php` (mirror the structure of
  `validate-context-budgets.php`) that:
  - emits `INFO`/`WARN`/`FAIL` + a `REFACTOR` flag line at the right tiers;
  - has a `--changed-only` / git-aware mode so **new files** (untracked/added) are treated
    as errors when over threshold, while existing files degrade to INFO first;
  - reuses `isGeneratedPath` / allowlist patterns already in the codebase.
- Register it in `docs/ai/validation.md` and the script registry.
- Tests: add `tests/php/CodeSizeValidatorTest.php` (new-file-errors, 350/450/550 tiers,
  allowlist skip, generated-path skip).

### P1 — Write-capable-agent code-quality rules

Inject a shared "Code Quality Constraints" block into the agent templates and re-render:

Rules to add (verbatim intent from `agents with write perm.md`):

- LOC > 500 in a touched unit → extract into new class(es) and flag for refactor.
- Max 3 returns per method.
- ≤ 2 nested `if` statements.
- Cyclomatic complexity < 15 per method.
- ≤ 5 parameters per method.
- Prefer readability (add lines when it helps) but never at the cost of performance.
- Always "show remaining work if any" in the handoff.

Placement decision (keep templates thin per `docs/ai/ai-file-standards.md`): put the full
rule text in a **capability** (e.g. `capabilities/code-quality-constraints/`) and have each
write-capable agent reference it, rather than duplicating 7 bullets across 4+ agents.

### P2 — Docs + adapter sync

- Re-render `.opencode/agents/*` and `.github/agents/*` from templates.
- Run `php tools/ai/validate-adapter-drift.php` to confirm no drift.
- Update `docs/ai/execution-protocol.md` verification ladder to mention the code-size gate.

---

## 4) Which Agents Benefit From Which Change

| Agent (template) | Code-size gate (P0) | Code-quality rules (P1) | Rationale |
|---|:---:|:---:|---|
| `implementer` | ✅ run as verification | ✅ **primary** | Writes new source; needs LOC/returns/complexity limits + new-file error awareness. |
| `super-implementer` (OpenCode) | ✅ | ✅ **primary** | Same as implementer, broader autonomy — highest benefit. |
| `refactorer` | ✅ run as verification | ✅ **primary** | Its whole job is structure; the 550→refactor flag and LOC>500→new-class rule are core to it. |
| `bugfix` (Copilot) | ✅ | ✅ | Bug fixes still add code; returns/nesting/complexity guards prevent regressions-by-bloat. |
| `config-maintainer` | ⚠️ partial | ➖ | Edits config, not classes; size gate useful for large config files, quality rules mostly N/A. |
| `architect` / `architecture-plan-writer` | ➖ | ✅ reference only | Should *plan for* the refactor flag and cite the constraints when scoping, not enforce at edit time. |
| `reviewer` / `repository-reviewer` | ✅ **as a review signal** | ✅ **as a review checklist** | Reviewers should flag violations even if the writer missed them — strong benefit for the INFO/WARN/REFACTOR tiers. |
| `release-auditor` | ✅ FAIL tier only | ➖ | Cares about the hard 550 FAIL as a release blocker, not the info tier. |
| `researcher` / `repository-researcher` | ➖ (read-only) | ➖ | No edits; no benefit. |
| `workflow-auditor` | ✅ validate the gate exists | ➖ | Audits that the new validator + rules are wired correctly. |
| `bootstrapper` / `post-install` | ➖ | ➖ | Install-time only. |

Legend: ✅ direct benefit · ⚠️ partial · ➖ not applicable.

Highest-value targets: **implementer, super-implementer, refactorer** (enforce at write time)
and **reviewer/repository-reviewer** (catch what slips through).

---

## 5) Things To Avoid

- Do NOT hardcode 350/450/550 in the validator — put them in policy JSON (data, not code).
- Do NOT duplicate the 7 quality bullets across every agent file — use one capability + references
  (respects `docs/ai/ai-file-standards.md` line budgets and adapter-thinness contract).
- Do NOT apply the source-size gate to markdown (that stays with `validate-context-budgets.php`).
- Do NOT edit rendered adapters by hand — edit templates then re-render.
- Do NOT fold in the permission-rethink or Copilot-hook fixes; they have their own tickets.
- Do NOT weaken or delete the existing markdown budget validator.

---

## 6) Acceptance Criteria

- [ ] `validate-code-size.php` exists, is registered, and emits INFO@350 / WARN@450 /
      FAIL@550 + REFACTOR flag, with new-file-over-threshold → error.
- [ ] Thresholds live in policy JSON with an allowlist; no magic numbers in the script.
- [ ] `CodeSizeValidatorTest.php` passes and covers all tiers + new-file + allowlist + generated skip.
- [ ] Code-quality constraints exist as a capability and are referenced by
      implementer, super-implementer, refactorer, bugfix (write agents) and
      reviewer/repository-reviewer (review agents).
- [ ] Adapters re-rendered; `validate-adapter-drift.php` clean.
- [ ] `composer test` green; verification evidence reported honestly.

---

## 7) Open Questions For User

1. Should the source-size gate count **all lines** or **code lines only** (excluding blanks/comments,
   e.g. via `scc`)? `agents with write perm.md` says "LOC" which usually means code lines.
2. Which languages/globs are in scope for the gate? (`*.php` only, or also shell/JS/etc.?)
3. Should the `info` tier ever fail CI, or is it advisory-only (exit 0)?
4. Confirm the two thresholds meaning: is `450` the same as the LOC>500 "new class" trigger, or
   are those independent numbers (450 warn vs 500 new-class vs 550 refactor-flag)?
