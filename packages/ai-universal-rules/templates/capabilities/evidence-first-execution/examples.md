Good: source-fix with focused test and explicit verification status.

Good: docs-only change with canonical references and no speculative behavior claims.

Bad: broad refactor while claiming a narrow bug fix.

Bad: snapshot-only update without proving behavior.

## Anti-Freeze Subprocess Recipe (Cross-Platform)

When invoking a long-running subprocess from an AI agent's shell, never sit idle waiting. Pick a per-command timeout up front (see `docs/ai/execution-protocol.md` for the budget table), use a real Process object so you can kill the whole tree, and capture stdout/stderr to files so Windows pipe buffers cannot deadlock.

### PowerShell (preferred on Windows)

`Start-Job` is convenient but its `Stop-Job` does not always kill the child process tree on Windows, and `Wait-Job -Timeout` only polls the job state. Prefer `Start-Process -PassThru` so you receive a real `Process` object and call `WaitForExit(ms)`:

```powershell
$tmpOut = Join-Path $env:TEMP "ai-verify.out.log"
$tmpErr = Join-Path $env:TEMP "ai-verify.err.log"
if (Test-Path $tmpOut) { Remove-Item $tmpOut -Force }
if (Test-Path $tmpErr) { Remove-Item $tmpErr -Force }

$proc = Start-Process -FilePath php `
        -ArgumentList 'tools/ai/ai.php','verify','--changed' `
        -NoNewWindow -PassThru `
        -RedirectStandardOutput $tmpOut `
        -RedirectStandardError $tmpErr

if (-not $proc.WaitForExit(60000)) {
    Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
    $proc.WaitForExit(2000) | Out-Null
    Write-Host "TIMEOUT after 60s - process killed"
}

Write-Host "--- stdout (exit=$($proc.ExitCode)) ---"
if (Test-Path $tmpOut) { Get-Content $tmpOut }
Write-Host "--- stderr ---"
if (Test-Path $tmpErr) { Get-Content $tmpErr }
```

Why this works:

- `Start-Process -PassThru` returns a `System.Diagnostics.Process` whose `WaitForExit(ms)` returns `$false` on timeout (no need to poll).
- `-RedirectStandardOutput` and `-RedirectStandardError` write directly to file descriptors, bypassing the ~4-64 KiB Windows pipe buffer that deadlocks `Start-Job`-style captures.
- `Stop-Process -Force` on Windows kills the process and its child tree, so abandoned `php.exe` subprocesses are not left holding file handles.

### Bash (Linux/macOS, also works under WSL)

`timeout` from coreutils is the canonical wrapper. It sends `SIGTERM` then `SIGKILL` if the process doesn't exit:

```bash
tmp_out=$(mktemp)
tmp_err=$(mktemp)
trap 'rm -f "$tmp_out" "$tmp_err"' EXIT

if ! timeout --kill-after=2s 60s php tools/ai/ai.php verify --changed >"$tmp_out" 2>"$tmp_err"; then
    rc=$?
    if [[ $rc -eq 124 ]]; then
        echo "TIMEOUT after 60s — process killed"
    else
        echo "FAILED with exit $rc"
    fi
fi

echo "--- stdout ---"
cat "$tmp_out"
echo "--- stderr ---"
cat "$tmp_err"
```

`timeout --kill-after=2s 60s` enforces a hard ceiling: terminate after 60s, hard-kill 2s later if still alive. Exit code `124` is the canonical "killed by timeout" signal.

### Common rules (both platforms)

- Pick the budget from the table in `docs/ai/execution-protocol.md` "Long-Running Commands And Anti-Freeze Discipline" — never run a command "to see if it eventually finishes" without an upper bound.
- Always capture stdout and stderr to files, never to in-memory pipes when the child can produce more than a few KiB.
- Always print the exit code, elapsed time, and (on timeout) the last bytes of output so the next agent step has actionable evidence.
- If a command times out, bisect: run a smaller scope (single test class, single file) until you find the offender. Surface the failing scope in your report.</newString>
</invoke>
