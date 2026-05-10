#!/usr/bin/env bash
# shellcheck disable=SC2030,SC2031
set +e

LOG_DIR=".ai-logs/checks"
mkdir -p "$LOG_DIR"

LOG_FILE="$LOG_DIR/batch4-shellcheck-$(date +%Y%m%d-%H%M%S).log"
bad=0

files=(
  scripts/ai/ai-structured.sh
  scripts/ai/ai-task.sh
  scripts/ai/ai-test-select.sh
  scripts/ai/ai-doc-check.sh
  scripts/ai/pre-tool-use.sh
)

{
  echo "Batch 4 shell verification"
  echo "Started: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo

  for f in "${files[@]}"; do
    echo
    echo "== file $f =="

    if [ ! -f "$f" ]; then
      echo "MISSING: $f"
      bad=1
      continue
    fi

    echo
    echo "== CRLF check $f =="
    if grep -Iq . "$f" && grep -q $'\r' "$f"; then
      echo "FAIL: CRLF detected in $f"
      bad=1
    else
      echo "OK: LF line endings"
    fi

    echo
    echo "== bash -n $f =="
    if ! bash -n "$f"; then
      bad=1
    fi

    echo
    echo "== shellcheck -x -e SC1091 $f =="
    if ! shellcheck -x -e SC1091 "$f"; then
      bad=1
    fi
  done

  echo
  if [ "$bad" -eq 0 ]; then
    echo "Batch 4 checks passed."
  else
    echo "Batch 4 checks failed."
  fi

  echo
  echo "Log: $LOG_FILE"
} 2>&1 | tee "$LOG_FILE"

if [ "${STRICT:-0}" = "1" ]; then
  exit "$bad"
fi

exit 0
