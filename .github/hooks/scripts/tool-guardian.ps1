$mode = if ($env:GUARD_MODE) { $env:GUARD_MODE.ToLowerInvariant() } else { 'block' }

if ($env:SKIP_TOOL_GUARD -eq 'true') {
    exit 0
}

$raw = [Console]::In.ReadToEnd()

if ([string]::IsNullOrWhiteSpace($raw)) {
    exit 0
}

try {
    $payload = $raw | ConvertFrom-Json -ErrorAction Stop
} catch {
    Write-Error 'Tool Guardian: unable to parse hook payload JSON.'
    if ($mode -eq 'warn') {
        exit 0
    }

    exit 1
}

$toolName = ''
$toolInput = ''

if ($null -ne $payload.toolName) {
    $toolName = [string]$payload.toolName
}

if ($null -ne $payload.toolInput) {
    if ($payload.toolInput -is [string]) {
        $toolInput = $payload.toolInput
    } else {
        $toolInput = $payload.toolInput | ConvertTo-Json -Compress -Depth 8
    }
}

$combined = ($toolName + ' ' + $toolInput).ToLowerInvariant()

$rules = @(
    @{ Pattern = 'git\s+reset\s+--hard'; Message = 'Avoid destructive git rewind. Use a safer targeted recovery path.' },
    @{ Pattern = 'git\s+push\s+--force(?!-with-lease)'; Message = 'Avoid force pushing. Prefer --force-with-lease or a feature branch.' },
    @{ Pattern = 'git\s+clean\s+-'; Message = 'Avoid destructive git clean. Use a narrower recovery path.' },
    @{ Pattern = 'git\s+(checkout|restore)\s+--'; Message = 'Avoid destructive git file restore. Review the specific file and use a safer path.' },
    @{ Pattern = 'rm\s+-rf'; Message = 'Avoid destructive recursive delete. Use a narrower path or manual review.' },
    @{ Pattern = 'rmdir\s+/(s|q)'; Message = 'Avoid destructive Windows directory removal. Use a narrower path or manual review.' },
    @{ Pattern = 'del\s+/(s|q)'; Message = 'Avoid destructive Windows file deletion. Use a narrower path or manual review.' },
    @{ Pattern = '(curl|wget).*\|\s*(sh|bash|zsh|python|python3|php|node|ruby)'; Message = 'Avoid remote pipe-to-shell execution. Download and inspect first.' },
    @{ Pattern = '(curl|wget|nc|ncat|netcat)\s+.*(-d|--data|--upload-file|--data-binary)'; Message = 'Possible data exfiltration pattern detected. Review carefully before continuing.' },
    @{ Pattern = '(chmod|chown|chgrp)\s+'; Message = 'Avoid permission mutation by default. Review the target and approval posture first.' },
    @{ Pattern = '(cat|bat|less|head|tail)\s+.*\.env(?!\.example)'; Message = 'Avoid direct reading of .env files. Review secrets policy first.' },
    @{ Pattern = '(\.env|credentials|secret|token|id_rsa)'; Message = 'This action may touch secrets or local credentials. Review carefully first.' },
    @{ Pattern = '(~|\$home|/home/[^/\s]+|/users/[^/\s]+)/\.ssh(/|\s|$)'; Message = 'Avoid touching ~/.ssh. Private keys must never be read or exfiltrated.' },
    @{ Pattern = '\.aws/credentials'; Message = 'Avoid touching ~/.aws/credentials. Cloud credentials must never be read or exfiltrated.' },
    @{ Pattern = '(^|[^\w])\.npmrc([^\w]|$)'; Message = 'Avoid touching .npmrc. It commonly contains registry auth tokens.' },
    @{ Pattern = '(^|[^\w])\.netrc([^\w]|$)'; Message = 'Avoid touching .netrc. It commonly contains plaintext credentials.' },
    @{ Pattern = '\S+\.(pem|key)([^\w]|$)'; Message = 'Avoid touching private key material (*.pem / *.key). Review secrets policy first.' },
    @{ Pattern = 'base64\s+(-d|--decode|-d\w*).*\|\s*(sh|bash|zsh|python|python3|php|node|ruby)'; Message = 'Blocked base64-decode piped to a shell (obfuscated execution).' }
)

$guardHits = @()

foreach ($rule in $rules) {
    if ($combined -match $rule.Pattern) {
        $guardHits += $rule.Message
    }
}

if ($guardHits.Count -eq 0) {
    exit 0
}

$uniqueMatches = $guardHits | Select-Object -Unique

Write-Error ("Tool Guardian blocked '{0}' because:`n- {1}" -f $toolName, ($uniqueMatches -join "`n- "))

if ($mode -eq 'warn') {
    exit 0
}

exit 1
