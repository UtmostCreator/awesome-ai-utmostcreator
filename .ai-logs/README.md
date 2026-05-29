# AI Evidence Logs

This directory is the canonical **local evidence root** for AI workflow runs.

- `scripts/ai/pre-tool-use.sh` (policy gate) and `scripts/ai/post-tool-use.sh`
  (evidence writer) record tool usage, approvals, and verification evidence here.
- Contents are **gitignored** and safe to delete; only this `README.md` is tracked
  so documentation references to `.ai-logs/README.md` resolve in a fresh checkout.
- Do not commit session logs, evidence dumps, or anything containing secrets.

See `docs/ai/AI-GUARDRAILS.md` and `docs/ai/capabilities/agent-observability-and-evidence/`
for the evidence model and failure taxonomy.
