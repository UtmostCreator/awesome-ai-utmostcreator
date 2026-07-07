<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/tools/ai/install/agent-assessment-template-writer.php';

/**
 * Covers the D3b template-authoring writer
 * (tools/ai/install/agent-assessment-template-writer.php) that injects the
 * approved `agent_assessment` block (risk_level + decision only) into a canonical
 * agent template's frontmatter, driven by docs/ai/agent-scores.yaml.
 */
class AgentAssessmentFrontmatterWriterTest extends TestCase
{
    public function testRenderBlockProducesNormalizedTwoLineForm(): void
    {
        $block = \aiAssessmentRenderBlock(['risk_level' => 'high', 'decision' => 'approve']);
        $this->assertSame("agent_assessment:\n  risk_level: high\n  decision: approve\n", $block);
    }

    public function testInjectInsertsBlockBeforeClosingFenceWhenAbsent(): void
    {
        $content = "---\nid: sample\ndescription: x\n---\n\n# Body\n";
        $result = \aiAssessmentInjectIntoTemplate($content, ['risk_level' => 'medium', 'decision' => 'needs_refactor']);

        $expected = "---\nid: sample\ndescription: x\nagent_assessment:\n  risk_level: medium\n  decision: needs_refactor\n---\n\n# Body\n";
        $this->assertSame($expected, $result);
    }

    public function testInjectReplacesExistingBlockInPlacePreservingRestOfFrontmatter(): void
    {
        $content = "---\nid: sample\nagent_assessment:\n  risk_level: low\n  decision: approve\ndescription: x\n---\n\n# Body\n";
        $result = \aiAssessmentInjectIntoTemplate($content, ['risk_level' => 'critical', 'decision' => 'block']);

        $expected = "---\nid: sample\nagent_assessment:\n  risk_level: critical\n  decision: block\ndescription: x\n---\n\n# Body\n";
        $this->assertSame($expected, $result);
    }

    public function testInjectIsIdempotent(): void
    {
        $content = "---\nid: sample\ndescription: x\n---\n\n# Body\n";
        $assessment = ['risk_level' => 'high', 'decision' => 'approve'];
        $once = \aiAssessmentInjectIntoTemplate($content, $assessment);
        $twice = \aiAssessmentInjectIntoTemplate($once, $assessment);

        $this->assertSame($once, $twice);
    }

    public function testInjectMatchesLiveArchitectTemplateBlockFormat(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $path = $root . '/packages/ai-universal-rules/templates/core/agents/architect.md';
        $content = (string) file_get_contents($path);

        // architect.md already carries the D1 pilot block; re-injecting the same
        // values must be a byte-identical no-op (idempotency against real content).
        $result = \aiAssessmentInjectIntoTemplate($content, ['risk_level' => 'high', 'decision' => 'approve']);
        $this->assertSame($content, $result);
    }

    public function testInjectThrowsWithoutFrontmatterFence(): void
    {
        $this->expectException(RuntimeException::class);
        \aiAssessmentInjectIntoTemplate("# No frontmatter here\n", ['risk_level' => 'low', 'decision' => 'approve']);
    }

    public function testLoadApprovedSourceRefusesWhenDraft(): void
    {
        $tmp = sys_get_temp_dir() . '/aav-writer-test-' . uniqid('', true);
        mkdir($tmp . '/docs/ai', 0777, true);
        file_put_contents($tmp . '/docs/ai/agent-scores.yaml', "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n  x:\n    agent_assessment:\n      risk_level: low\n      decision: approve\n    rationale: \"r\"\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not approved/');
        \aiAssessmentLoadApprovedSource($tmp);
    }

    public function testLoadApprovedSourceReturnsCategoricalFieldsOnlyFromLiveSource(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);
        $map = \aiAssessmentLoadApprovedSource($root);

        $this->assertArrayHasKey('architect', $map);
        $this->assertSame(['risk_level' => 'high', 'decision' => 'approve'], $map['architect']);
        $this->assertCount(2, $map['architect']);
        $this->assertCount(26, $map, 'expected exactly 26 live agent template entries');
    }

    public function testTemplatePathResolvesCoreThenOptional(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        $this->assertNotFalse($root);

        $core = \aiAssessmentTemplatePathForKey($root, 'architect');
        $this->assertNotNull($core);
        $this->assertStringContainsString('/templates/core/agents/architect.md', $core);

        $optional = \aiAssessmentTemplatePathForKey($root, 'ui-builder');
        $this->assertNotNull($optional);
        $this->assertStringContainsString('/templates/optional/agents/ui-builder.md', $optional);

        $missing = \aiAssessmentTemplatePathForKey($root, 'does-not-exist');
        $this->assertNull($missing);
    }
}
