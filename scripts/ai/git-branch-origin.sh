#!/usr/bin/env bash
# Detect the branch the current branch was most likely created from ("branched off").
#
# Git does not record the parent branch, so this infers it: for every candidate
# branch (local + remote-tracking), compute the merge-base with HEAD and pick the
# candidate whose merge-base is CLOSEST to HEAD (fewest commits on base..HEAD).
# That candidate is almost always the branch you checked out from.
#
# Ties are broken by preference: release-like patterns and branches sharing the
# current branch's prefix rank first, then origin/main / main as final fallback.
#
# Output: the preferred BRANCH NAME (not a raw commit), plus the merge-base commit.

set -euo pipefail

# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins git

# Patterns that mark a branch as a likely long-lived base (release/integration).
# Override with GIT_BASE_PATTERNS (newline- or space-separated glob list).
DEFAULT_BASE_PATTERNS='main master develop dev release/* releases/* phase* stage* staging* stat-* nuxt-* v[0-9]* *-[0-9]*.[0-9]*'

usage() {
    cat <<'EOF'
Usage:
  git-branch-origin.sh [--json] [--field name|base|count|all]

Detects the branch the current branch was most likely created from.

Options:
  --field name    print only the parent branch name (default)
  --field base    print only the merge-base commit sha
  --field count   print only the commit distance (base..HEAD)
  --field all     print "name<TAB>base<TAB>count"
  --json          emit a JSON envelope with all fields and candidates
  --help, -h      show this help

Environment:
  GIT_ORIGIN_REF       force a specific base ref, skip detection
  GIT_BASE_PATTERNS    override preferred base-branch glob patterns
  GIT_ORIGIN_INCLUDE_REMOTE   set to 0 to skip remote-tracking branches
EOF
}

FIELD="name"
OUTPUT_JSON=0
while [[ $# -gt 0 ]]; do
    case "$1" in
    --help | -h)
        usage
        exit 0
        ;;
    --json)
        OUTPUT_JSON=1
        shift
        ;;
    --field)
        FIELD="${2:?--field requires a value}"
        shift 2
        ;;
    --field=*)
        FIELD="${1#*=}"
        shift
        ;;
    *) die "unknown option: $1" ;;
    esac
done

case "$FIELD" in
name | base | count | all) ;;
*) die "invalid --field: $FIELD (expected name|base|count|all)" ;;
esac

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "not inside a git repository"

current_branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'HEAD')"
head_sha="$(git rev-parse HEAD 2>/dev/null || true)"
[[ -n "$head_sha" ]] || die "cannot resolve HEAD"

# Read base patterns into an array.
read -r -a base_patterns <<<"${GIT_BASE_PATTERNS:-$DEFAULT_BASE_PATTERNS}"

# True if a branch short-name matches any preferred base pattern.
matches_base_pattern() {
    local name="$1" pat
    for pat in "${base_patterns[@]}"; do
        # shellcheck disable=SC2053
        [[ "$name" == $pat ]] && return 0
    done
    return 1
}

# True if a branch shares a meaningful prefix token with the current branch
# (e.g. current "phase3.81.0-fix" vs candidate "phase3.81.0").
shares_prefix() {
    local name="$1"
    local cur="${current_branch##*/}"
    local cand="${name##*/}"
    [[ -n "$cur" && "$cur" != "HEAD" ]] || return 1
    # Compare the leading alphanumeric token of each.
    local cur_tok="${cur%%[-_./]*}"
    local cand_tok="${cand%%[-_./]*}"
    [[ -n "$cur_tok" && "$cur_tok" == "$cand_tok" ]]
}

# Collect candidate branch ref-names (short), local + remote-tracking.
collect_candidates() {
    git for-each-ref --format='%(refname:short)' refs/heads/
    if [[ "${GIT_ORIGIN_INCLUDE_REMOTE:-1}" == "1" ]]; then
        git for-each-ref --format='%(refname:short)' refs/remotes/
    fi
}

# Each scored line: "<priority> <count> <name> <base_sha>"
# priority: 0 = pattern match, 1 = prefix share, 2 = other. Lower is better.
# Lower count (distance) is better within the same priority.
declare -a scored=()

while IFS= read -r ref; do
    [[ -n "$ref" ]] || continue
    # Skip noise and self.
    case "$ref" in
    "$current_branch" | origin | */HEAD | HEAD) continue ;;
    esac
    # Resolve to a commit; skip symbolic/unresolvable refs.
    git rev-parse --verify --quiet "$ref^{commit}" >/dev/null 2>&1 || continue

    base="$(git merge-base HEAD "$ref" 2>/dev/null || true)"
    [[ -n "$base" ]] || continue
    # A candidate whose merge-base equals HEAD is an ancestor-or-equal (we are
    # behind/at it); it is not a "branched off from" point, skip it.
    [[ "$base" == "$head_sha" ]] && continue

    count="$(git rev-list --count "$base..HEAD" 2>/dev/null || printf '999999')"

    priority=2
    if matches_base_pattern "$ref"; then
        priority=0
    elif shares_prefix "$ref"; then
        priority=1
    fi

    scored+=("$priority $count $ref $base")
done < <(collect_candidates | sort -u)

if [[ ${#scored[@]} -eq 0 ]]; then
    # Fallback chain when no candidate produced a usable merge-base.
    if [[ -n "${GIT_ORIGIN_REF:-}" ]]; then
        fallback="$GIT_ORIGIN_REF"
    else
        fallback=""
        for c in origin/main origin/master main master; do
            if git rev-parse --verify --quiet "$c^{commit}" >/dev/null 2>&1; then
                fallback="$c"
                break
            fi
        done
    fi
    [[ -n "$fallback" ]] || die "could not determine a branch origin"
    best_name="$fallback"
    best_base="$(git merge-base HEAD "$fallback" 2>/dev/null || git rev-parse "$fallback")"
    best_count="$(git rev-list --count "$best_base..HEAD" 2>/dev/null || printf '0')"
else
    # Honor an explicit override before scoring output.
    if [[ -n "${GIT_ORIGIN_REF:-}" ]]; then
        best_name="$GIT_ORIGIN_REF"
        best_base="$(git merge-base HEAD "$GIT_ORIGIN_REF" 2>/dev/null || git rev-parse "$GIT_ORIGIN_REF")"
        best_count="$(git rev-list --count "$best_base..HEAD" 2>/dev/null || printf '0')"
    else
        # Sort by priority asc, then count asc; take the first.
        best_line="$(printf '%s\n' "${scored[@]}" | sort -t' ' -k1,1n -k2,2n | head -n1)"
        read -r _prio best_count best_name best_base <<<"$best_line"
    fi
fi

# Normalize a remote-tracking name back to a friendlier display when possible,
# but keep the ref usable for diffing (callers can pass it straight to git).
emit_plain() {
    case "$FIELD" in
    name) printf '%s\n' "$best_name" ;;
    base) printf '%s\n' "$best_base" ;;
    count) printf '%s\n' "$best_count" ;;
    all) printf '%s\t%s\t%s\n' "$best_name" "$best_base" "$best_count" ;;
    esac
}

if [[ "$OUTPUT_JSON" == "1" ]]; then
    candidates_json='[]'
    if [[ ${#scored[@]} -gt 0 ]]; then
        candidates_json="$(printf '%s\n' "${scored[@]}" |
            sort -t' ' -k1,1n -k2,2n |
            jq -R -s 'split("\n") | map(select(length>0)) | map(
                (. / " ") as $p | {priority: ($p[0]|tonumber), distance: ($p[1]|tonumber), name: $p[2], merge_base: $p[3]}
            )')"
    fi
    jq -cn \
        --arg schema "1" \
        --arg status "ok" \
        --arg tool "git-branch-origin" \
        --arg current "$current_branch" \
        --arg name "$best_name" \
        --arg base "$best_base" \
        --argjson count "${best_count:-0}" \
        --argjson candidates "$candidates_json" \
        '{schema: ($schema|tonumber), status: $status, tool: $tool,
          current_branch: $current, origin_branch: $name, merge_base: $base,
          distance: $count, candidates: $candidates, warnings: [], errors: []}'
else
    emit_plain
fi
