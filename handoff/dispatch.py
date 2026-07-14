#!/usr/bin/env python3
"""Edgeless handoff dispatcher — the provider-agnostic enforcement point.

Every agent, on any runtime (Claude Code / Copilot / OpenCode), transfers control
by calling this before routing. It enforces the one edgeless rule: `goto` must be
in the acting agent's allowed_next set (or the supervisor / done). No third-party
dependency — stdlib only — so it runs wherever a shell runs.

  python handoff/dispatch.py --from implementer --goto reviewer   # -> ROUTE, exit 0
  python handoff/dispatch.py --from implementer --goto architect  # -> REJECT, exit 1
  python handoff/dispatch.py --list                               # print the map

The transfer map is generated from agent-handoff.yaml into
handoff/generated/allowed_next.json (regenerate with handoff/gen_handoff.sh).
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

DEFAULT_MAP = Path(__file__).resolve().parent / "generated" / "allowed_next.json"


def load_map(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except OSError as exc:
        print(f"ERROR: cannot read transfer map {path}: {exc}", file=sys.stderr)
        print("Run handoff/gen_handoff.sh to generate it.", file=sys.stderr)
        raise SystemExit(2)
    except json.JSONDecodeError as exc:
        print(f"ERROR: transfer map {path} is not valid JSON: {exc}", file=sys.stderr)
        raise SystemExit(2)


def decide(data: dict, src: str, goto: str) -> tuple[bool, str]:
    allowed_next = data["allowed_next"]
    always = set(data.get("always_allowed", []))
    supervisor = data.get("supervisor", "orchestrator")

    if goto in always:
        return True, f"ROUTE {src} -> {goto} (escape hatch / terminal)"
    # The supervisor may dispatch to any known agent (free entry).
    if src == supervisor:
        if goto in allowed_next:
            return True, f"ROUTE {supervisor} -> {goto} (dispatch)"
        return False, f"REJECT {supervisor} -> {goto}: unknown agent"
    if src not in allowed_next:
        return False, f"REJECT: unknown source agent {src!r}"
    if goto in allowed_next[src]:
        return True, f"ROUTE {src} -> {goto}"
    legal = ", ".join(allowed_next[src] + [supervisor, "done"]) or f"{supervisor}, done"
    return False, f"REJECT {src} -> {goto}: not in allowed_next. Legal: {legal}"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--from", dest="src", help="acting agent id")
    ap.add_argument("--goto", help="requested target agent id")
    ap.add_argument("--map", type=Path, default=DEFAULT_MAP, help="transfer map JSON")
    ap.add_argument("--list", action="store_true", help="print the allowed_next map and exit")
    args = ap.parse_args()

    data = load_map(args.map)

    if args.list:
        print(f"supervisor: {data.get('supervisor')}")
        print(f"participants: {', '.join(data.get('participants', []))}")
        print(f"exits: {', '.join(data.get('exits', []))}")
        for src, nexts in data["allowed_next"].items():
            print(f"  {src} -> {', '.join(nexts) if nexts else '(exit only)'}")
        return 0

    if not args.src or not args.goto:
        ap.error("--from and --goto are required (or use --list)")

    ok, message = decide(data, args.src, args.goto)
    print(message)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
