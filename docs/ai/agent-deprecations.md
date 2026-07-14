# Agent Deprecations & Renames

Tracks agent id changes for the agent-handoff governance migration
(`docs/tickets/agent-handoff-governance-20260714/plan.md`). Old ids are retired in favor of the
new id; a 1-release alias window applies per `handoff/agent-handoff.yaml`
(`migration.compatibility`). Update any external references to the new id.

## Renames (this migration)

| Old id | New id | Note |
|---|---|---|
| `agent-critic` | `agent-definition-reviewer` | Optional audit agent renamed; role, permissions, and rubric unchanged. |
| `agent-fleet-assessor` | `fleet-assessor` | Optional fleet orchestrator renamed; delegates to `agent-definition-reviewer`. |
| `architecture-plan-writer` | `plan-writer` | Core plan-persistence agent renamed; docs/tickets-only write scope, permissions, and handoff routing unchanged. |
| `config-maintainer` | `configuration-maintainer` | Core config agent renamed to align filename with its existing `configuration-maintainer` handoff id; role, permissions, and routing unchanged. Now also the merge target for the retired `build-config` (build/toolchain config via the `build-configuration` skill). |

## Agent → skill / merge moves (already retired, listed for completeness)

| Old agent | Replacement | Note |
|---|---|---|
| `bugfix` | `implementer` + `bug-regression` skill | Agent retired; capability folded into implementer. |
| `docs` | `docs-sync` skill | Agent retired; documentation alignment is now a skill. |
| `refactorer` | `implementer` + `safe-refactor` skill | Agent retired; behavior-preserving edits via implementer. |
| `upgrade` | `implementer` + `dependency-upgrade` skill | Agent retired; dependency work via implementer. |
| `infra-auditor` | `reviewer` + `infra-risk-audit` skill | Agent retired; infra risk review is now a reviewer mode. |
| `workflow-auditor` | `reviewer` + `workflow-drift-audit` skill | Agent retired; workflow drift review is now a reviewer mode. |
| `repository-researcher` | `researcher` | Merged into the general researcher. |
| `repository-reviewer` | `reviewer` | Merged into the general reviewer. |
| `agent-creator` | `agent-factory` | Creator + supervisor merged into one staged pipeline agent. |
| `agent-creator-supervisor` | `agent-factory` | Supervisor/router role folded into `agent-factory`. |
| `agent-creator-static-validator` | `php tools/ai/validate-agent-spec.php` | Became the deterministic static-validation tool `agent-factory` calls. |
| `agent-creator-semantic-verifier` | `agent-semantic-verification` skill | Semantic-fit review is now a skill `agent-factory` loads. |
| `agent-creator-runtime-guardian` | `runtime-guardrail-design` skill | Runtime guardrail design is now a skill `agent-factory` loads. |
| `build-config` | `configuration-maintainer` + `build-configuration` skill | Optional agent retired; build/packaging/verification config work is now the `build-configuration` skill loaded by `configuration-maintainer`. |
| `post-install` | `post_install` workflow + `post-install-setup` skill | Core agent retired; placeholder cleanup and install/drift verification are now the temporary `post_install` workflow and the `post-install-setup` skill. |
