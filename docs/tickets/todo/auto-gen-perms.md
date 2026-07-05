Language,Provider,Filename,Lines,Code,Comments,Blanks,Complexity,Bytes,ULOC

<!-- STATUS: COMPLETE — all 25 agents (15 core + 11 optional, including ui-builder) are
     100% composed into tools/ai/install/permission-layers/ and verified drift-clean.
     Full audit trail archived at
     docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/archive/DONE-plan.md -->

<!-- Checklist status legend: [x] = 100% of this agent's permissions are inside the
     tools/ai/install/permission-layers/ composition system (verified via
     `php tools/ai/generate-agent-permissions.php --check` exiting 0 for this agent, plus
     `composer test:fast` showing no new regressions). [ ] = still (partly) hand-maintained
     outside the composition system, or not yet verified 100%. Do not check an item unless
     the composed model was regenerated and diffed against ground truth — see
     docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/plan.md
     for the full audit trail, discovered shared packs, and per-agent diff review. -->

- [x] Markdown,implementer.md,implementer.md,307,274,0,33,0,12371,0
- [x] Markdown,workflow-auditor.md,workflow-auditor.md,163,138,0,25,0,6593,0
- [x] Markdown,reviewer.md,reviewer.md,274,228,0,46,0,12548,0
- [x] Markdown,architecture-plan-writer.md,architecture-plan-writer.md,245,182,0,63,0,15584,0
- [x] Markdown,config-maintainer.md,config-maintainer.md,243,216,0,27,0,9128,0
- [x] Markdown,post-install.md,post-install.md,265,238,0,27,0,13319,0
- [x] Markdown,repository-researcher.md,repository-researcher.md,146,135,0,11,0,5833,0
- [x] Markdown,researcher.md,researcher.md,288,226,0,62,0,12511,0
- [x] Markdown,release-auditor.md,release-auditor.md,210,174,0,36,0,8717,0
- [x] Markdown,architect.md,architect.md,246,195,0,51,0,11619,0
- [x] Markdown,repository-reviewer.md,repository-reviewer.md,171,157,0,14,0,7223,0
- [x] Markdown,refactorer.md,refactorer.md,299,255,0,44,0,12474,0
- [x] Markdown,bootstrapper.md,bootstrapper.md,278,250,0,28,0,10628,0

<!-- Not in the original scc scan above but part of the same core 15-agent composition
     set (tools/ai/install/permission-layers/compositions.php) and already verified
     drift-clean via --check; added for a complete, honest checklist. -->
- [x] Markdown,script-runner.md,script-runner.md,165,,,,,,
- [x] Markdown,super-implementer.md,super-implementer.md,185,,,,,,

Language,Provider,Filename,Lines,Code,Comments,Blanks,Complexity,Bytes,ULOC

<!-- Optional agents (packages/ai-universal-rules/templates/optional/agents/ +
     .opencode/agents-optional/) — migration tracked in
     docs/tickets/arch-todo-optional-agent-permission-composition-20260705T221434Z/plan.md.
     Design Fork F1 (locked): these are composed for permission: rendering only; they are
     NOT added to aiInstallerAgentProfiles() (tool-gateway visibility stays unchanged). -->

- [x] Markdown,upgrade.md,upgrade.md,145,136,0,9,0,5722,0
      <!-- composed: impl profile, 'code' edit surface, cli_tools:none; verified via diff
           against build-config.md's original ground truth (byte-identical shape before
           either was composed) plus --check + sorted pattern:effect diff. -->
- [x] Markdown,docs.md,docs.md,118,109,0,9,0,5070,0
      <!-- composed: impl profile, 'docs' edit surface, cli_tools:none; verified via
           --check + sorted pattern:effect diff; composer test:fast shows no new
           regressions. -->
- [x] Markdown,build-config.md,build-config.md,143,134,0,9,0,5635,0
      <!-- composed: impl profile, 'code' edit surface, cli_tools:none; verified via
           --check + sorted pattern:effect diff. -->
- [x] Markdown,agent-creator-semantic-verifier.md,agent-creator-semantic-verifier.md,149,127,0,22,0,5990,0
      <!-- composed: readonly profile, edit:none, task:ask, cli_tools:none; shares most
           packs with agent-creator-runtime-guardian/static-validator plus
           verify.manual_ask for ai-verify.sh; verified via --check + sorted
           pattern:effect diff. -->
- [x] Markdown,infra-auditor.md,infra-auditor.md,122,110,0,12,0,5121,0
      <!-- composed: readonly profile, edit:none, task:ask, cli_tools:none; verified via
           --check + sorted pattern:effect diff. -->
- [x] Markdown,agent-creator-static-validator.md,agent-creator-static-validator.md,150,124,0,26,0,5787,0
      <!-- composed: readonly profile, its own narrower CLI/git preamble (denies git
           grep*/status*/show*/ls-files*/blame*/rev-parse*/branch*/yq* back beyond the
           shared family denies) plus agent-unique 'cat *': ask; verified via --check +
           sorted pattern:effect diff. -->
- [x] Markdown,bugfix.md,bugfix.md,155,141,0,14,0,5934,0
      <!-- composed: impl profile, 'code' edit surface, cli_tools:none; verified via
           --check + sorted pattern:effect diff; composer test:fast shows no new
           regressions. -->
- [x] Markdown,agent-creator-runtime-guardian.md,agent-creator-runtime-guardian.md,144,122,0,22,0,5854,0
      <!-- composed: readonly profile, edit:deny, task:ask; agent-unique 'ai-rollback.sh':
           ask exception; 'session-checkpoint.sh': ask sourced from the shared
           agent_creator.ask_session_checkpoint pack (shared with
           agent-creator-supervisor); 'pre-tool-use.sh'/'post-tool-use.sh' ground-truth
           'ask' forced to 'deny' via the immutable hard-deny floor (documented, no
           exception added). Verified via --check + sorted pattern:effect diff. -->
- [x] Markdown,agent-creator.md,agent-creator.md,132,115,0,17,0,6109,0
      <!-- composed: readonly profile, cli_tools:none; new pack
           agent_creator.deny_freshness_and_doc_check (keeps context-packaging family at
           its default 'ask', unlike the other 3 family members composed first); the only
           family member with NO validate-agent-spec.php grant; 'ai-task.sh' ground-truth
           'ask' forced to 'deny' via the immutable floor; trailing agent_assessment:
           frontmatter key confirmed splice-compatible with zero generator change.
           Verified via --check + sorted pattern:effect diff. -->
- [x] Markdown,ui-builder.md,ui-builder.md,181,154,0,27,0,7131,0
      <!-- composed: impl profile, new one-off 'ui' edit surface (no scripts/**/tools/**
           grant), starBaseline:'ask' preserved (not deny-normalized), cli_tools:none;
           explicit user "yes" decision resolved the Slice D gate. Fixed 2 real gaps found
           during diff review: core:safe-read's unconditional 'git grep *': allow needed
           an ask-gate-back exception; a pre-existing core.php hard-deny-floor typo
           ('common.sh*' missing the space convention every sibling entry uses) was
           corrected, zero behavior change verified for all affected agents. Verified via
           --check + sorted pattern:effect diff; composer test:fast shows no new
           regressions. ALL 11 optional agents now composed — this checklist is complete. -->
- [x] Markdown,agent-creator-supervisor.md,agent-creator-supervisor.md,165,135,0,30,0,6601,0
      <!-- composed: readonly profile, cli_tools:none; same freshness/doc-check pack as
           agent-creator plus agent_creator.validate_spec_allow +
           agent_creator.ask_session_checkpoint (shared with runtime-guardian);
           'ai-task.sh'/'pre-tool-use.sh'/'post-tool-use.sh' ground-truth 'ask' all forced
           to 'deny' via the immutable floor (3 forced tightenings, documented, no
           exceptions added). Verified via --check + sorted pattern:effect diff. -->
