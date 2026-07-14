<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for OpenCode agent bash permissions.
 *
 * The policy intentionally keeps read-only agents small. Only mutating agents
 * get explicit `ask` entries that promote safe workflows from silent deny to a
 * human prompt. Package/dependency install or add commands remain prompt-gated.
 */
class AgentPermissionPolicyTest extends TestCase
{
    private static string $repoRoot;

    /** @var list<string> */
    private const MUTATING_ROLE_GIT_ASK_PATTERNS = [
        'git add',
        'git commit',
        'git restore',
        'git stash push',
        'git stash pop',
        'git stash apply',
        'git stash drop',
    ];

    /** @var list<string> */
    private const IMPLEMENTER_EXTRA_ASK_PATTERNS = [
        'git fetch',
        'git merge',
        'git pull',
        'git checkout',
        'git switch',
        'git tag',
        'git cherry-pick',
        'git revert',
        'install-mandatory-tools.sh',
        'composer install',
        'composer update',
        'composer require',
        'npm install',
        'npm ci',
        'pnpm install',
        'pnpm add',
        'yarn install',
        'yarn add',
        'bun install',
        'bun add',
    ];

    /** @var list<string> */
    private const READ_ONLY_ALLOW_PATTERNS = [
        'git status',
        'git diff',
        'git log',
    ];

    /** @var list<string> */
    private const FORBIDDEN_ALLOW_PATTERNS = [
        'git push',
        'git reset --hard',
        'git clean -f',
        'git rebase',
        'sudo ',
        'rm -rf',
    ];

    /** @var list<string> */
    private const READ_ONLY_STRICT_DENY_AGENTS = [
        '.opencode/agents/architect.md',
        '.opencode/agents/release-auditor.md',
        '.opencode/agents/researcher.md',
        '.opencode/agents/reviewer.md',
        'packages/ai-universal-rules/templates/core/agents/architect.md',
        'packages/ai-universal-rules/templates/core/agents/release-auditor.md',
        'packages/ai-universal-rules/templates/core/agents/researcher.md',
        'packages/ai-universal-rules/templates/core/agents/reviewer.md',
    ];

    /** @var list<string> */
    private const GIT_MUTATING_AGENT_FILES = [
        '.opencode/agents/bootstrapper.md',
        '.opencode/agents/configuration-maintainer.md',
        '.opencode/agents/implementer.md',
        'packages/ai-universal-rules/templates/core/agents/bootstrapper.md',
        'packages/ai-universal-rules/templates/core/agents/configuration-maintainer.md',
        'packages/ai-universal-rules/templates/core/agents/implementer.md',
        // super-implementer.md is intentionally NOT listed here: it is gitignored
        // (.gitignore:32 — draft agent held back, `id: implementer` collides with
        // implementer.md's own id) and does not exist in a fresh checkout, so a
        // file-path-based test fixture referencing it would fail outside this working
        // tree. Its composition spec is still valid in compositions.php; only the
        // rendered file's shipping is blocked pending that id-collision fix.
    ];

    /** @var list<string> */
    private const IMPLEMENTER_AGENT_FILES = [
        '.opencode/agents/implementer.md',
        'packages/ai-universal-rules/templates/core/agents/implementer.md',
    ];

    /**
     * repository-researcher/repository-reviewer were retired
     * (agent-handoff-governance-20260714, Phase 5a); no shipped agent currently
     * defaults its bash `*` fallback to `ask`.
     *
     * @var list<string>
     */
    private const ASK_DEFAULT_AGENTS = [];

    /** @var list<string> */
    private const REVIEWER_AGENT_FILES = [
        '.opencode/agents/reviewer.md',
        'packages/ai-universal-rules/templates/core/agents/reviewer.md',
    ];

    /** @var list<string> */
    private const EXTERNAL_BOUNDARY_AGENT_FILES = [
        '.opencode/agents/architect.md',
        '.opencode/agents/implementer.md',
        '.opencode/agents/researcher.md',
        '.opencode/agents/reviewer.md',
        'packages/ai-universal-rules/templates/core/agents/architect.md',
        'packages/ai-universal-rules/templates/core/agents/implementer.md',
        'packages/ai-universal-rules/templates/core/agents/researcher.md',
        'packages/ai-universal-rules/templates/core/agents/reviewer.md',
    ];

    /** @var list<string> */
    private const REVIEWER_GIT_REVIEW_ALLOW_PATTERNS = [
        'git merge-base',
        'git range-diff',
        'git diff-tree',
        'git cherry',
        'git for-each-ref',
        'git config --get-regexp ^alias\\\\.',
    ];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }

        self::$repoRoot = $root;
    }

    /** @return list<array{0:string}> */
    public static function allAgentProvider(): array
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            return [];
        }

        $cases = [];
        foreach ([$root . '/.opencode/agents/*.md', $root . '/packages/ai-universal-rules/templates/core/agents/*.md'] as $glob) {
            foreach (glob($glob) ?: [] as $file) {
                $relative = ltrim(substr($file, strlen($root)), '/');
                $cases[$relative] = [$relative];
            }
        }
        ksort($cases);

        return array_values($cases);
    }

    /** @return list<array{0:string}> */
    public static function gitMutatingAgentProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], self::GIT_MUTATING_AGENT_FILES);
    }

    /** @return list<array{0:string}> */
    public static function implementerAgentProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], self::IMPLEMENTER_AGENT_FILES);
    }

    /** @return list<array{0:string}> */
    public static function readOnlyStrictDenyAgentProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], self::READ_ONLY_STRICT_DENY_AGENTS);
    }

    /** @return list<array{0:string}> */
    public static function projectConfigProvider(): array
    {
        return [
            ['opencode.jsonc'],
            ['packages/ai-universal-rules/templates/core/opencode.json'],
        ];
    }

    /** @return list<array{0:string}> */
    public static function reviewerAgentProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], self::REVIEWER_AGENT_FILES);
    }

    /** @return list<array{0:string}> */
    public static function externalBoundaryAgentProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], self::EXTERNAL_BOUNDARY_AGENT_FILES);
    }

    #[DataProvider('gitMutatingAgentProvider')]
    public function testGitMutatingAgentsAskBeforeLocalGitMutations(string $relativePath): void
    {
        $contents = $this->loadFrontmatter($relativePath);

        $missing = [];
        foreach (self::MUTATING_ROLE_GIT_ASK_PATTERNS as $needle) {
            if (!$this->isPatternAskOrAllow($contents, $needle)) {
                $missing[] = $needle;
            }
        }

        self::assertSame([], $missing, sprintf('%s is missing git mutation ask entries: %s', $relativePath, implode(', ', $missing)));
    }

    #[DataProvider('implementerAgentProvider')]
    public function testImplementerAsksBeforeBranchAndDependencyMutations(string $relativePath): void
    {
        $contents = $this->loadFrontmatter($relativePath);

        $missing = [];
        foreach (self::IMPLEMENTER_EXTRA_ASK_PATTERNS as $needle) {
            if (!$this->isPatternAskOrAllow($contents, $needle)) {
                $missing[] = $needle;
            }
        }

        self::assertSame([], $missing, sprintf('%s is missing implementer ask entries: %s', $relativePath, implode(', ', $missing)));
    }

    #[DataProvider('readOnlyStrictDenyAgentProvider')]
    public function testReadOnlyAgentsDoNotCarryMutationAskBlock(string $relativePath): void
    {
        $contents = $this->loadFrontmatter($relativePath);

        $violations = [];
        foreach (array_merge(self::MUTATING_ROLE_GIT_ASK_PATTERNS, self::IMPLEMENTER_EXTRA_ASK_PATTERNS) as $needle) {
            if ($this->isPatternAskOrAllow($contents, $needle)) {
                $violations[] = $needle;
            }
        }

        self::assertSame([], $violations, sprintf('%s should stay read-only and compact, but contains mutation entries: %s', $relativePath, implode(', ', $violations)));
    }

    #[DataProvider('allAgentProvider')]
    public function testStrictDenyAgentsAllowBasicReadOnlyGitInspection(string $relativePath): void
    {
        if (in_array($relativePath, self::ASK_DEFAULT_AGENTS, true)) {
            $this->markTestSkipped(sprintf('%s defaults to ask.', $relativePath));
        }

        $contents = $this->loadFrontmatter($relativePath);
        if (!$this->usesStrictDeny($contents)) {
            $this->markTestSkipped(sprintf('%s does not use strict bash deny.', $relativePath));
        }

        $missing = [];
        foreach (self::READ_ONLY_ALLOW_PATTERNS as $needle) {
            if (!$this->isPatternAllow($contents, $needle)) {
                $missing[] = $needle;
            }
        }

        self::assertSame([], $missing, sprintf('%s is missing read-only git allow entries: %s', $relativePath, implode(', ', $missing)));
    }

    #[DataProvider('allAgentProvider')]
    public function testAgentNeverAllowsForbiddenPatterns(string $relativePath): void
    {
        $contents = $this->loadFrontmatter($relativePath);

        $violations = [];
        foreach (self::FORBIDDEN_ALLOW_PATTERNS as $needle) {
            if ($this->isPatternAllow($contents, $needle)) {
                $violations[] = $needle;
            }
        }

        self::assertSame([], $violations, sprintf('%s allows forbidden patterns: %s', $relativePath, implode(', ', $violations)));
    }

    #[DataProvider('reviewerAgentProvider')]
    public function testReviewerAgentsAllowReadOnlyReviewGitWithoutBroadBranchMutation(string $relativePath): void
    {
        $contents = $this->loadFrontmatter($relativePath);

        self::assertFalse(
            $this->isExactPatternAllow($contents, 'git branch*'),
            sprintf('%s must not allow broad git branch* because it can match destructive branch deletion.', $relativePath)
        );
        self::assertFalse(
            $this->isExactPatternAllow($contents, 'git cherry*'),
            sprintf('%s must not allow broad git cherry* because it can match mutating cherry-pick.', $relativePath)
        );

        $missing = [];
        foreach (self::REVIEWER_GIT_REVIEW_ALLOW_PATTERNS as $needle) {
            if (!$this->isPatternAllow($contents, $needle) && !$this->isExactPatternAllow($contents, $needle)) {
                $missing[] = $needle;
            }
        }

        self::assertSame([], $missing, sprintf('%s is missing read-only review git allow entries: %s', $relativePath, implode(', ', $missing)));
    }

    #[DataProvider('allAgentProvider')]
    public function testAgentFrontmatterIsParseable(string $relativePath): void
    {
        $full = self::$repoRoot . '/' . $relativePath;
        self::assertFileExists($full);

        $raw = file_get_contents($full);
        self::assertNotFalse($raw, "Could not read $relativePath");
        self::assertStringStartsWith('---', $raw, sprintf('%s must begin with YAML frontmatter.', $relativePath));
        self::assertNotFalse(strpos($raw, "\n---", 4), sprintf('%s frontmatter has no terminating delimiter.', $relativePath));
    }

    #[DataProvider('projectConfigProvider')]
    public function testProjectConfigAsksBeforeInstallApply(string $relativePath): void
    {
        $bash = $this->loadProjectBashPermissions($relativePath);

        foreach (['php tools/ai/ai.php install * --apply', 'php tools/ai/install-ai-kit.php *'] as $pattern) {
            self::assertSame('ask', $bash[$pattern] ?? null, sprintf('%s must ask for %s', $relativePath, $pattern));
        }
    }

    /**
     * Phase 4 permission-drift guard: the gateway `tool:run *` allow rule must never
     * extend a plain `allow` to any `--apply` invocation. Any `tool:run` pattern that
     * mentions `--apply` must resolve to `ask` (or `deny`), so the no-prompt mutation
     * lane cannot silently re-open in either the template or the rendered config.
     */
    #[DataProvider('projectConfigProvider')]
    public function testProjectConfigNeverPlainAllowsToolRunApply(string $relativePath): void
    {
        $bash = $this->loadProjectBashPermissions($relativePath);

        $violations = [];
        foreach ($bash as $pattern => $decision) {
            if (str_contains($pattern, 'tool:run') && str_contains($pattern, '--apply') && $decision === 'allow') {
                $violations[] = "$pattern => allow";
            }
        }

        self::assertSame([], $violations, sprintf('%s must not plain-allow tool:run --apply: %s', $relativePath, implode('; ', $violations)));

        // And the explicit ask override must be present so --apply always prompts.
        self::assertSame(
            'ask',
            $bash['php tools/ai/ai.php tool:run * --apply*'] ?? null,
            sprintf('%s must ask before tool:run --apply (Fix A defense-in-depth)', $relativePath)
        );

        // last-match-wins: the --apply ask override must come AFTER the tool:run * allow.
        $keys = array_keys($bash);
        $allowPos = array_search('php tools/ai/ai.php tool:run *', $keys, true);
        $askPos = array_search('php tools/ai/ai.php tool:run * --apply*', $keys, true);
        self::assertIsInt($allowPos, sprintf('%s must define tool:run * allow', $relativePath));
        self::assertIsInt($askPos, sprintf('%s must define tool:run * --apply* ask', $relativePath));
        self::assertGreaterThan(
            $allowPos,
            $askPos,
            sprintf('%s: the --apply ask rule must come after tool:run * allow (last-match-wins)', $relativePath)
        );
    }

    #[DataProvider('projectConfigProvider')]
    public function testProjectConfigNeverAllowsForbiddenPatterns(string $relativePath): void
    {
        $bash = $this->loadProjectBashPermissions($relativePath);

        $violations = [];
        foreach (self::FORBIDDEN_ALLOW_PATTERNS as $needle) {
            foreach ($bash as $pattern => $decision) {
                if ($decision === 'allow' && str_contains($pattern, $needle)) {
                    $violations[] = "$pattern => allow";
                }
            }
        }

        self::assertSame([], $violations, sprintf('%s contains forbidden allow patterns: %s', $relativePath, implode('; ', $violations)));
    }

    #[DataProvider('projectConfigProvider')]
    public function testProjectConfigAsksForExternalDirectoryAndLoadsBoundaryDocs(string $relativePath): void
    {
        $config = $this->loadProjectConfig($relativePath);

        self::assertSame('ask', $config['permission']['external_directory'] ?? null, sprintf('%s must ask before external-directory access.', $relativePath));
        self::assertContains('docs/ai/project-context.md', $config['instructions'] ?? [], sprintf('%s must load project context.', $relativePath));
        self::assertContains('docs/ai/project/project-interaction.md', $config['instructions'] ?? [], sprintf('%s must load project interaction rules.', $relativePath));
    }

    #[DataProvider('externalBoundaryAgentProvider')]
    public function testKeyAgentsDocumentExternalBoundaryPolicy(string $relativePath): void
    {
        $raw = $this->loadFile($relativePath);

        self::assertStringContainsString('docs/ai/project-context.md', $raw, sprintf('%s must reference project context.', $relativePath));
        self::assertStringContainsString('docs/ai/project/project-interaction.md', $raw, sprintf('%s must reference project interaction.', $relativePath));
        self::assertStringContainsString('external_directory: ask', $raw, sprintf('%s must mention OpenCode external directory prompt.', $relativePath));
    }

    private function loadFrontmatter(string $relativePath): string
    {
        $raw = $this->loadFile($relativePath);

        if (!str_starts_with($raw, '---')) {
            return $raw;
        }

        $end = strpos($raw, "\n---", 4);
        return $end === false ? $raw : substr($raw, 0, $end);
    }

    /** @return array<string,string> */
    private function loadProjectBashPermissions(string $relativePath): array
    {
        $data = $this->loadProjectConfig($relativePath);
        self::assertIsArray($data['permission']['bash'] ?? null, sprintf('%s must declare permission.bash', $relativePath));

        /** @var array<string,string> $bash */
        $bash = $data['permission']['bash'];
        return $bash;
    }

    /** @return array<string,mixed> */
    private function loadProjectConfig(string $relativePath): array
    {
        $raw = file_get_contents(self::$repoRoot . '/' . $relativePath);
        self::assertNotFalse($raw);

        // opencode.json ships as JSONC (managed soft-notice comment header), so strip
        // comments before decoding. Plain .json files are unaffected.
        if (str_ends_with(strtolower($relativePath), '.json') || str_ends_with(strtolower($relativePath), '.jsonc')) {
            $raw = $this->stripJsonCommentsForTest($raw);
        }

        $data = json_decode($raw, true);
        self::assertIsArray($data, sprintf('%s must be valid JSON', $relativePath));

        return $data;
    }

    private function loadFile(string $relativePath): string
    {
        $raw = file_get_contents(self::$repoRoot . '/' . $relativePath);
        if ($raw === false) {
            throw new \RuntimeException("Could not read $relativePath");
        }

        return $raw;
    }

    private function stripJsonCommentsForTest(string $input): string
    {
        $out = '';
        $inString = false;
        $escaped = false;
        $inLine = false;
        $inBlock = false;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            $next = $i + 1 < $length ? $input[$i + 1] : '';

            if ($inLine) {
                if ($char === "\n") {
                    $inLine = false;
                    $out .= $char;
                }
                continue;
            }
            if ($inBlock) {
                if ($char === '*' && $next === '/') {
                    $inBlock = false;
                    $i++;
                }
                continue;
            }
            if ($inString) {
                $out .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
                $out .= $char;
                continue;
            }
            if ($char === '/' && $next === '/') {
                $inLine = true;
                $i++;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlock = true;
                $i++;
                continue;
            }
            $out .= $char;
        }

        return (string) preg_replace('/,(\s*[}\]])/', '$1', $out);
    }

    private function usesStrictDeny(string $haystack): bool
    {
        return (bool) preg_match('/[\'\"]\*[\'\"]\s*:\s*[\'\"]?deny[\'\"]?/', $haystack);
    }

    private function isPatternAskOrAllow(string $haystack, string $needle): bool
    {
        $escaped = preg_quote($needle, '/');
        return (bool) preg_match('/[\'\"][^\'\"]*' . $escaped . '(?:[\s\*\'\"])[^\'\"]*[\'\"]\s*:\s*[\'\"]?(?:ask|allow)[\'\"]?/m', $haystack);
    }

    private function isPatternAllow(string $haystack, string $needle): bool
    {
        $escaped = preg_quote($needle, '/');
        return (bool) preg_match('/[\'\"][^\'\"]*' . $escaped . '(?:[\s\*\'\"])[^\'\"]*[\'\"]\s*:\s*[\'\"]?allow[\'\"]?/m', $haystack);
    }

    private function isExactPatternAllow(string $haystack, string $pattern): bool
    {
        $escaped = preg_quote($pattern, '/');
        return (bool) preg_match('/[\'\"]' . $escaped . '[\'\"]\s*:\s*[\'\"]?allow[\'\"]?/m', $haystack);
    }
}
