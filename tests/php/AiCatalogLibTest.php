<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class AiCatalogLibTest extends TestCase
{
    // ---- aiNormalizePath ----

    public function testNormalizePathConvertsBackslashes(): void
    {
        $this->assertSame('foo/bar/baz', aiNormalizePath('foo\\bar\\baz'));
    }

    public function testNormalizePathLeavesForwardSlashesUnchanged(): void
    {
        $this->assertSame('foo/bar/baz', aiNormalizePath('foo/bar/baz'));
    }

    public function testNormalizePathEmptyString(): void
    {
        $this->assertSame('', aiNormalizePath(''));
    }

    // ---- aiAbsolutePath ----

    public function testAbsolutePathCombinesRootAndRelative(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        $this->assertSame('/tmp' . $sep . 'foo' . $sep . 'bar', aiAbsolutePath('/tmp', 'foo/bar'));
    }

    public function testAbsolutePathNormalizesRelativeSeparators(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        $this->assertSame('/tmp' . $sep . 'a' . $sep . 'b', aiAbsolutePath('/tmp', 'a/b'));
    }

    // ---- aiNormalizeGeneratedContent ----

    public function testNormalizeGeneratedContentConvertsCarriageReturns(): void
    {
        $this->assertSame("line1\nline2\n", aiNormalizeGeneratedContent("line1\r\nline2\r\n"));
    }

    public function testNormalizeGeneratedContentLeavesLfUnchanged(): void
    {
        $this->assertSame("line1\nline2\n", aiNormalizeGeneratedContent("line1\nline2\n"));
    }

    public function testNormalizeGeneratedContentEmptyString(): void
    {
        $this->assertSame('', aiNormalizeGeneratedContent(''));
    }

    // ---- aiParseFrontMatter ----

    public function testParseFrontMatterExtractsKeyValues(): void
    {
        $content = "---\ntitle: Hello World\nauthor: Alice\n---\nContent here";
        $result = aiParseFrontMatter($content);
        $this->assertSame('Hello World', $result['title']);
        $this->assertSame('Alice', $result['author']);
    }

    public function testParseFrontMatterReturnsEmptyWhenNoMarker(): void
    {
        $this->assertSame([], aiParseFrontMatter('No front matter here'));
    }

    public function testParseFrontMatterReturnsEmptyWhenNoClosingMarker(): void
    {
        $this->assertSame([], aiParseFrontMatter("---\ntitle: Unclosed\n"));
    }

    public function testParseFrontMatterStripsDoubleQuotes(): void
    {
        $content = "---\ntitle: \"Quoted Title\"\n---\n";
        $result = aiParseFrontMatter($content);
        $this->assertSame('Quoted Title', $result['title']);
    }

    public function testParseFrontMatterSkipsLinesWithoutColon(): void
    {
        $content = "---\ntitle: Valid\nno-colon-line\n---\n";
        $result = aiParseFrontMatter($content);
        $this->assertSame('Valid', $result['title']);
        $this->assertArrayNotHasKey('no-colon-line', $result);
    }

    // ---- aiExtractTitle ----

    public function testExtractTitleFindsH1(): void
    {
        $this->assertSame('My Title', aiExtractTitle("# My Title\n\nParagraph.", 'fallback'));
    }

    public function testExtractTitleReturnsFallbackWhenNoH1(): void
    {
        $this->assertSame('fallback', aiExtractTitle("No heading here", 'fallback'));
    }

    public function testExtractTitleFindsH1InMiddleOfContent(): void
    {
        $this->assertSame('Mid Title', aiExtractTitle("Some text\n# Mid Title\nMore text", 'fallback'));
    }

    public function testExtractTitleTrimsWhitespace(): void
    {
        $this->assertSame('Trimmed', aiExtractTitle("#  Trimmed  \n", 'fallback'));
    }

    // ---- aiSummarizeMarkdown ----

    public function testSummarizeMarkdownReturnsFirstParagraph(): void
    {
        $this->assertSame(
            'First paragraph.',
            aiSummarizeMarkdown("# Heading\n\nFirst paragraph.\n\nSecond paragraph.")
        );
    }

    public function testSummarizeMarkdownSkipsHrule(): void
    {
        $this->assertSame('Real content.', aiSummarizeMarkdown("---\nReal content."));
    }

    public function testSummarizeMarkdownSkipsHeadings(): void
    {
        $this->assertSame('Body text.', aiSummarizeMarkdown("# Heading\n## Sub\nBody text."));
    }

    public function testSummarizeMarkdownReturnsNullWhenOnlyHeadings(): void
    {
        $this->assertNull(aiSummarizeMarkdown("# Only heading\n\n---\n"));
    }

    // ---- aiResource ----

    public function testResourceBuildsCorrectArray(): void
    {
        $result = aiResource('root', 'doc', 'My Doc', 'docs/my-doc.md', 'A description', 'github-copilot');
        $this->assertSame('root', $result['scope']);
        $this->assertSame('doc', $result['type']);
        $this->assertSame('My Doc', $result['name']);
        $this->assertSame('docs/my-doc.md', $result['path']);
        $this->assertSame('A description', $result['description']);
        $this->assertSame('github-copilot', $result['runtime']);
    }

    public function testResourceNormalizesBackslashesInPath(): void
    {
        $result = aiResource('root', 'doc', 'Doc', 'docs\\sub\\file.md');
        $this->assertSame('docs/sub/file.md', $result['path']);
    }

    public function testResourceDefaultsOptionalParamsToNull(): void
    {
        $result = aiResource('root', 'doc', 'Doc', 'path.md');
        $this->assertNull($result['description']);
        $this->assertNull($result['runtime']);
    }

    public function testResourceMergesExtraFields(): void
    {
        $result = aiResource('root', 'doc', 'Doc', 'path.md', null, null, ['custom' => 'value', 'num' => 42]);
        $this->assertSame('value', $result['custom']);
        $this->assertSame(42, $result['num']);
    }

    // ---- aiEscapeTable ----

    public function testEscapeTableReplacesBar(): void
    {
        $this->assertSame('foo\\|bar', aiEscapeTable('foo|bar'));
    }

    public function testEscapeTableLeavesNonBarUnchanged(): void
    {
        $this->assertSame('hello world', aiEscapeTable('hello world'));
    }

    public function testEscapeTableMultipleBars(): void
    {
        $this->assertSame('a\\|b\\|c', aiEscapeTable('a|b|c'));
    }

    // ---- aiRenderTableRows ----

    public function testRenderTableRowsIncludesHeaderAndSeparator(): void
    {
        $lines = aiRenderTableRows([], 'root');
        $this->assertSame('| Type | Name | Path | Description |', $lines[0]);
        $this->assertSame('| --- | --- | --- | --- |', $lines[1]);
    }

    public function testRenderTableRowsFiltersOutWrongScope(): void
    {
        $resources = [
            aiResource('root', 'doc', 'Doc A', 'docs/a.md', 'Desc A'),
            aiResource('package', 'doc', 'Doc B', 'docs/b.md', 'Desc B'),
        ];
        $lines = aiRenderTableRows($resources, 'root');
        $this->assertCount(3, $lines); // header + separator + 1 data row
        $this->assertStringContainsString('Doc A', $lines[2]);
        $this->assertStringNotContainsString('Doc B', implode("\n", $lines));
    }

    public function testRenderTableRowsIncludesPathAndDescription(): void
    {
        $resources = [aiResource('root', 'capability', 'My Cap', 'docs/cap.md', 'Cap description')];
        $lines = aiRenderTableRows($resources, 'root');
        $this->assertStringContainsString('docs/cap.md', $lines[2]);
        $this->assertStringContainsString('Cap description', $lines[2]);
    }

}
