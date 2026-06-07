#!/usr/bin/env sh
# Tool Guardian (POSIX sibling of tool-guardian.ps1).
#
# Reads a JSON hook payload on stdin and blocks destructive / exfiltration / secret-touching
# tool calls. Dependency-free: uses only POSIX sh + grep (no jq, no yq, no PHP at runtime).
# Rule parity with tool-guardian.ps1 is asserted by the test suite.
#
# Env:
#   GUARD_MODE=warn|block   (default: block) — warn exits 0 even on a hit.
#   SKIP_TOOL_GUARD=true     — bypass entirely (exit 0).
set -u

mode="block"
if [ "${GUARD_MODE:-}" != "" ]; then
    mode="$(printf '%s' "$GUARD_MODE" | tr '[:upper:]' '[:lower:]')"
fi

if [ "${SKIP_TOOL_GUARD:-}" = "true" ]; then
    exit 0
fi

raw="$(cat)"

# Empty payload: nothing to inspect.
if [ -z "$(printf '%s' "$raw" | tr -d '[:space:]')" ]; then
    exit 0
fi

# Lowercase the whole payload; we match toolName + toolInput together exactly like the ps1,
# which serializes both into one lowercased string. Working from the raw JSON is dependency-free
# and strictly broader (never misses a field), which is the safe direction for a deny guard.
combined="$(printf '%s' "$raw" | tr '[:upper:]' '[:lower:]' | tr '\n' ' ')"

hits=""
add_hit() {
    # $1 = extended-regex pattern, $2 = message
    if printf '%s' "$combined" | grep -Eq "$1"; then
        hits="${hits}- $2
"
    fi
}

# Rule set mirrors tool-guardian.ps1 (keep in sync; parity is test-enforced).
add_hit 'git[[:space:]]+reset[[:space:]]+--hard' 'Avoid destructive git rewind. Use a safer targeted recovery path.'
add_hit 'git[[:space:]]+push[[:space:]]+--force([^-]|$)' 'Avoid force pushing. Prefer --force-with-lease or a feature branch.'
add_hit 'git[[:space:]]+clean[[:space:]]+-' 'Avoid destructive git clean. Use a narrower recovery path.'
add_hit 'git[[:space:]]+(checkout|restore)[[:space:]]+--' 'Avoid destructive git file restore. Review the specific file and use a safer path.'
add_hit 'rm[[:space:]]+-rf' 'Avoid destructive recursive delete. Use a narrower path or manual review.'
add_hit 'rmdir[[:space:]]+/(s|q)' 'Avoid destructive Windows directory removal. Use a narrower path or manual review.'
add_hit 'del[[:space:]]+/(s|q)' 'Avoid destructive Windows file deletion. Use a narrower path or manual review.'
add_hit '(curl|wget).*\|[[:space:]]*(sh|bash|zsh|python|python3|php|node|ruby)' 'Avoid remote pipe-to-shell execution. Download and inspect first.'
add_hit '(curl|wget|nc|ncat|netcat)[[:space:]]+.*(-d|--data|--upload-file|--data-binary)' 'Possible data exfiltration pattern detected. Review carefully before continuing.'
add_hit '(chmod|chown|chgrp)[[:space:]]' 'Avoid permission mutation by default. Review the target and approval posture first.'
add_hit '(cat|bat|less|head|tail)[[:space:]].*\.env([^.]|$)' 'Avoid direct reading of .env files. Review secrets policy first.'
add_hit '(\.env|credentials|secret|token|id_rsa)' 'This action may touch secrets or local credentials. Review carefully first.'
# shellcheck disable=SC2016 # literal $home is a regex alternative matched in the payload, not a shell var
add_hit '(~|\$home|/home/[^/[:space:]]+|/users/[^/[:space:]]+)/\.ssh(/|[[:space:]]|$)' 'Avoid touching ~/.ssh. Private keys must never be read or exfiltrated.'
add_hit '\.aws/credentials' 'Avoid touching ~/.aws/credentials. Cloud credentials must never be read or exfiltrated.'
add_hit '(^|[^[:alnum:]_])\.npmrc([^[:alnum:]_]|$)' 'Avoid touching .npmrc. It commonly contains registry auth tokens.'
add_hit '(^|[^[:alnum:]_])\.netrc([^[:alnum:]_]|$)' 'Avoid touching .netrc. It commonly contains plaintext credentials.'
add_hit '[^[:space:]"]+\.(pem|key)([^[:alnum:]_]|$)' 'Avoid touching private key material (*.pem / *.key). Review secrets policy first.'
add_hit 'base64[[:space:]]+(-d|--decode|-d[[:alpha:]]*).*\|[[:space:]]*(sh|bash|zsh|python|python3|php|node|ruby)' 'Blocked base64-decode piped to a shell (obfuscated execution).'

if [ -z "$hits" ]; then
    exit 0
fi

printf 'Tool Guardian blocked the command because:\n%s' "$hits" >&2

if [ "$mode" = "warn" ]; then
    exit 0
fi

exit 1
