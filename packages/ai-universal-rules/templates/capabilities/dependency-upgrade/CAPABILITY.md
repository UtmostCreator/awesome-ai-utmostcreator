# Dependency Upgrade Capability

## Purpose

Evaluate and implement a dependency upgrade with attention to compatibility, verification depth, and release risk.

## Trigger When

- a library, framework, runtime, or toolchain dependency is being upgraded
- a security or compatibility update is requested
- build or verification behavior may change because of dependency drift

## Do Not Trigger When

- the request is a normal feature or bug fix with no dependency change
- the version change is already fully automated and the repository has a separate enforced workflow

## Required Inputs

- package or dependency name
- current and target version if known
- affected packages or paths
- verification expectations

## Read Next

- `checklist.md` for upgrade review order
- `gotchas.md` for compatibility traps
- `examples.md` for expected reporting

## Workflow

1. Identify scope and affected owners.
2. Check release notes, breaking changes, and repo usage surface.
3. Apply the smallest safe upgrade slice.
4. Run the most relevant focused verification first, then broader checks if needed.
5. Report compatibility risk, evidence, and any follow-up work.

## Verification Expectations

- Verification depth should match the dependency's runtime impact.
- Upgrades touching build, runtime, or shared contracts usually need broader review than a dev-only formatter change.
- Silent dependency drift is not acceptable evidence; report the exact version change and its blast radius.

## Output Contract

- upgrade scope
- compatibility risk
- verification evidence
- follow-up work or rollout notes

## Related Capabilities

- `project-context`
- `verify-change`
- `review-diff`
- `release-safety`
