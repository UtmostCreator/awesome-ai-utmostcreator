#!/usr/bin/env python3
"""Validate the agent-handoff model and render Mermaid interaction diagrams.

This mirrors the pattern of the OpenCode lifecycle generator: a single YAML
model is the source of truth, it is validated structurally and semantically,
and the diagrams are generated from it so humans and agents read the same
authoritative picture. Edit the YAML, regenerate, never hand-edit the .mmd.

Usage:
  python gen_handoff.py agent-handoff.yaml                 # validate + render to ./generated
  python gen_handoff.py agent-handoff.yaml --out DIR       # render into DIR
  python gen_handoff.py agent-handoff.yaml --check         # validate only, no files

Exit codes: 0 ok, 1 validation error, 2 usage/IO error.

Only dependency is PyYAML. If it is missing, use gen_handoff.sh which falls
back to nix-shell.
"""
from __future__ import annotations

import argparse
import html
import json
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

try:
    import yaml
except ImportError as exc:  # pragma: no cover - environment guard
    raise SystemExit(
        "PyYAML is required. Run handoff/gen_handoff.sh, or "
        "`python -m pip install pyyaml`."
    ) from exc


# --------------------------------------------------------------- loading
class UniqueKeyLoader(yaml.SafeLoader):
    """SafeLoader that rejects duplicate mapping keys so a later duplicate
    cannot silently overwrite an authoritative value."""


def _no_dupes(loader: yaml.SafeLoader, node: yaml.MappingNode, deep: bool = False) -> dict:
    mapping: dict = {}
    for key_node, value_node in node.value:
        key = loader.construct_object(key_node, deep=deep)
        if key in mapping:
            raise yaml.constructor.ConstructorError(
                "while constructing a mapping", node.start_mark,
                f"found duplicate key: {key!r}", key_node.start_mark,
            )
        mapping[key] = loader.construct_object(value_node, deep=deep)
    return mapping


UniqueKeyLoader.add_constructor(
    yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, _no_dupes
)


class ValidationError(Exception):
    pass


# --------------------------------------------------------------- helpers
def _mapping(value: Any, path: str) -> dict:
    if not isinstance(value, dict):
        raise ValidationError(f"{path} must be a mapping")
    return value


def _list(value: Any, path: str) -> list:
    if not isinstance(value, list):
        raise ValidationError(f"{path} must be a list")
    return value


def _unique_ids(items: list, path: str) -> set[str]:
    ids: list[str] = []
    for i, item in enumerate(items):
        if not isinstance(item, dict):
            raise ValidationError(f"{path}[{i}] must be a mapping")
        item_id = item.get("id")
        if not isinstance(item_id, str) or not item_id.strip():
            raise ValidationError(f"{path}[{i}].id must be a non-empty string")
        ids.append(item_id)
    dupes = sorted(k for k, c in Counter(ids).items() if c > 1)
    if dupes:
        raise ValidationError(f"{path} contains duplicate ids: {', '.join(dupes)}")
    return set(ids)


def _all_skills(data: dict) -> set[str]:
    skills = _mapping(data.get("skills"), "skills")
    combined: list = []
    for section in ("existing_keep", "create"):
        section_items = _list(skills.get(section, []), f"skills.{section}")
        _unique_ids(section_items, f"skills.{section}")  # per-section shape/id check
        combined.extend(section_items)
    # One combined check so an id duplicated ACROSS the two sections also fails.
    return _unique_ids(combined, "skills")


def _has_cycle(nodes: set[str], edges: list[dict]) -> bool:
    adj: dict[str, list[str]] = defaultdict(list)
    indeg = {n: 0 for n in nodes}
    for e in edges:
        adj[e["from"]].append(e["to"])
        indeg[e["to"]] += 1
    queue = [n for n, d in indeg.items() if d == 0]
    seen = 0
    while queue:
        n = queue.pop()
        seen += 1
        for t in adj[n]:
            indeg[t] -= 1
            if indeg[t] == 0:
                queue.append(t)
    return seen != len(nodes)


# --------------------------------------------------------------- validation
TOP_LEVEL = (
    "meta", "provider_integration", "authority", "principles",
    "handoff_contract_schema", "handoff_payload_schema", "permission_profiles",
    "roles", "skills", "deterministic_tools", "artifacts", "handoffs",
    "workflows", "isolation", "dynamic_routing", "migration", "validation",
)

NON_EMPTY_HANDOFF_LISTS = (
    "provide", "produce", "avoid", "acceptance", "evidence", "stop_conditions",
)


def validate(data: dict) -> list[str]:
    msgs: list[str] = []
    for key in TOP_LEVEL:
        if key not in data:
            raise ValidationError(f"Missing top-level key: {key}")

    profiles = set(_mapping(data["permission_profiles"], "permission_profiles"))
    roles = _list(data["roles"], "roles")
    role_ids = _unique_ids(roles, "roles")
    skill_ids = _all_skills(data)
    tool_ids = _unique_ids(
        _list(data["deterministic_tools"], "deterministic_tools"), "deterministic_tools"
    )
    artifact_ids = _unique_ids(_list(data["artifacts"], "artifacts"), "artifacts")
    handoffs = _list(data["handoffs"], "handoffs")
    handoff_ids = _unique_ids(handoffs, "handoffs")
    workflows = _list(data["workflows"], "workflows")
    workflow_ids = _unique_ids(workflows, "workflows")

    # roles ---------------------------------------------------------------
    for role in roles:
        if role.get("permission_profile") not in profiles:
            raise ValidationError(
                f"Role {role['id']} references unknown permission profile "
                f"{role.get('permission_profile')!r}"
            )
        for skill in role.get("loads_skills", []):
            if skill not in skill_ids:
                raise ValidationError(
                    f"Role {role['id']} loads unknown skill {skill!r}"
                )

    # artifacts -----------------------------------------------------------
    for art in data["artifacts"]:
        if art.get("owner") not in role_ids:
            raise ValidationError(
                f"Artifact {art['id']} has unknown owner {art.get('owner')!r}"
            )

    # handoffs ------------------------------------------------------------
    required = _list(
        _mapping(data["handoff_contract_schema"], "handoff_contract_schema").get("required_fields"),
        "handoff_contract_schema.required_fields",
    )
    for h in handoffs:
        missing = [f for f in required if f not in h]
        if missing:
            raise ValidationError(f"Handoff {h['id']} missing fields: {', '.join(missing)}")
        for end in ("from", "to", "failure_route"):
            if h.get(end) not in role_ids:
                raise ValidationError(
                    f"Handoff {h['id']} has unknown {end} role {h.get(end)!r}"
                )
        for field in NON_EMPTY_HANDOFF_LISTS:
            if not isinstance(h.get(field), list) or not h[field]:
                raise ValidationError(f"Handoff {h['id']}.{field} must be a non-empty list")

    # workflows -----------------------------------------------------------
    used_contracts: set[str] = set()
    for wf in workflows:
        steps = _list(wf.get("steps"), f"workflows.{wf['id']}.steps")
        if not steps:
            raise ValidationError(f"Workflow {wf['id']} has no steps")
        if not any(s.get("type") == "terminal" for s in steps):
            raise ValidationError(f"Workflow {wf['id']} has no terminal step")
        step_ids = _unique_ids(steps, f"workflows.{wf['id']}.steps")
        for step in steps:
            st = step.get("type")
            if st == "role" and step.get("ref") not in role_ids:
                raise ValidationError(
                    f"Workflow {wf['id']} step {step['id']} references unknown role {step.get('ref')!r}"
                )
            if st == "skill" and step.get("ref") not in skill_ids:
                raise ValidationError(
                    f"Workflow {wf['id']} step {step['id']} references unknown skill {step.get('ref')!r}"
                )
            if st not in {"role", "skill", "gate", "terminal"}:
                raise ValidationError(
                    f"Workflow {wf['id']} step {step['id']} has invalid type {st!r}"
                )
        edges = _list(wf.get("edges"), f"workflows.{wf['id']}.edges")
        keys: set[tuple] = set()
        for i, e in enumerate(edges):
            if not isinstance(e, dict):
                raise ValidationError(f"workflows.{wf['id']}.edges[{i}] must be a mapping")
            if e.get("from") not in step_ids or e.get("to") not in step_ids:
                raise ValidationError(
                    f"Workflow {wf['id']} edge {e.get('from')!r}->{e.get('to')!r} references unknown endpoint"
                )
            key = (e["from"], e["to"], str(e.get("when", "")))
            if key in keys:
                raise ValidationError(
                    f"Workflow {wf['id']} has duplicate edge {e['from']}->{e['to']} ({key[2]})"
                )
            keys.add(key)
            contract = e.get("contract")
            if contract:
                if contract not in handoff_ids:
                    raise ValidationError(
                        f"Workflow {wf['id']} edge {e['from']}->{e['to']} references unknown contract {contract!r}"
                    )
                used_contracts.add(contract)
        if _has_cycle(step_ids, edges) and not wf.get("allow_cycles", False):
            raise ValidationError(
                f"Workflow {wf['id']} contains a cycle but allow_cycles is false"
            )

    for unused in sorted(handoff_ids - used_contracts):
        msgs.append(f"WARN handoff contract not wired to any workflow edge: {unused}")

    # isolation bridges ---------------------------------------------------
    bridges = _list(
        _mapping(data["isolation"], "isolation").get("bridges"), "isolation.bridges"
    )
    bridge_ids = _unique_ids(bridges, "isolation.bridges")
    for b in bridges:
        if b.get("from_workflow") not in workflow_ids:
            raise ValidationError(f"Bridge {b['id']} has unknown from_workflow")
        if b.get("to_workflow") not in workflow_ids:
            raise ValidationError(f"Bridge {b['id']} has unknown to_workflow")
        if b["from_workflow"] == b["to_workflow"]:
            raise ValidationError(f"Bridge {b['id']} must connect different workflows")

    # migration -----------------------------------------------------------
    migration = _mapping(data["migration"], "migration")
    expected = _list(migration.get("expected_current_agents"), "migration.expected_current_agents")
    actions = _list(migration.get("actions"), "migration.actions")
    currents = [a.get("current") for a in actions]
    exp_dupes = sorted(k for k, v in Counter(expected).items() if v > 1)
    act_dupes = sorted(k for k, v in Counter(currents).items() if v > 1)
    if exp_dupes:
        raise ValidationError("Duplicate expected current agents: " + ", ".join(exp_dupes))
    if act_dupes:
        raise ValidationError("Agents have multiple migration actions: " + ", ".join(act_dupes))
    missing = sorted(set(expected) - set(currents))
    extra = sorted(set(currents) - set(expected))
    if missing or extra:
        raise ValidationError(f"Migration coverage mismatch; missing={missing}, extra={extra}")
    # Every migration target must resolve to a real destination. Targets may be
    # composite ("implementer+bug-regression" = role + skill it loads).
    known_targets = role_ids | skill_ids | tool_ids
    for a in actions:
        for part in str(a.get("target", "")).split("+"):
            part = part.strip()
            if part and part not in known_targets:
                raise ValidationError(
                    f"Migration action for {a['current']!r} has unknown target part {part!r}"
                )

    # provider integration -----------------------------------------------
    pi = _mapping(data["provider_integration"], "provider_integration")
    providers = _unique_ids(_list(pi.get("providers"), "provider_integration.providers"),
                            "provider_integration.providers")
    provider_required = ("native_subagent_invocation", "native_handoff_contract",
                         "routing", "carrier", "human_summary", "fallback")
    for p in pi["providers"]:
        for field in provider_required:
            if field not in p:
                raise ValidationError(
                    f"Provider {p['id']} missing integration field {field!r}"
                )

    # handoff payload requires a human summary and a goto target ---------
    payload = _mapping(data["handoff_payload_schema"], "handoff_payload_schema")
    payload_fields = _list(payload.get("required_fields"), "handoff_payload_schema.required_fields")
    for needed in ("human_summary", "goto"):
        if needed not in payload_fields:
            raise ValidationError(f"handoff_payload_schema.required_fields must include {needed}")

    # dynamic routing (edgeless) -----------------------------------------
    dr = _mapping(data["dynamic_routing"], "dynamic_routing")
    if not isinstance(dr.get("supervisor"), str) or not dr["supervisor"].strip():
        raise ValidationError("dynamic_routing.supervisor must be a non-empty string")
    participants = _list(dr.get("participants"), "dynamic_routing.participants")
    part_set: set[str] = set()
    for p in participants:
        if p not in role_ids:
            raise ValidationError(f"dynamic_routing participant {p!r} is not a role")
        part_set.add(p)
    allowed = _mapping(dr.get("allowed_next"), "dynamic_routing.allowed_next")
    for src, nexts in allowed.items():
        if src not in role_ids:
            raise ValidationError(f"dynamic_routing.allowed_next has unknown source role {src!r}")
        for nxt in _list(nexts, f"dynamic_routing.allowed_next.{src}"):
            if nxt not in role_ids:
                raise ValidationError(
                    f"dynamic_routing.allowed_next[{src}] targets unknown role {nxt!r}"
                )
    for missing_src in sorted(part_set - set(allowed)):
        raise ValidationError(
            f"dynamic_routing participant {missing_src!r} has no allowed_next entry"
        )
    for ex in _list(dr.get("exits"), "dynamic_routing.exits"):
        if ex not in part_set:
            raise ValidationError(f"dynamic_routing.exit {ex!r} is not a participant")

    # Reachability warnings (non-fatal): flag declared-but-never-referenced ids.
    referenced_skills = {s for r in roles for s in r.get("loads_skills", [])}
    referenced_skills |= {
        step["ref"] for wf in workflows for step in wf["steps"] if step.get("type") == "skill"
    }
    for unused in sorted(skill_ids - referenced_skills):
        msgs.append(f"WARN skill declared but never loaded: {unused}")
    roles_in_workflows = {
        step["ref"] for wf in workflows for step in wf["steps"] if step.get("type") == "role"
    }
    roles_in_handoffs = {h["from"] for h in handoffs} | {h["to"] for h in handoffs}
    for orphan in sorted(role_ids - roles_in_workflows - roles_in_handoffs):
        msgs.append(f"WARN role never appears in a workflow or handoff: {orphan}")

    msgs.extend([
        f"PASS roles={len(role_ids)} skills={len(skill_ids)} tools={len(tool_ids)} artifacts={len(artifact_ids)}",
        f"PASS handoffs={len(handoff_ids)} workflows={len(workflow_ids)} bridges={len(bridge_ids)} providers={len(providers)}",
        f"PASS migration_coverage={len(expected)}/{len(currents)}",
    ])
    return msgs


# --------------------------------------------------------------- rendering
ROLE_CLASS = {
    "researcher": "research",
    "architect": "design",
    "plan-writer": "write",
    "implementer": "write",
    "configuration-maintainer": "write",
    "reviewer": "review",
    "release-auditor": "gate",
    "agent-factory": "factory",
    "fleet-assessor": "audit",
    "agent-definition-reviewer": "audit",
}

CLASS_DEFS = [
    "  classDef gate fill:#fff3e0,stroke:#ef6c00,color:#e65100,stroke-width:2px",
    "  classDef terminal fill:#e8f5e9,stroke:#2e7d32,color:#1b5e20",
    "  classDef research fill:#e0f7fa,stroke:#00838f,color:#004d40",
    "  classDef design fill:#f3e5f5,stroke:#6a1b9a,color:#4a148c",
    "  classDef write fill:#e8eaf6,stroke:#3949ab,color:#1a237e",
    "  classDef review fill:#fff8e1,stroke:#f9a825,color:#6d4c00",
    "  classDef factory fill:#ede7f6,stroke:#5e35b1,color:#311b92",
    "  classDef audit fill:#eceff1,stroke:#455a64,color:#263238",
    "  classDef skill fill:#e3f2fd,stroke:#1565c0,color:#0d47a1",
    "  classDef removed fill:#ffebee,stroke:#c62828,color:#b71c1c",
    "  classDef kept fill:#e8f5e9,stroke:#2e7d32,color:#1b5e20",
]


def esc(text: str) -> str:
    return html.escape(str(text), quote=False).replace('"', "#quot;")


def _step_node(prefix: str, step: dict) -> str:
    nid = f"{prefix}__{step['id']}"
    label = esc(step.get("label", step["id"]))
    t = step["type"]
    if t == "gate":
        return f'    {nid}{{"{label}"}}:::gate'
    if t == "terminal":
        return f'    {nid}(["{label}"]):::terminal'
    if t == "skill":
        return f'    {nid}[/"{label}"/]:::skill'
    cls = ROLE_CLASS.get(step.get("ref"), "write")
    return f'    {nid}["{label}"]:::{cls}'


def _wf_body(wf: dict, indent: str = "  ") -> list[str]:
    prefix = wf["id"]
    lines = [f'{indent}subgraph {prefix}["{esc(wf["title"])}"]', f"{indent}  direction LR"]
    for step in wf["steps"]:
        lines.append(indent + _step_node(prefix, step).lstrip())
    for e in wf["edges"]:
        src = f"{prefix}__{e['from']}"
        dst = f"{prefix}__{e['to']}"
        cond = esc(e.get("when", ""))
        lbl = f"|{cond}|" if cond else ""
        lines.append(f"{indent}  {src} -->{lbl} {dst}")
    lines.append(f"{indent}end")
    return lines


def render_workflow(wf: dict) -> str:
    lines = ["flowchart LR", *CLASS_DEFS, ""]
    lines += _wf_body(wf, indent="  ")
    return "\n".join(lines) + "\n"


def render_combined(data: dict) -> str:
    lines = ["flowchart LR", *CLASS_DEFS, ""]
    for wf in data["workflows"]:
        lines += _wf_body(wf, indent="  ")
        lines.append("")
    lines.append("  %% Explicit cross-workflow bridges only")
    terminal_of = {
        wf["id"]: next(
            (s["id"] for s in reversed(wf["steps"]) if s["type"] == "terminal"),
            wf["steps"][-1]["id"],
        )
        for wf in data["workflows"]
    }
    first_of = {wf["id"]: wf["steps"][0]["id"] for wf in data["workflows"]}
    for b in data["isolation"]["bridges"]:
        src = f"{b['from_workflow']}__{terminal_of[b['from_workflow']]}"
        dst = f"{b['to_workflow']}__{first_of[b['to_workflow']]}"
        lines.append(f"  {src} -.->|{esc(b['id'])}| {dst}")
    return "\n".join(lines) + "\n"


def render_handoffs(data: dict) -> str:
    """Role-to-role interaction graph. Each edge is one handoff contract; the
    provide/produce/avoid detail lives in the model and plan, the diagram shows
    who hands what to whom and under which contract."""
    lines = ["flowchart LR", *CLASS_DEFS, ""]
    for role in data["roles"]:
        cls = ROLE_CLASS.get(role["id"], "write")
        lines.append(f'  {role["id"].replace("-", "_")}["{esc(role["label"])}"]:::{cls}')
    lines.append("")
    for h in data["handoffs"]:
        src = h["from"].replace("-", "_")
        dst = h["to"].replace("-", "_")
        lines.append(f'  {src} -->|{esc(h["id"])}| {dst}')
    return "\n".join(lines) + "\n"


def render_migration(data: dict) -> str:
    """Fleet reduction map: each current agent -> its migration target."""
    lines = ["flowchart LR", *CLASS_DEFS, ""]
    role_ids = {r["id"] for r in data["roles"]}
    targets_seen: dict[str, str] = {}
    for a in data["migration"]["actions"]:
        cur = a["current"].replace("-", "_")
        tgt_raw = a["target"]
        cur_cls = "kept" if a["action"] == "keep" else "removed"
        lines.append(f'  cur_{cur}["{esc(a["current"])}"]:::{cur_cls}')
        tgt_id = "tgt_" + tgt_raw.replace("-", "_").replace("+", "_plus_").replace(".", "_")
        if tgt_id not in targets_seen:
            base = tgt_raw.split("+")[0]
            tcls = ROLE_CLASS.get(base, "skill" if base not in role_ids else "write")
            lines.append(f'  {tgt_id}(["{esc(tgt_raw)}"]):::{tcls}')
            targets_seen[tgt_id] = tgt_raw
        lines.append(f"  cur_{cur} -->|{esc(a['action'])}| {tgt_id}")
    return "\n".join(lines) + "\n"


def render_edgeless(data: dict) -> str:
    """Edgeless (dynamic-routing) view. No predefined edges: a supervisor
    dispatches to any participant (dotted 'dispatch'), and each agent then
    transfers at runtime only within its allowed_next set (solid 'handoff
    goto'). Equivalent to LangGraph Command(goto=...) constrained by Literal."""
    dr = data["dynamic_routing"]
    role_by_id = {r["id"]: r for r in data["roles"]}
    sup = dr["supervisor"]
    parts = dr["participants"]
    part_set = set(parts)
    lines = ["flowchart LR", *CLASS_DEFS, ""]
    lines.append(f'  {sup}{{"{esc(sup)} · any-entry supervisor"}}:::gate')
    lines.append('  done(["done"]):::terminal')
    for pid in parts:
        cls = ROLE_CLASS.get(pid, "write")
        lines.append(f'  {pid.replace("-", "_")}["{esc(role_by_id[pid]["label"])}"]:::{cls}')
    lines.append("")
    lines.append("  %% free entry: supervisor may dispatch to any participant")
    for pid in parts:
        lines.append(f'  {sup} -.->|dispatch| {pid.replace("-", "_")}')
    lines.append("")
    lines.append("  %% runtime transfers: each agent routes within allowed_next")
    for pid in parts:
        for nxt in dr["allowed_next"].get(pid, []):
            if nxt in part_set:
                lines.append(f'  {pid.replace("-", "_")} -->|handoff goto| {nxt.replace("-", "_")}')
    lines.append("")
    lines.append("  %% exits and escape hatch")
    for ex in dr["exits"]:
        lines.append(f'  {ex.replace("-", "_")} -->|complete| done')
    for pid in parts:
        lines.append(f'  {pid.replace("-", "_")} -.->|blocked| {sup}')
    return "\n".join(lines) + "\n"


RENDERERS = {
    "combined": render_combined,
    "handoffs": render_handoffs,
    "migration": render_migration,
    "edgeless": render_edgeless,
}


# ---------------------------------------------------------- agnostic handoff kit
GENERATED_HEADER = "<!-- GENERATED from handoff/agent-handoff.yaml by gen_handoff.py — do not hand-edit. -->"


def render_allowed_next_json(data: dict) -> str:
    """Machine-readable transfer map the dispatcher enforces. Stdlib json so the
    dispatcher needs no third-party dependency on any runtime."""
    dr = data["dynamic_routing"]
    return json.dumps(
        {
            "supervisor": dr["supervisor"],
            "participants": dr["participants"],
            "exits": dr["exits"],
            "always_allowed": [dr["supervisor"], "done"],
            "allowed_next": dr["allowed_next"],
        },
        indent=2,
    ) + "\n"


def _allowed_next_table(data: dict) -> list[str]:
    dr = data["dynamic_routing"]
    rows = ["| Agent | may `handoff goto` |", "| --- | --- |"]
    for role in data["roles"]:
        nxt = dr["allowed_next"].get(role["id"])
        if nxt is None:
            continue
        cell = ", ".join(f"`{n}`" for n in nxt) if nxt else "_(exit only)_"
        rows.append(f"| `{role['id']}` | {cell} |")
    return rows


def render_protocol_md(data: dict) -> str:
    """The provider-agnostic handoff protocol every agent follows."""
    dr = data["dynamic_routing"]
    cmd = dr["command"]
    emit = cmd["emit"]
    lines = [
        GENERATED_HEADER,
        "",
        "# Handoff Protocol (provider-agnostic)",
        "",
        "No runtime (Claude Code, Copilot, OpenCode) enforces a typed agent-to-agent",
        "handoff. This protocol makes handoffs portable: an agent **emits a block** and a",
        "**shared dispatcher** enforces the transfer. Same behavior on every surface.",
        "",
        "## 1. When you finish, emit a handoff block",
        "",
        f"````{emit['language']}",
        "\n".join(f"{f}: ..." for f in emit["fields"]),
        "````",
        "",
        "- `from` — your agent id.",
        "- `goto` — the next agent id (see allowed_next below), or "
        f"`{dr['supervisor']}` to escalate, or `done`.",
        "- `status` — `ok` for a normal transfer, `blocked` when a stop_condition fired.",
        "- `contract_id` — the handoff contract governing this transfer.",
        "- `payload_ref` — path to the serialized `HandoffPayload`, or inline it.",
        "- `human_summary` — <=6 lines a person can read to own the merge.",
        "",
        "## 2. Validate the transfer (edgeless guardrail)",
        "",
        "```bash",
        f"{cmd['enforcement']['dispatcher']}",
        "```",
        "",
        "Exit `0` = **ROUTE** (goto is allowed). Exit `1` = **REJECT**: do not proceed —",
        f"re-emit with `goto: {dr['supervisor']}` and `status: blocked`.",
        "",
        "## 3. allowed_next — routing lives in the agent",
        "",
        "A transfer is legal only if `goto` is in your row (or `"
        f"{dr['supervisor']}` / `done`).",
        "",
        *_allowed_next_table(data),
        "",
        "## Failure & loops",
        "",
        *[f"- {c}" for c in dr["loop_and_failure_controls"]],
        "",
    ]
    return "\n".join(lines)


_PROVIDER_FRONTMATTER = {
    "claude": "---\ndescription: Transfer control to the next agent via the edgeless handoff contract\nargument-hint: <next-agent-id>\n---\n",
    "copilot": "---\nmode: agent\ndescription: Transfer control to the next agent via the edgeless handoff contract\n---\n",
    "opencode": "---\ndescription: Transfer control to the next agent via the edgeless handoff contract\n---\n",
}


def _include_block(data: dict) -> str:
    """The marker-delimited handoff include injected into agent files."""
    dr = data["dynamic_routing"]
    marker = dr["wire_agents"]["marker"]
    doc = dr["command"]["protocol_doc"]
    lang = dr["command"]["emit"]["language"]
    return (
        f"<!-- {marker}:START — injected by handoff/gen_handoff.py; canonical source "
        "handoff/AGENT-INCLUDE.md. This file is regenerated by the ai-kit installer, "
        "which drops this block; upstream the include into the canonical agent "
        "template for a durable fix. -->\n"
        "\n"
        "## Handoff (edgeless, provider-agnostic)\n"
        "\n"
        "On completion, transfer control with the shared handoff command — do not just "
        "recommend a next agent in prose:\n"
        "\n"
        f"1. Emit a ```{lang}``` block: `from`, `goto`, `status`, `contract_id`, "
        "`payload_ref`, `human_summary`.\n"
        "2. Validate the transfer: `python handoff/dispatch.py --from <this-agent-id> "
        "--goto <target>`.\n"
        "3. Exit 0 → route to `goto`. Exit 1 → re-emit `goto: "
        f"{dr['supervisor']}`, `status: blocked`; never force an illegal transfer.\n"
        "\n"
        f"Legal targets and rules: [`{doc}`]({doc}). The `/{dr['command']['name']}` "
        "command runs this flow.\n"
        "\n"
        f"<!-- {marker}:END -->"
    )


def wire_agent_files(data: dict, repo_root: Path) -> None:
    """Inject (or refresh) the handoff include in each configured agent file.
    Idempotent: replaces the block between START/END markers if present, else
    appends it."""
    wa = data["dynamic_routing"].get("wire_agents")
    if not wa:
        return
    marker = wa["marker"]
    start_tag = f"<!-- {marker}:START"
    end_tag = f"<!-- {marker}:END -->"
    block = _include_block(data)
    for rel in wa["targets"]:
        p = repo_root / rel
        if not p.exists():
            print(f"WARN wire target missing: {rel}")
            continue
        text = p.read_text(encoding="utf-8")
        if start_tag in text and end_tag in text:
            pre = text[: text.index(start_tag)].rstrip()
            post = text[text.index(end_tag) + len(end_tag):]
            new = f"{pre}\n\n{block}{post}"
        else:
            new = f"{text.rstrip()}\n\n{block}\n"
        if new != text:
            p.write_text(new, encoding="utf-8")
            print(f"wired {rel}")
        else:
            print(f"wired {rel} (unchanged)")


def render_provider_command(data: dict, provider: str) -> str:
    dr = data["dynamic_routing"]
    cmd = dr["command"]
    body = [
        _PROVIDER_FRONTMATTER[provider] + GENERATED_HEADER,
        "",
        f"# /{cmd['name']} — edgeless agent handoff",
        "",
        "Perform a provider-agnostic handoff to the next agent. Steps:",
        "",
        f"1. Emit a ```{cmd['emit']['language']}``` block with fields: "
        + ", ".join(f"`{f}`" for f in cmd["emit"]["fields"]) + ".",
        f"2. Set `goto` to a target allowed for your agent — see "
        f"`{cmd['protocol_doc']}` (the allowed_next table).",
        f"3. Validate before transferring: `{cmd['enforcement']['dispatcher']}` "
        "(replace `<FROM>`/`<GOTO>`).",
        "4. On exit 0, route to `goto`. On exit 1 (REJECT), re-emit with "
        f"`goto: {dr['supervisor']}` and `status: blocked` — never force an "
        "illegal transfer.",
        "",
        f"Full rules: [`{cmd['protocol_doc']}`]({cmd['protocol_doc']}).",
        "",
    ]
    return "\n".join(body)


# --------------------------------------------------------------- main
def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("model", type=Path)
    ap.add_argument("--out", type=Path, default=Path(__file__).resolve().parent / "generated")
    ap.add_argument("--check", action="store_true", help="validate only, no output")
    args = ap.parse_args()

    try:
        data = yaml.load(args.model.read_text(encoding="utf-8"), Loader=UniqueKeyLoader)
        if not isinstance(data, dict):
            raise ValidationError("root of the model must be a mapping")
        messages = validate(data)
    except (OSError, yaml.YAMLError) as exc:
        print(f"INPUT ERROR: {exc}", file=sys.stderr)
        return 2
    except ValidationError as exc:
        print(f"MODEL INVALID: {exc}", file=sys.stderr)
        return 1

    for m in messages:
        print(m)
    if args.check:
        return 0

    out = args.out
    out.mkdir(parents=True, exist_ok=True)
    for name, fn in RENDERERS.items():
        (out / f"{name}.mmd").write_text(fn(data), encoding="utf-8")
        print(f"wrote {out / f'{name}.mmd'}")
    for wf in data["workflows"]:
        path = out / f"workflow-{wf['id']}.mmd"
        path.write_text(render_workflow(wf), encoding="utf-8")
        print(f"wrote {path}")

    # Provider-agnostic handoff kit: transfer map, protocol doc, per-surface commands.
    repo_root = args.model.resolve().parent.parent
    (out / "allowed_next.json").write_text(render_allowed_next_json(data), encoding="utf-8")
    print(f"wrote {out / 'allowed_next.json'}")
    (out / "HANDOFF-PROTOCOL.md").write_text(render_protocol_md(data), encoding="utf-8")
    print(f"wrote {out / 'HANDOFF-PROTOCOL.md'}")
    for provider, rel in data["dynamic_routing"]["command"]["provider_commands"].items():
        target = repo_root / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(render_provider_command(data, provider), encoding="utf-8")
        print(f"wrote {target}")
    wire_agent_files(data, repo_root)
    return 0


if __name__ == "__main__":
    sys.exit(main())
