<?php

declare(strict_types=1);

/**
 * Extract the heredoc body inside the usage() function. Returns the raw help
 * text (without the heredoc delimiters), or '' when not found.
 *
 * @param array<int,string> $lines
 */
function shIntrospectExtractUsageBlock(array $lines): string
{
    $inUsage = false;
    $braceDepth = 0;
    $collecting = false;
    $delimiter = null;
    $collected = [];

    foreach ($lines as $line) {
        if (!$inUsage) {
            // usage() { OR function usage { OR function usage() {
            if (preg_match('/^\s*(function\s+)?usage\s*(\(\s*\))?\s*\{/', $line)) {
                $inUsage = true;
                $braceDepth = substr_count($line, '{') - substr_count($line, '}');
            }
            continue;
        }

        if ($collecting) {
            // Heredoc terminator: delimiter alone on its own line (optionally
            // indented when <<- was used; we accept leading whitespace).
            if (preg_match('/^\s*' . preg_quote((string) $delimiter, '/') . '\s*$/', $line)) {
                $collecting = false;
                $delimiter = null;
                continue;
            }
            $collected[] = $line;
            continue;
        }

        // Detect a heredoc opener: cat <<'EOF' / <<EOF / <<-"EOF".
        if (preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line, $m)) {
            $collecting = true;
            $delimiter = $m[1];
            continue;
        }

        $braceDepth += substr_count($line, '{') - substr_count($line, '}');
        if ($braceDepth <= 0) {
            break;
        }
    }

    return implode("\n", $collected);
}

/**
 * Produce a copy of the source lines with all heredoc bodies blanked out so
 * help/usage text is never parsed as code. Line count is preserved.
 *
 * @param array<int,string> $lines
 * @return array<int,string>
 */
function shIntrospectStripHeredocs(array $lines): array
{
    $out = [];
    $delimiter = null;

    foreach ($lines as $line) {
        if ($delimiter !== null) {
            // Inside a heredoc body: blank it but keep the line slot.
            if (preg_match('/^\s*' . preg_quote($delimiter, '/') . '\s*$/', $line)) {
                $delimiter = null;
            }
            $out[] = '';
            continue;
        }

        // Opener detection. Ignore here-strings (<<<) and `$(...)`. Capture the
        // delimiter token; require <<word or <<'word'/<<"word"/<<-word.
        if (preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line)
            && !preg_match('/<<</', $line)) {
            preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?/', $line, $m);
            $delimiter = $m[1];
            // Keep the opener line itself (it is code), only blank the body.
            $out[] = $line;
            continue;
        }

        $out[] = $line;
    }

    return $out;
}

/**
 * Extract function definitions from the (heredoc-stripped) code lines.
 * Detects: name() {, function name {, function name() {.
 *
 * @param array<int,string> $codeLines
 * @return array<string,array<string,mixed>> keyed by function name
 */
function shIntrospectExtractFunctions(array $codeLines): array
{
    $functions = [];

    foreach ($codeLines as $idx => $line) {
        if (preg_match('/^\s*(?:function\s+)([A-Za-z_][A-Za-z0-9_-]*)\s*(?:\(\s*\))?\s*\{/', $line, $m)
            || preg_match('/^\s*([A-Za-z_][A-Za-z0-9_-]*)\s*\(\s*\)\s*\{/', $line, $m)) {
            $name = $m[1];
            if (isset($functions[$name])) {
                continue;
            }
            $functions[$name] = [
                'name' => $name,
                'line' => $idx + 1,
                'source' => 'definition',
                'confidence' => 95,
            ];
        }
    }

    return $functions;
}

/**
 * Walk the code lines and extract every `case <subject> in ... esac` with its
 * branches. Each branch records labels, the start line, and the body lines.
 *
 * @param array<int,string> $codeLines
 * @param array<string,array<string,mixed>> $functions
 * @return array<int,array<string,mixed>> list of branch records:
 *   { subject, labels[], line, body, enclosing_function }
 */
function shIntrospectExtractCaseBranches(array $codeLines, array $functions): array
{
    $branches = [];
    $total = count($codeLines);

    // Precompute, for each line, the nearest enclosing function name by
    // tracking brace depth from each function header.
    $enclosing = shIntrospectEnclosingFunctions($codeLines, $functions);

    $i = 0;
    while ($i < $total) {
        $line = $codeLines[$i];

        // case SUBJECT in
        if (preg_match('/^\s*case\s+(.+?)\s+in\b/', $line, $cm)) {
            $subject = trim($cm[1]);
            $caseFn = $enclosing[$i] ?? '';
            $i++;

            // Collect branches until matching `esac`.
            while ($i < $total) {
                $bline = $codeLines[$i];

                if (preg_match('/^\s*esac\b/', $bline)) {
                    $i++;
                    break;
                }

                // Nested case inside this case: skip to its esac to avoid
                // double-counting its branches at the outer level.
                if (preg_match('/^\s*case\s+.+?\s+in\b/', $bline)) {
                    $depth = 1;
                    $i++;
                    while ($i < $total && $depth > 0) {
                        if (preg_match('/^\s*case\s+.+?\s+in\b/', $codeLines[$i])) {
                            $depth++;
                        } elseif (preg_match('/^\s*esac\b/', $codeLines[$i])) {
                            $depth--;
                        }
                        $i++;
                    }
                    continue;
                }

                // A branch label line: LABELS) ... ;;  (labels up to the
                // first unescaped `)`). Body runs from this line until `;;`.
                if (preg_match('/^\s*([^()]+?)\)/', $bline, $lm)) {
                    $labelRaw = trim($lm[1]);
                    $labels = array_values(array_filter(array_map('trim', explode('|', $labelRaw)), static fn($s) => $s !== ''));
                    $startLine = $i + 1;
                    $body = [];

                    // Include remainder of the label line after the `)` as body.
                    $afterParen = substr($bline, strpos($bline, ')') + 1);
                    $body[] = $afterParen;

                    // Detect ;; on the label line itself.
                    if (preg_match('/;;\s*$/', $bline) || strpos($afterParen, ';;') !== false) {
                        $branches[] = shIntrospectBranchRecord($subject, $labels, $startLine, $body, $caseFn);
                        $i++;
                        continue;
                    }

                    $i++;
                    // Track nested case...esac so an inner `;;` does not
                    // prematurely terminate this (outer) branch body.
                    $nestDepth = 0;
                    while ($i < $total) {
                        $bodyLine = $codeLines[$i];

                        if ($nestDepth === 0 && preg_match('/^\s*esac\b/', $bodyLine)) {
                            // Unterminated branch ended by the enclosing esac.
                            break;
                        }

                        if (preg_match('/^\s*case\s+.+?\s+in\b/', $bodyLine)) {
                            $nestDepth++;
                            $body[] = $bodyLine;
                            $i++;
                            continue;
                        }
                        if ($nestDepth > 0 && preg_match('/^\s*esac\b/', $bodyLine)) {
                            $nestDepth--;
                            $body[] = $bodyLine;
                            $i++;
                            continue;
                        }

                        if ($nestDepth === 0 && (preg_match('/;;\s*$/', $bodyLine) || preg_match('/;;\s/', $bodyLine))) {
                            // Body up to (and including) text before ;;.
                            $body[] = preg_replace('/;;.*$/', '', $bodyLine) ?? '';
                            $i++;
                            break;
                        }

                        $body[] = $bodyLine;
                        $i++;
                    }

                    $branches[] = shIntrospectBranchRecord($subject, $labels, $startLine, $body, $caseFn);
                    continue;
                }

                $i++;
            }
            continue;
        }

        $i++;
    }

    return $branches;
}

/**
 * @param array<int,string> $labels
 * @param array<int,string> $body
 * @return array<string,mixed>
 */
function shIntrospectBranchRecord(string $subject, array $labels, int $startLine, array $body, string $caseFn): array
{
    return [
        'subject' => $subject,
        'labels' => $labels,
        'line' => $startLine,
        'body' => implode("\n", $body),
        'enclosing_function' => $caseFn,
    ];
}

/**
 * Map each line index to the name of its enclosing function (or '').
 *
 * @param array<int,string> $codeLines
 * @param array<string,array<string,mixed>> $functions
 * @return array<int,string>
 */
function shIntrospectEnclosingFunctions(array $codeLines, array $functions): array
{
    $map = array_fill(0, count($codeLines), '');

    foreach ($functions as $fn) {
        $start = $fn['line'] - 1;
        $depth = 0;
        $started = false;
        for ($j = $start, $n = count($codeLines); $j < $n; $j++) {
            $depth += substr_count($codeLines[$j], '{') - substr_count($codeLines[$j], '}');
            if (!$started && substr_count($codeLines[$j], '{') > 0) {
                $started = true;
            }
            // Only claim lines not already owned by a deeper (later-starting)
            // function. Since we iterate functions in definition order, the
            // last writer for a line wins; prefer the innermost by writing the
            // function whose header is closest above. We approximate by only
            // setting when currently empty OR the existing owner starts earlier.
            if ($map[$j] === '') {
                $map[$j] = $fn['name'];
            }
            if ($started && $depth <= 0) {
                break;
            }
        }
    }

    return $map;
}
