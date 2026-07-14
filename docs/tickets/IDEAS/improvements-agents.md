ensure each agents now its limits and wont try to edit if it is read only and wont waste token attempting it.
ensure agent iteraction is good btwn each other asp hands off process one agents prepares for the other and they must expect something prepared from previous agent if such scenario exists. this should increase understanding and it should prevent context loss

## Overall score

**84/100 — strong but not fully production-safe**

Benchmark:

|  Score | Meaning                                                          |
| -----: | ---------------------------------------------------------------- |
| 90–100 | Production-grade agent chain; low token waste; clear enforcement |
|  80–89 | Strong; usable with several hardening gaps                       |
|  70–79 | Works but will still loop, over-read, or attempt blocked actions |
|    <70 | Unreliable handoff / weak boundaries                             |

Expected score after fixes below: **91–93/100**.

---

## Pair score: architect → architecture-plan-writer

| Area                       |      Score | Notes                                                                                                       |
| -------------------------- | ---------: | ----------------------------------------------------------------------------------------------------------- |
| Role separation            | **88/100** | Architect is clearly read-only; writer is clearly plan-only.                                                |
| Tool-limit awareness       | **81/100** | Good prose rules, but hard enforcement depends on `.claude/settings.json` / hooks.                          |
| Token-waste prevention     | **78/100** | Some stop conditions exist, but writer may still re-inspect too much instead of trusting architect handoff. |
| Handoff quality            | **84/100** | Architect says what to pass; writer says what to expect. Missing strict machine-readable handoff envelope.  |
| Context-loss prevention    | **82/100** | Good section structure, but no required handoff checksum / scope ID / AC mapping validation.                |
| Safety against wrong edits | **85/100** | Writer is limited by instruction, but `Write/Edit` path scope is advisory unless runtime enforces it.       |

---

# Major issues

## 1. Bash remains the biggest escape hatch

### Problem

Both agents have `Bash`.

The body says:

> hard enforcement depends on `.claude/settings.json` or runtime hooks

That means the instructions are good, but not sufficient alone. A model can still attempt:

```bash
cat > file
tee file
python -c 'write file'
php -r 'file_put_contents(...)'
sed -i
```

You already block some dangerous commands, but the policy still depends on external enforcement.

### Impact

**Risk: high**

Architect is supposed to be read-only, but if Bash is not strongly blocked, it can still mutate files indirectly.

### Fix

For **architect**, enforce:

```text
Bash write-class commands: deny
Shell redirection: deny
tee: deny
cat >: deny
sed -i: deny
perl -pi: deny
python/php/node/ruby file writes: deny
cp/mv/rm/chmod: deny or ask
```

Architect should have **read-only Bash only**.

---

## 2. Writer write-scope is not hard enough in the agent definition

### Problem

`architecture-plan-writer` has:

```yaml
tools: Read, Grep, Glob, Bash, Write, Edit
permissionMode: default
```

The body says write only under:

```text
docs/tickets/**
```

But if the runtime does not support path-scoped native edits, this becomes advisory.

### Impact

**Risk: high**

The writer could accidentally edit source/config files if the runtime permits it.

### Fix

Hard-enforce in generated `.claude/settings.json`:

```json
{
  "permissions": {
    "allow": [
      "Write(docs/tickets/**)",
      "Edit(docs/tickets/**)",
      "Bash(git status*)",
      "Bash(git branch*)",
      "Bash(git rev-parse*)",
      "Bash(ls*)",
      "Bash(fd*)",
      "Bash(rg*)",
      "Bash(mkdir -p docs/tickets/*)",
      "Bash(date*)"
    ],
    "deny": [
      "Write(**)",
      "Edit(**)",
      "Bash(* > *)",
      "Bash(* >> *)",
      "Bash(tee *)",
      "Bash(cat > *)",
      "Bash(cp *)",
      "Bash(mv *)",
      "Bash(rm *)"
    ]
  }
}
```

Equivalent policy depends on your actual generator format, but the rule is: **native Write/Edit allowed only for `docs/tickets/**`; shell writes denied globally.\*\*

---

## 3. Missing strict architect → writer handoff schema

### Problem

Architect says it must pass:

- design
- non-goals
- steps
- ACs
- contracts
- paths
- verification
- risks

But there is no strict required shape.

### Impact

**Risk: high-medium**

The writer may:

- re-design because handoff is incomplete
- widen scope
- lose AC mapping
- create vague Todo items
- waste tokens searching the repo again

### Fix

Add a required handoff block to architect final output:

```md
## Plan Writer Handoff Envelope

- Handoff version: 1
- Source agent: architect
- Target agent: architecture-plan-writer
- Scope ID: {short stable id}
- Ticket: {id or none}
- Branch hint: {branch or unknown}
- Title: {plan title}
- Write target: docs/tickets/{branch-name}/plan-{n}-{short-desc}.md
- Scope status: complete | blocked
- In scope:
  - ...
- Out of scope:
  - ...
- Affected paths:
  - ...
- Source-of-truth files:
  - ...
- Contracts:
  - ...
- Ordered implementation steps:
  - ...
- Acceptance criteria:
  - AC-01 [{explicit|inferred|evidence-backed|negative}]: ...
- Verification mapping:
  - AC-01 -> command/inspection: ...
- Risks:
  - ...
- Rollback:
  - ...
- Diagram:
  - included | not required
- Next required agent: architecture-plan-writer
```

Then add to plan-writer:

```md
If `Plan Writer Handoff Envelope` exists, treat it as the primary source of truth. Do not re-design. Do not broaden scope. Only inspect branch/status/target numbering and read files needed to resolve the plan path.
```

---

## 4. Writer may still over-read and waste tokens

### Problem

Plan-writer has `Read`, `Grep`, `Glob`, `Bash`, plus references to canonical docs.

That is useful, but after architect handoff the writer should not perform broad discovery.

### Impact

**Risk: medium-high**

The writer may duplicate architect work.

### Fix

Add a token-saving rule:

```md
## Handoff-First Token Discipline

When invoked with an architect handoff, do not repeat architecture discovery. Limit inspection to:

1. `git status --short`
2. current branch
3. existing plan files under the target `docs/tickets/{branch-name}/`
4. the existing plan file when in Update Mode
5. only the specific referenced docs if the handoff is missing a required field

Do not run broad repo search unless the handoff is incomplete or contradictory.
```

This directly reduces wasted tokens.

---

# Medium issues

## 5. Architect should explicitly not call implementer directly

### Current state

Architect says:

> route first through the plan writer

Good.

### Gap

Because architect has `Agent`, it can technically delegate. Add an explicit hard rule:

```md
Do not hand off directly to implementer. Complete designs must go:
architect -> architecture-plan-writer -> implementer.
Only bypass plan-writer when the user explicitly says no plan file should be created.
```

Score impact: **+2 to +3 points**.

---

## 6. Plan-writer should validate architect handoff completeness before writing

Add this before writing:

```md
## Intake Validation

Before writing, confirm the handoff contains:

- bounded title
- in-scope items
- out-of-scope items
- affected paths
- contracts/boundaries
- ordered steps
- acceptance criteria
- verification plan
- risks/rollback

If any are missing, stop and return:
`architect means architect agent handoff`
with the missing fields.
```

This prevents the writer from inventing missing architecture.

---

## 7. AC mapping should be stronger

Architect already requires ACs and verification surfaces. Good.

But plan-writer should require this format:

```md
- [ ] AC-01: ...
  - Type: explicit | inferred | evidence-backed | negative
  - Proves: P0-01, P0-02
  - Verification: ...
```

Without this, ACs may become disconnected from tasks.

---

## 8. No explicit context-loss checksum

Add a small handoff integrity mechanism:

```md
- Handoff summary hash: {manual short slug or generated checksum if available}
- Scope lock: {one sentence describing the exact allowed scope}
```

Even without real hashing, a **scope lock sentence** helps:

```md
Scope lock: This plan only covers making read-only agents avoid write attempts and improving architect -> plan-writer handoff.
```

The writer must copy it into the plan.

---

## 9. Writer archive policy is safe but slightly awkward

The archive flow avoids shell move/delete. Good.

But tombstoning the original means active plan paths remain present. That is safer, but downstream agents must know tombstones are not active plans.

Add:

```md
When scanning for active plans, ignore files whose first line starts with `Archived:`.
```

---

# Minor issues

## 10. `Grep` tool conflicts with your preference for `rg`

You prefer enforcing `rg`.

The agents still list `Grep`.

### Fix

Where runtime supports it:

```yaml
tools: Read, Glob, Bash, Agent
disallowedTools: Write, Edit, Grep
```

Or keep `Grep` but add:

```md
Do not use native Grep unless `rg`/`ai-search.sh` is unavailable or blocked.
```

---

## 11. Architect has a lot of canonical references

This is good for correctness but can waste context.

Add:

```md
Do not load all canonical references by default. Load only files directly connected to the affected paths/contracts. Maximum initial reference load: 3 files unless evidence requires more.
```

---

## 12. Plan-writer `date *` is broader than needed

Prefer:

```text
date
```

or one brokered timestamp command.

`date *` is low risk, but unnecessary.

---

# Recommended final scores

## Current version

| Agent / chain                  |      Score |
| ------------------------------ | ---------: |
| Architect alone                | **87/100** |
| Architecture-plan-writer alone | **80/100** |
| Architect → writer chain       | **84/100** |

## After fixes

| Agent / chain                  |      Score |
| ------------------------------ | ---------: |
| Architect alone                | **92/100** |
| Architecture-plan-writer alone | **90/100** |
| Architect → writer chain       | **92/100** |

---

# Best minimal patch set

## P0 — must fix

1. Hard-deny shell write paths for architect.
2. Hard-scope writer `Write/Edit` to `docs/tickets/**`.
3. Add strict `Plan Writer Handoff Envelope`.
4. Add writer `Handoff-First Token Discipline`.
5. Add writer intake validation: if handoff incomplete, return to architect instead of guessing.

## P1 — should fix

1. Add explicit `architect -> writer -> implementer` routing lock.
2. Add AC-to-task verification mapping.
3. Add active-plan scan rule to ignore tombstoned archived plans.
4. Limit canonical reference loading to only needed files.

## P2 — nice to fix

1. Remove or discourage native `Grep`.
2. Narrow `date *` to `date`.
3. Add scope-lock sentence to every handoff and plan.

---

## Verdict

The design is **good and close to reliable**.

The main weakness is not the prose. The prose is strong. The weakness is **enforcement gap between agent instructions and runtime permissions**.

Best target state:

```text
architect:
  read-only by native tools
  read-only by Bash
  produces strict handoff envelope
  never writes plan
  never calls implementer directly

architecture-plan-writer:
  trusts architect handoff first
  writes only docs/tickets/**
  never designs
  never implements
  validates intake before writing
  reports exact next agent
```

With those changes, this pair should work well together at approximately **92/100**.
