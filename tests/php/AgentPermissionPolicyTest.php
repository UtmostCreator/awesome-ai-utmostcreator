<?php

declare(strict_types=1);

namespace Tests;

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
        '.opencode/agents/workflow-auditor.md',
        'packages/ai-universal-rules/templates/core/agents/architect.md',
        'packages/ai-universal-rules/templates/core/agents/release-auditor.md',
        'packages/ai-universal-rules/templates/core/agents/researcher.md',
        'packages/ai-universal-rules/templates/core/agents/reviewer.md',
        'packages/ai-universal-rules/templates/core/agents/workflow-auditor.md',
    ];

    /** @var list<string> */
    private const GIT_MUTATING_AGENT_FILES = [
        '.opencode/agents/bootstrapper.md',
        '.opencode/agents/config-maintainer.md',
        '.opencode/agents/implementer.md',
        '.opencode/agents/refactorer.md',
        'packages/ai-universal-rules/templates/core/agents/bootstrapper.md',
        'packages/ai-universal-rules/templates/core/agents/config-maintainer.md',
        'packages/ai-universal-rules/templates/core/agents/implementer.md',
        'packages/ai-universal-rules/templates/core/agents/refactorer.md',
    ];

    /** @var list<string> */
    private const IMPLEMENTER_AGENT_FILES = [
        '.opencode/agents/implementer.md',
        'packages/ai-universal-rules/templates/core/agents/implementer.md',
    ];

    /** @var list<string> */
    private const ASK_DEFAULT_AGENTS = [
        '.opencode/agents/repository-researcher.md',
        '.opencode/agents/repository-reviewer.md',
        'packages/ai-universal-rules/templates/core/agents/repository-researcher.md',
        'packages/ai-universal-rules/templates/core/agents/repository-reviewer.md',
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

    /**
     * @dataProvider gitMutatingAgentProvider
     */
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

    /**
     * @dataProvider implementerAgentProvider
     */
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

    /**
     * @dataProvider readOnlyStrictDenyAgentProvider
     */
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

    /**
     * @dataProvider allAgentProvider
     */
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

    /**
     * @dataProvider allAgentProvider
     */
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

    /**
     * @dataProvider allAgentProvider
     */
    public function testAgentFrontmatterIsParseable(string $relativePath): void
    {
        $full = self::$repoRoot . '/' . $relativePath;
        self::assertFileExists($full);

        $raw = file_get_contents($full);
        self::assertNotFalse($raw, "Could not read $relativePath");
        self::assertStringStartsWith('---', $raw, sprintf('%s must begin with YAML frontmatter.', $relativePath));
        self::assertNotFalse(strpos($raw, "\n---", 4), sprintf('%s frontmatter has no terminating delimiter.', $relativePath));
    }

    /**
     * @dataProvider projectConfigProvider
     */
    public function testProjectConfigAsksBeforeInstallApply(string $relativePath): void
    {
        $bash = $this->loadProjectBashPermissions($relativePath);

        foreach (['php tools/ai/ai.php install * --apply', 'php tools/ai/install-ai-kit.php *'] as $pattern) {
            self::assertSame('ask', $bash[$pattern] ?? null, sprintf('%s must ask for %s', $relativePath, $pattern));
        }
    }

    /**
     * @dataProvider projectConfigProvider
     */
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

    private function loadFrontmatter(string $relativePath): string
    {
        $raw = file_get_contents(self::$repoRoot . '/' . $relativePath);
        if ($raw === false) {
            throw new \RuntimeException("Could not read $relativePath");
        }

        if (!str_starts_with($raw, '---')) {
            return $raw;
        }

        $end = strpos($raw, "\n---", 4);
        return $end === false ? $raw : substr($raw, 0, $end);
    }

    /** @return array<string,string> */
    private function loadProjectBashPermissions(string $relativePath): array
    {
        $raw = file_get_contents(self::$repoRoot . '/' . $relativePath);
        self::assertNotFalse($raw);

        $data = json_decode($raw, true);
        self::assertIsArray($data, sprintf('%s must be valid JSON', $relativePath));
        self::assertIsArray($data['permission']['bash'] ?? null, sprintf('%s must declare permission.bash', $relativePath));

        /** @var array<string,string> $bash */
        $bash = $data['permission']['bash'];
        return $bash;
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
}
