<?php

declare(strict_types=1);

/**
 * Canonical pattern builders for the most-repeated bash command shapes in
 * compositions.php, so a script/tool path is written once (as a name + args) instead of
 * hand-typed in full at every call site. Deliberately NOT exhaustive: patterns with
 * irregular spacing conventions (e.g. most `php tools/ai/ai.php <sub>*` patterns glue the
 * trailing `*` directly to the subcommand with no space, while a few use a space-separated
 * argument) are left as raw strings via `aiPatternAiTool()`'s single-suffix form rather than
 * forced through a two-arg builder that would have to guess spacing and could silently
 * change matching behavior — see `docs/tickets/arch-todo-permission-packs-handoff-*` for the
 * "avoid a complex DSL too early" rationale.
 *
 * Named constants for the four shell-composition operators the immutable floor and several
 * agents deny are also defined here so they are grep-able and reusable by both agent
 * compositions and the shell-composition-deny validator test.
 */

const AI_BASH_PATTERN_PIPE = '* | *';
const AI_BASH_PATTERN_AND_CHAIN = '* && *';
const AI_BASH_PATTERN_SEMICOLON_CHAIN = '* ; *';
const AI_BASH_PATTERN_COMMAND_SUBSTITUTION = '$(*';

/**
 * `bash scripts/ai/<name> <args>` — the space-separated convention every shipped script
 * wrapper uses except `run-repo-tests.sh`, which is a pre-existing, established special case
 * (see `aiPermissionScriptCommandPatterns()` in script-tiers.php, which glues its trailing
 * `*` with no space); keep that one occurrence as a raw literal instead of forcing it here.
 */
function aiPatternAiScript(string $name, string $args = '*'): string
{
    $base = 'bash scripts/ai/' . $name;

    return $args === '' ? $base : $base . ' ' . $args;
}

/**
 * `php tools/ai/ai.php <suffix>` — caller supplies the exact suffix verbatim (including any
 * leading space or glued wildcard), so this is pure prefix deduplication with zero spacing
 * ambiguity, unlike a name+args split would introduce for this family.
 */
function aiPatternAiTool(string $suffix): string
{
    return 'php tools/ai/ai.php ' . $suffix;
}

/**
 * `git <suffix>` — same rationale as aiPatternAiTool(): caller controls exact spacing
 * (some git subcommands glue `*` directly, others need a space before it or an exact
 * argument), so this only removes the repeated `git ` prefix, never guesses spacing.
 */
function aiPatternGit(string $suffix): string
{
    return 'git ' . $suffix;
}
