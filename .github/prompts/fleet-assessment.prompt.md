---
name: fleet-assessment
description: Use to enumerate the canonical agent fleet and aggregate per-agent scores into a ranked report with remediation priorities
argument-hint: 'Confirm the fleet scope (core + optional agents) to assess'
---

## What I Do

I enumerate the canonical agent fleet, delegate each agent to a one-file `agent-definition-review`, and aggregate the returned scores into a ranked 0-100 fleet report with per-agent strengths, weaknesses, and fix priorities. I own enumeration and aggregation; the per-file review owns each score. I never average a blocker away and never invent a missing result.

## When To Use Me

- to assess the whole agent fleet, not a single file
- to produce a ranked report with remediation priorities across all agents
- after delivery changes agent templates, skills, handoffs, or permissions
- to surface duplication and unexecutable-handoff patterns across the fleet

## Read Alongside

- `packages/ai-universal-rules/templates/core/agents` — core (callable) fleet
- `packages/ai-universal-rules/templates/optional/agents` — optional / not-yet-promoted agents
- `docs/ai/agents.md` — the live roster to cross-check enumeration against

## Steps

1. Enumerate the fleet from the canonical template surfaces (core + optional); group provider variants by normalized `id` and prefer the template as canonical. Report which surfaces existed and which were absent.
2. For each agent group, delegate the canonical file to `agent-definition-review` (one file per call) and collect its `score`, `readiness`, `decision`, and blocker/major/minor counts.
3. Rank agents best to worst. The per-file score is the anchor; apply only documented adjustments. Hard caps hold: a blocker never scores above 69, an unexecutable top handoff never above 79, an invalid schema never above 39.
4. For each agent, list up to 3 evidenced strengths and up to 3 actionable weaknesses (never padded), plus P0/P1/P2 fixes.
5. Emit fleet readiness, the ranking table, fleet-wide patterns, and a remediation queue.

## Output

- `FLEET SCORE: NN/100` and `READINESS: ready | ready-with-fixes | blocked`
- a best-to-worst ranking table with per-agent score, readiness, and finding counts
- per-agent strengths, weaknesses, and P0/P1/P2 fix priorities
- fleet-wide patterns and a top-fixes remediation queue

## Gotchas

- never re-audit files yourself; if a per-file result is missing, mark that agent `unknown` and never fabricate counts
- never hide a blocked agent behind a healthy fleet average; bucket blocked-first
- one file per delegation call; do not batch multiple agents into one review
- derive the core set from the live roster at run time; do not trust a memorized list that drifts
