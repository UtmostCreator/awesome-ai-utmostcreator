# Architecture Plan — ai-search External / Outside-Root Access (DEFERRED, SECURITY-GATED)

> RELEASE-SAFETY REVIEW RECORDED (satisfies item 105a / AC-01), verdict **DO-NOT-SHIP-YET**.
> Reviewer (release-auditor) found two blocking facts that INVERT this plan's framing:
> 1. **`external_directory: ask` is NOT in the bash enforcement path.** It is an OpenCode
>    runtime key (`opencode.jsonc:224`) that does not fire for an allow-listed
>    `bash scripts/ai/ai-search.sh *` invocation. Empirically, `ai-search files opencode /tmp`
>    returned `/tmp` files with `status: ok` and no prompt. The plan's "respects
>    external_directory: ask" guarantee is therefore false/unverifiable at the script layer.
> 2. **ai-search is ALREADY outside-root capable with no gate.** `interpret_positionals`
>    (`internal/search/35-parse-positionals.sh:17-83`) + `canonical_root`
>    (`internal/search/40-output-json.sh:90-100`) accept any `root` with no confinement; only
>    git-backed modes call `require_git_root` (`60-guards.sh:60-63`). `test-ai-search.sh:399`
>    already asserts `ok` for an outside `mktemp` dir. So AC-02 ("default stays inside-root")
>    is NOT currently true — item 105 is not additive.
> 3. **ai-search has NO secret/sensitive block in its own pipeline** (those guards live in
>    ai-verify/ai-edit via `internal/lib/70-secrets.sh`, never called by search). Only weak
>    output text-redaction exists (`lib/10-json.sh:15-26`).
>
> REQUIRED PRE-SHIP (blocking): (a) establish inside-root confinement default at the single
> root-resolution chokepoint; (b) add an in-script secret/sensitive-path block for ALL reads;
> (c) emit `blocked`/`unsafe_blocked` for un-flagged outside-root; (d) stop claiming the host
> prompt is the gate. Rollback/disable: `AI_SEARCH_ALLOW_OUTSIDE_ROOT` default-off + revert commit.
> Success signal: a test where outside-root WITHOUT the flag returns `blocked` (today it returns `ok`).
>
> NEXT STEP: route to ARCHITECT to re-sequence Plan F (confinement default + secret guard as
> prerequisites). Do NOT implement against the plan as written.

- Ticket: none
- Source: decomposition of `docs/tickets/arch-todo-repo-cleanup-shipping-surface-20260614-101701/plan.md`, with binding user decisions
- Generated: 20260614-104818
- Plan folder: docs/tickets/arch-todo-ai-search-external-access-20260614-104818/
- Status: **DEFERRED** — Todo (unchecked); backlog plan, written now, security-gated
- Decomposition role: backlog plan (Plan F)
- Rank: item 105
- Dependency: **Plan A committed** + a release-safety review
- Risk: **HIGH** (security boundary change)

## Context

ai-search currently operates inside the repository root. Item 105 proposes an `external-*` / `--allow-outside-root` family of modes/flags that would let ai-search read paths outside the project root. This crosses the project's external-directory boundary (OpenCode `external_directory: ask`) and the repo's read-only inspection policy, so it is HIGH risk and approval-gated.

## Problem

There is no supported, safe way for ai-search to inspect explicitly-approved external paths; doing so naively would silently widen the read boundary, bypass the `external_directory: ask` prompt, and risk reading secrets or unrelated projects. Any implementation must keep inside-root as the default and gate outside-root access behind explicit approval.

## Target Outcome

ai-search can, only when explicitly opted in and approved, read a named external path; the default remains strictly inside-root; the feature is disableable/flag-gated; and the security boundary is documented, tested, and reviewed under release-safety.

## In Scope

- 105: Add the `external-*` mode / `--allow-outside-root` flag family as an opt-in, approval-gated capability, with inside-root remaining the default.

## Out Of Scope (Things To Avoid)

- The read-only shortcut modes (Plan E, item 100).
- Making outside-root the default or removing the `external_directory: ask` prompt.
- Any write/mutation outside the project root.
- Reading secrets or sensitive globs even when outside-root is enabled.

## Affected Paths

- `scripts/ai/internal/search/**` (scope/guard modules, e.g. `55-scope-args.sh`, `60-guards.sh`).
- `scripts/ai/ai-search.sh` facade (flag parsing/help).
- Policy/guard surfaces that enforce root boundaries (`scripts/ai/internal/lib/50-policy.sh` and related).
- `docs/ai/tools/ai-search.md` and external-access policy docs.
- Tests covering the boundary (new boundary/guard tests).

## Contracts And Boundaries

- Default behavior is unchanged: ai-search stays inside the project root unless explicitly opted in.
- Outside-root access requires an explicit flag AND respects `external_directory: ask`; it must not bypass the runtime permission prompt.
- Secret/sensitive-file rules still apply; outside-root never overrides them.
- This is HIGH risk and requires a release-safety review before implementation (per dependency).
- A disable/feature-flag path must exist so the capability can be turned off without reverting unrelated code.

## Todo Plan

- [ ] 105a: Run a release-safety review of the external-access boundary BEFORE implementation; record the rollback/disable path and success signal.
- [ ] 105b: Define the opt-in surface (`external-*` mode and/or `--allow-outside-root`) with inside-root default and explicit approval requirement.
- [ ] 105c: Implement the guard so outside-root reads require the flag and respect `external_directory: ask`; secrets/sensitive globs stay blocked.
- [ ] 105d: Add boundary tests (default stays inside-root; flag required; secrets blocked even when enabled).
- [ ] 105e: Document the capability and its security caveats in `docs/ai/tools/ai-search.md` and the external-access policy.

## Acceptance Criteria

- [ ] AC-01: A release-safety review is recorded with rollback/disable path and post-deploy success signal before implementation begins.
- [ ] AC-02: Default ai-search behavior is unchanged and stays inside the project root (proven by a test).
- [ ] AC-03: Outside-root reads require the explicit flag/mode and respect `external_directory: ask`; without it, access is blocked.
- [ ] AC-04: Secret/sensitive-file rules still block access even when outside-root is enabled (proven by a test).
- [ ] AC-05: A disable/feature-flag path exists and is documented; `composer test:fast` passes.

## Verification Plan

- `bash tests/scripts/ai/test-ai-search.sh` (and new boundary tests) — proves default inside-root and flag-gated outside-root behavior (AC-02, AC-03, AC-04).
- `AI_OUTPUT=json bash scripts/ai/ai-search.sh text <q> <outside-path>` without the flag — confirms it is blocked (AC-03).
- `bash scripts/ai/preview-file.sh docs/ai/tools/ai-search.md` — confirms documented capability + disable path (AC-05).
- `composer test:fast` — regression smoke (AC-05).
- Release-safety review artifact — confirms AC-01.

## Risks And Rollback

- Risk (HIGH): silently widening the read boundary or bypassing `external_directory: ask`. Mitigation: opt-in only; default inside-root; respect the runtime prompt; boundary tests.
- Risk: reading secrets via an external path. Mitigation: secret/sensitive rules enforced regardless of outside-root.
- Risk: feature cannot be disabled if it misbehaves in the field. Mitigation: feature-flag/disable path required (AC-05).
- Rollback: disable via the feature flag (default stays inside-root); if needed, revert the capability commit.

## Handoff Notes

- DEFERRED and SECURITY-GATED: do not implement before Plan A lands AND a release-safety review is complete.
- Inside-root is the default; outside-root is opt-in, approval-gated, and prompt-respecting.
- Secrets/sensitive rules are never overridden by outside-root access.
- implementer means implementer agent handoff using OpenCode command: /implement
