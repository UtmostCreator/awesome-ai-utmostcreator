# Approval Boundaries

Approval boundaries define where an AI-assisted workflow must stop and ask for
human confirmation before continuing.

## Always Ask First

Ask before any operation that could be hard to reverse, expose private data, or
change a user's environment outside the bounded task.

- Destructive file operations, including deletes, broad moves, and cleanup of
  untracked directories.
- Secret, credential, token, key, certificate, or private environment access.
- Dependency installation, package upgrades, or workstation tool installation.
- Deployments, release publishing, remote writes, or infrastructure changes.
- Generated artifact rewrites, unless the user has approved the exact generator
  command for the current task.
- Broad formatting or mechanical rewrites outside the stated scope.

## Safe Default

When unsure, stay read-only, report the evidence checked, and ask one focused
question that names the exact path or command needing approval.

## Reporting

The final handoff must identify approvals requested, approvals received, and any
work intentionally skipped because approval was missing.
