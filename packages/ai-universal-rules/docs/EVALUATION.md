# Capability Evaluation

Use this rubric when deciding whether the workflow is working well enough to keep, expand, or remove.

See `docs/operations/EVAL-SCENARIOS.md` for scenario-based testing.

## Trigger Quality

- Did the right capability trigger for the request?
- Did it avoid triggering on near-miss requests?
- Is the description written in user-language rather than maintainer shorthand?

## Output Quality

- Did the capability produce the expected structure?
- Did it provide evidence instead of generic confidence?
- Did it stay within scope instead of overreaching?

## Outcome And Trace Quality

- Did the workflow prove the real outcome, not only produce a plausible transcript?
- When tools, handoffs, or guards were involved, does the trace show the right sequence and boundaries?
- If the task failed, can the trace identify whether the problem came from routing, tool use, guardrails, or missing context?

## Verification Quality

- Did it choose the smallest relevant verification first?
- Did it distinguish build success from behavior proof?
- Did it report exactly what was verified?

## Maintainability

- Is the entry file short enough to scan quickly?
- Are gotchas and examples in support files instead of bloating the entry file?
- Does the capability duplicate another capability without adding value?

## Workflow Separation

- Is stable policy kept out of task-entry prompts and commands when possible?
- Are repeated one-off tasks using prompt files or commands instead of always-on instructions?
- Are staged agents used when context isolation is important?
- Are hooks or deterministic checks used when the repo says something must always happen?

## Tool Contract Quality

- Are tool descriptions specific enough to route the model correctly?
- Do tools avoid overlapping scope that makes selection noisy or ambiguous?
- Does the workflow make expected inputs, outputs, and boundaries explicit?

## Review Questions

For every high-value capability, review these regularly:

1. What user requests should trigger it?
2. What requests should not trigger it?
3. What are the top three failure modes?
4. What support file gets read most often and should that content move?
5. Can any repeated reasoning become a deterministic script or template?

## Keep, Improve, Or Remove

- Keep when the capability triggers cleanly and reduces repeated prompting.
- Improve when it is useful but misses edge cases, lacks gotchas, or has weak examples.
- Remove or merge when it duplicates another capability or fires too broadly.

## See Also

- `operations/EVAL-SCENARIOS.md` — scenario set for hands-on workflow testing
- `operations/MAINTENANCE.md` — update triggers and update order
- `foundations/CAPABILITY-MODEL.md` — capability contract requirements being evaluated
- `workflows/SYSTEM-WORKFLOW.md` — end-to-end lifecycle this rubric grades
