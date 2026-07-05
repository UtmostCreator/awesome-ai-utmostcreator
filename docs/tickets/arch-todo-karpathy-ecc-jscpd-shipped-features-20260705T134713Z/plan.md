# Arch Todo: Karpathy Parity + ECC-Inspired Shipped Features + jscpd

- Ticket slug: `arch-todo-karpathy-ecc-jscpd-shipped-features-20260705T134713Z`
- Status: `RESEARCH FINDINGS — plan only, nothing implemented`
- Author: implementer agent (read-only research handoff)
- Risk: `medium` (touches shipped adapter/template surfaces + ai-verify; no runtime/data change)
- Method: two parallel researcher agents (internal Karpathy audit + external ECC assessment)
  + direct verification of `ai-verify.sh`, `policies/ai-file-standards.json`, pack lock/catalog.

---

## 0) Scope Of This Research

Answer four user questions with evidence:

1. Is the **Karpathy Guidelines** behavior already covered in shipped agents/instructions/prompts/skills?
2. What in **ECC** (`.opencode`, `agents`, `plugins`, `hooks`, `examples`, `contexts`) is worth
   shipping to Copilot + OpenCode + Claude Code targets?
3. Can we let **users add their own pattern/example files that agents auto-pick-up**?
4. Wire **jscpd** (https://github.com/kucherenko/jscpd) into `ai-verify` with user-tunable thresholds.

---

## 1) Karpathy Guidelines — Coverage Audit

Only content under `packages/ai-universal-rules/templates/**` propagates to other projects.
Everything in `docs/ai/**` (except what the installer copies) is local-only.

| Pillar | Shipped coverage | Evidence |
|---|---|---|
| **1. Think Before Coding** | **FULL** | `templates/snippets/behavioral-baseline.snippet.md:7` "Ask instead of guessing... do not invent new conventions"; `templates/capabilities/clarification-and-handoff/CAPABILITY.md:56-67` (present interpretations, simpler-path pushback); `AGENTS.template.md:32,42-43` |
| **2. Simplicity First** | **PARTIAL** | Generic "smallest safe change" (`execution-protocol.template.md:7`, `behavioral-baseline.snippet.md:9-10`). **Missing:** "no error handling for impossible scenarios" and "rewrite 200 lines to 50" (grep-confirmed absent). |
| **3. Surgical Changes** | **PARTIAL** | "surgical, task-scoped... no drive-by" (`behavioral-baseline.snippet.md:11-12`), slice cap (`AGENTS.template.md:45`). **Missing:** "match existing style", "mention don't delete unrelated dead code", "remove only orphans YOUR change created", "every changed line traces to the request" (all grep-confirmed absent). |
| **4. Goal-Driven Execution** | **FULL** | `execution-protocol.template.md:44` "restate the request as a verifiable goal... 'add validation' becomes 'write tests... then make them pass'"; verification statuses + test-first ordering. |

### 1a) Critical shipping gap (highest-value, low-risk fix)

`templates/snippets/anti-pattern-examples.md` **exists** (byte-identical to `docs/ai/snippets/anti-pattern-examples.md`) and holds the richest concrete Karpathy coverage (Overcomplication / Drive-By / Hidden Assumptions / Weak Success Criteria). But:

- It is **referenced** by 3 shipped surfaces: `AGENTS.template.md:56`, `copilot-instructions.template.md:71`, `behavioral-baseline.snippet.md:24` ("See `docs/ai/snippets/anti-pattern-examples.md`").
- It is **NOT** in `packages/ai-universal-rules/package-lock.ai.json`, `manifest.json`, or `catalog.json`
  (verified: `rg -l anti-pattern-examples` over those three files → exit 1, no matches).
- **Consequence:** installed target repos get a **dangling reference** — the file the shipped
  policy points at may never be delivered. Fixing this alone closes most of the Pillar 2/3 gap.

### 1b) Karpathy work items

- P0: Add `anti-pattern-examples.md` to manifest/catalog/lock (or confirm+fix the directory-copy
  rule). Closes dangling reference. **Lowest risk, highest payoff.**
- P1: Add the 5 missing sub-rules to `behavioral-baseline.snippet.md` (the byte-parity source for
  AGENTS.template.md + copilot-instructions.template.md, so one edit propagates to all three):
  match-existing-style; mention-don't-delete unrelated dead code; remove only orphans your change
  created; every changed line traces to the request; no error handling for impossible scenarios.
- Note: keep it thin — these are one-line rules, not a new capability.

---

## 2) ECC Architecture — What's Worth Shipping

ECC = "Everything Claude Code" (`affaan-m/everything-claude-code`). Portable IDEAS below,
mechanism adapted per runtime (matches our `docs/ai/adapter-contract.md`).

| ECC area | Reusable idea | Portability | Our analogue / gap |
|---|---|---|---|
| `.opencode/` + `MIGRATION.md` | One canonical catalog rendered per-runtime with an explicit Claude↔OpenCode concept map | High | We already do this; their `MIGRATION.md` map ≈ our `integration-matrix.md` |
| `agents/` (67) | **Paired per-stack agent template** (`{lang}-reviewer` + `{lang}-build-resolver`); **shared prompt-defense preamble**; **confidence-gated review + false-positive skip list + "zero findings is valid"** | Very high (prose) | We have reviewer/refactorer but **no per-stack pairs**, **no prompt-injection preamble**, **no confidence/FP-gate** in review agents |
| `plugins/README.md` | Marketplace/plugin distribution | Low (Claude-CLI-specific) | Our `.ai-install-manifest.json` + pack registry already covers the concept |
| `hooks/` + `memory-persistence/` | **Enforcement at tool boundary, not prompts** ("LLMs forget ~20%"); **profile-tiered + env-disableable** (`minimal\|standard\|strict`, `ECC_DISABLED_HOOKS`); **contract/impl split**; **cross-session memory** | Concept high, mechanism Claude-specific | We have `pre/post-tool-use.sh` + `.ai-logs`; **gaps:** no profile tiers, no env-disable list, no fact-forcing gate, no cross-session memory |
| `examples/` (per-stack CLAUDE.md gallery) | **Golden config per stack**, fixed section skeleton, adopted by copy | High (content) | We ship per-runtime adapters but **no stack-specific golden-config gallery** |
| `contexts/` (dev/research/review) | **Named swappable operating modes** — small behavior+tool-preference files loaded per session | High (concept) | We express these as researcher/implementer/reviewer agents already |

### 2a) Highest-value ECC adoptions (ranked)

1. **Prompt-defense preamble** injected into every write/review agent (runtime-agnostic prose,
   trivially portable, real security value). ECC: `architect.md:8-15`, `code-reviewer.md:8-15`.
2. **Confidence-gated review** ("zero findings is valid" + explicit false-positive skip list) added
   to our reviewer/repository-reviewer — directly reduces reviewer noise. ECC: `code-reviewer.md:29-112`.
3. **jscpd duplication gate** (see §4) — our refactorer/reviewer already *talk about* duplication;
   this makes it mechanical.
4. **User-defined example/pattern auto-discovery** (see §3).
5. **Hook profile tiers + env-disable** for our `pre/post-tool-use.sh` (nice-to-have).

---

## 3) User-Defined Patterns That Agents Auto-Pick-Up

ECC has NO `examples/` auto-loader (grep of `.opencode/` for `examples/` = empty). Its working
auto-discovery primitive is the `ck` skill: a `SessionStart` hook scans a registry
(`~/.claude/ck/projects.json`) and injects a **bounded (~100-token)** `CONTEXT.md` per session
(`skills/ck/SKILL.md:22-27,123-139`).

### Portable design for THIS kit

1. **Convention'd dir + frontmatter**: users drop `*.md` into e.g. `docs/ai/patterns/` (or
   `.ai/patterns/`) with frontmatter: `name`, `appliesTo: ["**/*.tsx","next"]` (glob/stack tags),
   `scope: project|global`, `priority`.
2. **Discovery step** enumerates the dir, filters by detected stack + changed-file globs, injects
   only matching **bounded** snippets (cap total chars, rank by priority — copy ECC's bounding).
3. **Per-runtime wiring** (one source, three loaders):
   - Copilot → generate `.github/instructions/<name>.instructions.md` with `applyTo:` glob (deterministic).
   - OpenCode → append matching paths to `instructions[]` in opencode config (or a plugin session handler).
   - Claude → SessionStart hook / `CLAUDE.md` include.
4. **Guardrail**: bound injected chars; never blow the context window.

This reuses our existing capability-routing + adapter-render model; the new piece is the
discovery/selection + bounded injection.

---

## 4) jscpd In ai-verify (explicit user request)

### Current state (verified)

- `ai-verify.sh` modules: `20-shipped-filters`, `10-scope`, `30-linecount`, `40-step-runner`,
  `90-run`. **No duplication check exists.**
- `jscpd` is already known here: v0.6 plan used `npx --yes jscpd` **ad-hoc, user-approved,
  on-demand** (`v0.6-plan/.../plan.md:635-666`) — not wired into any script. Result then:
  0 exact clones, 0.00% duplicated lines. Note recorded: jscpd's markdown reporter only tokenizes
  fenced code blocks (not prose) — so it measures code/example-fence duplication, not prose.
- The existing **line-count gate** (`30-linecount.sh`) is the structural template to mirror:
  tiered thresholds via env vars (`LINECOUNT_INFO=350`, `LINECOUNT_WARN=550`, `LINECOUNT_ERROR=800`),
  info/warn as advisory, error increments `$failures`.

### Proposed design (mirror the line-count module)

- New module `scripts/ai/internal/ai-verify/35-jscpd.sh`, gated behind
  `command -v jscpd` OR `npx jscpd` availability, opt-in like link/secret checks.
- Env-tunable thresholds (user-changeable, matching existing convention):
  - `VERIFY_JSCPD=0|1` (default 0 = off, opt-in — same posture as `VERIFY_LINKS`).
  - `JSCPD_THRESHOLD` (max % duplicated lines before WARN; default e.g. 5).
  - `JSCPD_MIN_TOKENS` (jscpd `--min-tokens`, default 50).
  - `JSCPD_FAIL_THRESHOLD` (% that increments `$failures`; default e.g. 10, empty = never fail).
  - `JSCPD_PATHS` (scan roots; default scope-aware changed files).
- Use jscpd JSON reporter; parse `statistics.total.percentage`; emit INFO/WARN/FAIL by tier
  exactly like `30-linecount.sh`.
- Register in `docs/ai/validation.md` + script registry; add a bats test under `tests/shell/`.
- Honesty note to document: jscpd markdown only tokenizes fenced blocks (known limitation).

---

## 5) Which Agents Benefit From Which Change

| Change | Primary agents | Why |
|---|---|---|
| anti-pattern-examples shipping fix (§1a) | ALL (baseline snippet) | Fixes dangling ref every installed target inherits |
| 5 missing Karpathy sub-rules (§1b) | implementer, super-implementer, refactorer, bugfix (write); reviewer, repository-reviewer (check) | Surgical-change + simplicity rules are write-time + review-time |
| Prompt-defense preamble (§2a.1) | ALL write/review agents | Runtime-agnostic security guardrail |
| Confidence-gated review + FP list (§2a.2) | reviewer, repository-reviewer, release-auditor | Cuts reviewer false-positive noise |
| jscpd gate (§4) | refactorer (primary — duplication is its job), reviewer, release-auditor | Mechanical duplication proof |
| User pattern auto-discovery (§3) | implementer, super-implementer, architect (consume examples); workflow-auditor (validate wiring) | Lets user conventions steer agents |
| Hook profile tiers / env-disable (§2a.5) | (infra) config-maintainer, workflow-auditor | Tunable enforcement |
| Named contexts / swappable modes (§2, C) | already covered by researcher/implementer/reviewer split | Low marginal value |

---

## 6) Things To Avoid

- Do NOT bake stack-specific or tool-specific claims into universal templates
  (`AGENTS.template.md` ships to every target — no false "this project has X" assertions).
- Do NOT duplicate Karpathy sub-rules across many agents — put them in `behavioral-baseline.snippet.md`
  (byte-parity source) and let render propagate.
- Do NOT make jscpd a hard default-on failure — opt-in + tunable, like `VERIFY_LINKS`/`VERIFY_SECRETS`.
- Do NOT hand-edit rendered adapters; edit templates then re-render + run `validate-adapter-drift.php`.
- Do NOT trust ECC's own counts (agents "26" vs 13 vs 12 across files) — mirror behavior, not tallies.
- Do NOT unbounded-inject user pattern files — cap chars, rank by priority (ECC's own guardrail).

---

## 7) Acceptance Criteria (per workstream, if implemented)

- [ ] Karpathy: `anti-pattern-examples.md` in manifest+catalog+lock; no dangling ref; catalog validators green.
- [ ] Karpathy: 5 sub-rules added to `behavioral-baseline.snippet.md`; adapter drift clean; byte-parity holds.
- [ ] ECC preamble + confidence-gate added to relevant agent templates; re-rendered; drift clean.
- [ ] jscpd: `35-jscpd.sh` opt-in, env-tunable, mirrors line-count tiers; registered; bats test passes.
- [ ] User patterns: `docs/ai/patterns/` convention + discovery + bounded per-runtime injection; workflow-auditor validates.
- [ ] `composer test` green; verification reported honestly.

---

## 8) Open Questions For User

1. jscpd defaults: opt-in (`VERIFY_JSCPD=0`, like links) or on-by-default? And numeric
   threshold defaults (WARN % / FAIL %) you want shipped?
2. User pattern dir: `docs/ai/patterns/` vs `.ai/patterns/`? Project-scope only, or also global?
3. Per-stack golden-config gallery (ECC `examples/`) — in scope, or defer? Which stacks first
   (PHP/Laravel given this repo, Next.js, Go, Rust)?
4. Prompt-defense preamble — adopt ECC's wording or author our own?
5. Should this be split into separate tickets per workstream (Karpathy / ECC-agents / jscpd /
   user-patterns) since they are independent and differently-risked?
