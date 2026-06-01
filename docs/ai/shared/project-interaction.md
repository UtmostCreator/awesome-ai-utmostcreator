# System Architecture - How The Projects Interact

This document is the canonical cross-repository overview for the multi-project workspace that includes `awesome-ai-utmostcreator`.

Replace the neutral repository labels in this file after install. Use `PROJECT-A`, `PROJECT-B`, and similar labels only until the owning team fills in the real names and contracts.

Verified against the current local workspace on [OWNER TO FILL: YYYY-MM-DD].

## Shared Doc Ownership

- Canonical file: `[OWNER TO FILL: canonical repo path]/docs/shared/project-interaction.md`
- Linked or mirrored into adjacent repos through `docs/shared/` only if the workspace actually uses shared documentation
- Current linked repos in this workspace:
- `PROJECT-A`
- `PROJECT-B`
- `PROJECT-C`
- `PROJECT-D`
- `PROJECT-E`
- If this document changes, the linked repos should pick up the same content through the chosen sync mechanism

## Repositories In Scope

### 1. `PROJECT-A`

Primary role: [OWNER TO FILL: public app, backend service, package, operations system, content platform, etc.]

Verified stack from [OWNER TO FILL: package file or entrypoint]:

- [OWNER TO FILL: primary language and version]
- [OWNER TO FILL: framework or runtime]
- [OWNER TO FILL: important dependency or platform detail]

What it owns:

- [OWNER TO FILL: responsibility 1]
- [OWNER TO FILL: responsibility 2]
- [OWNER TO FILL: responsibility 3]

Important implementation surface in this repo:

- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`

### 2. `PROJECT-B`

Primary role: [OWNER TO FILL]

Verified stack from [OWNER TO FILL]:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

What it owns:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

Verified integration points:

- `[OWNER TO FILL: path or contract]`
- `[OWNER TO FILL: path or contract]`
- `[OWNER TO FILL: config or environment contract]`

### 3. `PROJECT-C`

Primary role: [OWNER TO FILL]

Verified from [OWNER TO FILL: package file, docs, or entrypoint]:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

What it owns:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

Working rule that remains important for debugging:

- [OWNER TO FILL: final authority, source of truth, or override rule]

### 4. `PROJECT-D`

Primary role: [OWNER TO FILL]

Verified from [OWNER TO FILL]:

- [OWNER TO FILL: package or service name]
- [OWNER TO FILL: integration surface]
- [OWNER TO FILL: notable constraint]

Capabilities or responsibilities:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

Important boundary:

- [OWNER TO FILL: what this project does not own]

### 5. `PROJECT-E`

Primary role: [OWNER TO FILL]

Verified stack from [OWNER TO FILL]:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

What it owns:

- [OWNER TO FILL]
- [OWNER TO FILL]
- [OWNER TO FILL]

Most visible consumer in this workspace:

- `PROJECT-[OWNER TO FILL]`

## High-Level System Picture

```text
User / client
    |
    v
PROJECT-A
    |
    +--> [OWNER TO FILL: request or handoff] -> PROJECT-B
    |
    +--> [OWNER TO FILL: shared package or component use] -> PROJECT-E
    v
PROJECT-B
    |
    +--> [OWNER TO FILL: downstream authority] -> PROJECT-C
    +--> [OWNER TO FILL: payments, search, or integration] -> PROJECT-D
    v
PROJECT-C
    |
    +--> [OWNER TO FILL: final authority or committed state]
```

## Verified Flow To Document

### Initial Context Or Region Detection

- `[OWNER TO FILL: entrypoint]` reads [OWNER TO FILL: headers, cookies, environment, user input]
- It sends that context to `[OWNER TO FILL: receiving project or endpoint]`
- The returned data is stored in `[OWNER TO FILL: cookie, session, state store, DB row]`

### Handoff Between Projects

- `[OWNER TO FILL: upstream path]` proxies or redirects to `[OWNER TO FILL: downstream path]`
- `[OWNER TO FILL: receiving project]` validates inputs, starts state, and routes to the next step
- `[OWNER TO FILL: service or controller]` seeds the shared state needed for later steps

### Final Authority Boundary

- `[OWNER TO FILL: project]` assembles the request payload
- `[OWNER TO FILL: integration project or package]` handles the external or downstream integration behavior
- `[OWNER TO FILL: authoritative project]` remains the final authority for committed state

## Ownership Rules

This is the most important cross-project rule for debugging:

- `PROJECT-A` owns [OWNER TO FILL]
- `PROJECT-B` owns [OWNER TO FILL]
- `PROJECT-C` owns [OWNER TO FILL]
- `PROJECT-D` owns [OWNER TO FILL]
- `PROJECT-E` owns [OWNER TO FILL]

## Contract Caveats To Re-Check

Document any version or path mismatches here instead of assuming alignment.

- `[OWNER TO FILL: upstream path or contract]` currently points to `[OWNER TO FILL: path or version]`
- `[OWNER TO FILL: downstream repo]` currently exposes `[OWNER TO FILL: path or version]`

That means at least one of these may be true:

- another compatibility route exists outside the currently inspected file set
- one project has drifted from the visible contract
- this shared document needs a narrower statement

Treat unresolved differences as live caveats until verified.

## Dependency Summary

| Repository  | Depends on      |
| ----------- | --------------- |
| `PROJECT-A` | [OWNER TO FILL] |
| `PROJECT-B` | [OWNER TO FILL] |
| `PROJECT-C` | [OWNER TO FILL] |
| `PROJECT-D` | [OWNER TO FILL] |
| `PROJECT-E` | [OWNER TO FILL] |

## Where To Look First

When investigating a bug across repos, start here first:

### In `PROJECT-A`

- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`

### In `PROJECT-B`

- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`
- `[OWNER TO FILL: path]`

### In `PROJECT-C`

- `[OWNER TO FILL: path or subsystem]`

### In `PROJECT-D`

- `[OWNER TO FILL: path or subsystem]`

### In `PROJECT-E`

- `[OWNER TO FILL: path or subsystem]`

## Short Version

- `PROJECT-A` owns [OWNER TO FILL]
- `PROJECT-B` owns [OWNER TO FILL]
- `PROJECT-C` owns [OWNER TO FILL]
- `PROJECT-D` owns [OWNER TO FILL]
- `PROJECT-E` owns [OWNER TO FILL]
- this file should be canonical in the chosen owner repo and shared with the other repos only through a deliberate sync mechanism
