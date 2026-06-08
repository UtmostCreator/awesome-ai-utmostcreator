<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase C / P5: read-only `status` command contract.
 *
 * aiRunStatus() is exercised in-process against isolated temp roots so the live
 * working tree is never mutated. Each section is asserted to be HONESTLY derived
 * from real filesystem/manifest evidence — the invariant is "no false enforcement
 * claims", so the policy section must never report `enforced` without wiring.
 *
 * The drift section reuses aiInstallerCollectChecksumDrift (shared with doctor);
 * these tests prove the status command surfaces that same evidence, not a re-impl.
 */
class StatusCommandTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;

        require_once self::$repoRoot . '/tools/ai/ai_output_lib.php';
        require_once self::$repoRoot . '/tools/ai/install/core.php';
        require_once self::$repoRoot . '/tools/ai/commands/install_preflight.php';
    }

    /**
     * Run aiRunStatus() against a temp root, capturing stdout and the JSON artifact `data`.
     *
     * @return array{exit:int, stdout:string, data:array<string,mixed>, raw:array<string,mixed>}
     */
    private function runStatus(string $root): array
    {
        // Note: aiRunStatus writes its human summary via fwrite(STDOUT, ...), which targets the
        // STDOUT file descriptor directly and bypasses ob_start(). The JSON artifact is the
        // authoritative, assertable evidence (same as doctor); stdout is not asserted here.
        $exit = aiRunStatus($root);
        $stdout = '';

        $artifact = $root . '/docs/ai/generated/status.json';
        $this->assertFileExists($artifact, 'status must write a JSON artifact like doctor');
        $decoded = json_decode((string) file_get_contents($artifact), true);
        $this->assertIsArray($decoded);
        $data = $decoded['data'] ?? [];
        $this->assertIsArray($data);

        return ['exit' => $exit, 'stdout' => $stdout, 'data' => $data, 'raw' => $decoded];
    }

    private function makeTempRoot(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_status_' . uniqid('', true);
        mkdir($root, 0777, true);
        mkdir($root . '/docs/ai/generated', 0777, true);
        return $root;
    }

    /**
     * @param array<string,mixed> $files
     */
    private function writeManifest(string $root, array $files, ?string $profile = 'full-governance'): void
    {
        $manifest = ['files' => $files];
        if ($profile !== null) {
            $manifest['profile'] = $profile;
        }
        file_put_contents(
            $root . '/.ai-install-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function removeTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }

    // ---- install state ----

    public function testStatusOnNotInstalledDirReportsInstalledFalseExitZero(): void
    {
        $root = $this->makeTempRoot();
        try {
            $result = $this->runStatus($root);
            $this->assertSame(0, $result['exit'], 'read-only status on a non-installed dir is exit 0');
            $this->assertFalse($result['data']['install_state']['installed'], 'no manifest => installed=false');
            $this->assertSame(0, $result['data']['install_state']['managed_file_count']);
            $this->assertContains('kit not installed in this repo (no .ai-install-manifest.json)', $result['data']['warnings']);
            $this->assertSame('warning', $result['raw']['status'], 'not-installed surfaces a warning, not a hard failure');
        } finally {
            $this->removeTree($root);
        }
    }

    public function testStatusReportsProfileAndManagedCountFromManifest(): void
    {
        $root = $this->makeTempRoot();
        try {
            // docs/ai already exists (created by makeTempRoot as parent of docs/ai/generated).
            file_put_contents($root . '/docs/ai/clean.md', "clean content\n");
            $this->writeManifest($root, [
                'docs/ai/clean.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:' . hash('sha256', "clean content\n")],
            ], 'minimal');

            $result = $this->runStatus($root);
            $this->assertTrue($result['data']['install_state']['installed']);
            $this->assertSame('minimal', $result['data']['install_state']['profile']);
            $this->assertSame(1, $result['data']['install_state']['managed_file_count']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- kit-owned files: clean vs drifted (reuses aiInstallerCollectChecksumDrift) ----

    public function testStatusReportsCleanManagedFileWithEmptyDrift(): void
    {
        $root = $this->makeTempRoot();
        try {
            file_put_contents($root . '/docs/ai/clean.md', "clean content\n");
            $this->writeManifest($root, [
                'docs/ai/clean.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:' . hash('sha256', "clean content\n")],
            ]);

            $result = $this->runStatus($root);
            $kit = $result['data']['kit_owned_files'];
            $this->assertSame([], $kit['modified'], 'clean managed file must not be flagged modified');
            $this->assertSame([], $kit['missing']);
            $this->assertFalse($kit['checksum_drift']);
            $this->assertSame(1, $kit['clean']);
            $this->assertSame(0, $result['exit'], 'clean install is exit 0');
        } finally {
            $this->removeTree($root);
        }
    }

    public function testStatusReportsModifiedManagedFileAsDrifted(): void
    {
        $root = $this->makeTempRoot();
        try {
            file_put_contents($root . '/docs/ai/owned.md', "original content\n");
            $this->writeManifest($root, [
                'docs/ai/owned.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:' . hash('sha256', "original content\n")],
            ]);
            // User edits the owned file after install.
            file_put_contents($root . '/docs/ai/owned.md', "user edited this\n");

            $result = $this->runStatus($root);
            $kit = $result['data']['kit_owned_files'];
            $this->assertSame(['docs/ai/owned.md'], $kit['modified'], 'modified owned file must appear in drifted/modified');
            $this->assertTrue($kit['checksum_drift']);
            $this->assertSame(0, $result['exit'], 'drift is a warning, still exit 0');
            $this->assertSame('warning', $result['raw']['status']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- conflicts ----

    public function testStatusReportsConflictSubtree(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            mkdir($root . '/.ai/conflicts/20250101T000000Z-upgrade/files', 0777, true);
            file_put_contents($root . '/.ai/conflicts/20250101T000000Z-upgrade/files/x.md', "x\n");

            $result = $this->runStatus($root);
            $conflicts = $result['data']['conflicts'];
            $this->assertSame(1, $conflicts['count']);
            $this->assertSame(['20250101T000000Z-upgrade'], $conflicts['subtrees'], 'op-tagged conflict dir name is reported');
            $this->assertSame(0, $result['exit']);
        } finally {
            $this->removeTree($root);
        }
    }

    public function testStatusReportsNoConflictsWhenAbsent(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            $result = $this->runStatus($root);
            $this->assertSame(0, $result['data']['conflicts']['count']);
            $this->assertSame([], $result['data']['conflicts']['subtrees']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- template updates / rendered-stale ----

    public function testStatusReportsTemplateUpdateAvailable(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            mkdir($root . '/.ai/templates-new', 0777, true);
            file_put_contents($root . '/.ai/templates-new/foo.md', "new template\n");

            $result = $this->runStatus($root);
            $this->assertTrue($result['data']['template_updates']['available']);
            $this->assertContains('.ai/templates-new/foo.md', $result['data']['template_updates']['pending_paths']);
            // Section 3 (rendered/stale) mirrors the same templates-new signal.
            $this->assertTrue($result['data']['rendered_stale']['template_updates_available']);
            $this->assertContains('.ai/templates-new/foo.md', $result['data']['rendered_stale']['pending_paths']);
            $this->assertSame(0, $result['exit']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- preserved user files (ownership == template) ----

    public function testStatusListsPreservedTemplateOwnedFilesThatExistOnDisk(): void
    {
        $root = $this->makeTempRoot();
        try {
            file_put_contents($root . '/AGENTS.md', "# user owned\n");
            $this->writeManifest($root, [
                // ownership=template exists on disk => preserved
                'AGENTS.md' => ['ownership' => 'template', 'installed_hash' => 'sha256:test'],
                // ownership=template but missing on disk => NOT preserved (no false claim)
                'CLAUDE.md' => ['ownership' => 'template', 'installed_hash' => 'sha256:test'],
                // owned file is not a preserved user file
                'docs/ai/x.md' => ['ownership' => 'owned', 'installed_hash' => 'sha256:test'],
            ]);

            $result = $this->runStatus($root);
            $preserved = $result['data']['preserved_user_files']['template_owned'];
            $this->assertContains('AGENTS.md', $preserved, 'on-disk template-owned file must be listed as preserved');
            $this->assertNotContains('CLAUDE.md', $preserved, 'missing template file must not be claimed as preserved');
            $this->assertNotContains('docs/ai/x.md', $preserved, 'owned files are not preserved user files');
        } finally {
            $this->removeTree($root);
        }
    }

    public function testStatusReportsProjectFilesPresence(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            mkdir($root . '/docs/ai/project', 0777, true);
            file_put_contents($root . '/docs/ai/project/README.md', "# project\n");

            $result = $this->runStatus($root);
            $this->assertTrue($result['data']['preserved_user_files']['project_files_present']);
            $this->assertContains('docs/ai/project/README.md', $result['data']['preserved_user_files']['project_files']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- policy enforcement: HONEST classification ----

    public function testPolicyNotClaimedEnforcedWithoutRuntimeReference(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            // Guard scripts + compiled policy present, but NO runtime reference wires them.
            mkdir($root . '/.github/hooks/scripts', 0777, true);
            file_put_contents($root . '/.github/hooks/scripts/tool-guardian.sh', "#!/usr/bin/env bash\n");
            file_put_contents($root . '/.github/hooks/scripts/command-policy.compiled.sh', "#!/usr/bin/env bash\n");
            file_put_contents($root . '/.github/hooks/tool-policy.json', "{}\n");

            $result = $this->runStatus($root);
            $policy = $result['data']['policy_enforcement'];

            // Honest: without a runtime reference, OpenCode must NOT be 'enforced'.
            $this->assertNotSame('enforced', $policy['opencode']['classification'], 'no false enforcement claim');
            $this->assertSame('advisory', $policy['opencode']['classification'], 'policy files present but unwired => advisory');
            $this->assertFalse($policy['opencode']['runtime_reference_present']);

            // Factual runtime-hook presence checks.
            $this->assertTrue($policy['runtime_hooks']['tool_guardian_sh']);
            $this->assertTrue($policy['runtime_hooks']['command_policy_compiled_sh']);
            $this->assertFalse($policy['runtime_hooks']['tool_guardian_ps1']);
        } finally {
            $this->removeTree($root);
        }
    }

    public function testPolicyEnforcedOnlyWithWiringAndRuntimeReference(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            mkdir($root . '/.github/hooks/scripts', 0777, true);
            file_put_contents($root . '/.github/hooks/scripts/command-policy.compiled.sh', "#!/usr/bin/env bash\n");
            file_put_contents($root . '/.github/hooks/tool-policy.json', "{}\n");
            // A loaded config that actually references the hook wiring.
            file_put_contents($root . '/opencode.jsonc', "{ \"hooks\": \".github/hooks/tool-policy.json\" }\n");

            $result = $this->runStatus($root);
            $policy = $result['data']['policy_enforcement'];
            $this->assertSame('enforced', $policy['opencode']['classification'], 'wiring + compiled policy + runtime reference => enforced');
            $this->assertTrue($policy['opencode']['runtime_reference_present']);
        } finally {
            $this->removeTree($root);
        }
    }

    public function testPolicyAbsentWhenNothingPresent(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            $result = $this->runStatus($root);
            $policy = $result['data']['policy_enforcement'];
            $this->assertSame('absent', $policy['opencode']['classification']);
            $this->assertSame('absent', $policy['copilot']['classification']);
        } finally {
            $this->removeTree($root);
        }
    }

    public function testCopilotClassifiedAdvisoryWhenInstructionsPresent(): void
    {
        $root = $this->makeTempRoot();
        try {
            $this->writeManifest($root, []);
            mkdir($root . '/.github', 0777, true);
            file_put_contents($root . '/.github/copilot-instructions.md', "# instructions\n");

            $result = $this->runStatus($root);
            $policy = $result['data']['policy_enforcement'];
            // Copilot has no enforced hook runtime; advisory is the honest ceiling.
            $this->assertSame('advisory', $policy['copilot']['classification']);
            $this->assertNotSame('enforced', $policy['copilot']['classification']);
        } finally {
            $this->removeTree($root);
        }
    }

    // ---- hard error: manifest present but unreadable ----

    public function testStatusReturnsNonZeroWhenManifestUnreadable(): void
    {
        $root = $this->makeTempRoot();
        try {
            file_put_contents($root . '/.ai-install-manifest.json', "{ not valid json ");
            $result = $this->runStatus($root);
            $this->assertSame(1, $result['exit'], 'a hard manifest error is the only non-zero status exit');
            $this->assertSame('failed', $result['raw']['status']);
        } finally {
            $this->removeTree($root);
        }
    }
}
