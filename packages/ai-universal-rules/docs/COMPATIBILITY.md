# Compatibility

This root file is the short guide version.

See `docs/foundations/COMPATIBILITY.md` for the maintained canonical copy.

## Safe Default

- start with policy, project context, and capability folders
- add one runtime adapter first
- keep the core agent set small
- add prompt files, hooks, and MCP only with documented fallbacks

## Code-Development Choice Model

Recommended core agents:

- `researcher`
- `architect`
- `implementer`
- `reviewer`

Recommended optional agents:

- `release-auditor`
- `refactorer`

Do not make users choose from many agents for normal development work.
