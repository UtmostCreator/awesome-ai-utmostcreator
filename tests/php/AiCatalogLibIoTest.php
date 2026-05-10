<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class AiCatalogLibIoTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ai_catalog_lib_io_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    // ---- aiReadFile ----

    public function testReadFileReturnsContent(): void
    {
        file_put_contents($this->tmpDir . '/test.txt', 'hello world');
        $result = aiReadFile($this->tmpDir, 'test.txt');
        $this->assertSame('hello world', $result);
    }

    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testReadFileThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        aiReadFile($this->tmpDir, 'nonexistent.txt');
    }

    public function testReadFilePreservesContent(): void
    {
        $content = "line1\nline2\nline3";
        file_put_contents($this->tmpDir . '/multi.txt', $content);
        $this->assertSame($content, aiReadFile($this->tmpDir, 'multi.txt'));
    }

    // ---- aiLoadJson ----

    public function testLoadJsonParsesValidJson(): void
    {
        file_put_contents($this->tmpDir . '/data.json', '{"key":"value","num":42}');
        $result = aiLoadJson($this->tmpDir, 'data.json');
        $this->assertSame('value', $result['key']);
        $this->assertSame(42, $result['num']);
    }

    public function testLoadJsonParsesNestedJson(): void
    {
        file_put_contents($this->tmpDir . '/nested.json', '{"outer":{"inner":"x"}}');
        $result = aiLoadJson($this->tmpDir, 'nested.json');
        $this->assertSame('x', $result['outer']['inner']);
    }

    public function testLoadJsonThrowsOnMalformedJson(): void
    {
        file_put_contents($this->tmpDir . '/bad.json', '{not valid json}');
        $this->expectException(RuntimeException::class);
        aiLoadJson($this->tmpDir, 'bad.json');
    }

    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testLoadJsonThrowsOnMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        aiLoadJson($this->tmpDir, 'missing.json');
    }

    public function testLoadJsonThrowsOnJsonString(): void
    {
        // json_decode of a JSON string (not object) returns string, not array
        file_put_contents($this->tmpDir . '/str.json', '"just a string"');
        $this->expectException(RuntimeException::class);
        aiLoadJson($this->tmpDir, 'str.json');
    }

    // ---- aiListFilesInDirectory ----

    public function testListFilesInDirectoryReturnsSortedPaths(): void
    {
        file_put_contents($this->tmpDir . '/b.txt', '');
        file_put_contents($this->tmpDir . '/a.txt', '');
        $files = aiListFilesInDirectory($this->tmpDir);
        $this->assertCount(2, $files);
        $this->assertStringEndsWith('/a.txt', $files[0]);
        $this->assertStringEndsWith('/b.txt', $files[1]);
    }

    public function testListFilesInDirectoryReturnsEmptyForEmptyDir(): void
    {
        $emptyDir = $this->tmpDir . '/empty';
        mkdir($emptyDir);
        $this->assertSame([], aiListFilesInDirectory($emptyDir));
    }

    public function testListFilesInDirectoryRecursesIntoSubdirectories(): void
    {
        mkdir($this->tmpDir . '/sub');
        file_put_contents($this->tmpDir . '/sub/nested.txt', '');
        $files = aiListFilesInDirectory($this->tmpDir);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('/sub/nested.txt', $files[0]);
    }

    public function testListFilesInDirectoryUsesForwardSlashes(): void
    {
        file_put_contents($this->tmpDir . '/file.txt', '');
        $files = aiListFilesInDirectory($this->tmpDir);
        $this->assertStringNotContainsString('\\', $files[0]);
    }

    // ---- aiWriteIfChanged ----

    public function testWriteIfChangedCreatesNewFile(): void
    {
        $path = $this->tmpDir . '/new.txt';
        $wrote = aiWriteIfChanged($path, 'content');
        $this->assertTrue($wrote);
        $this->assertSame('content', file_get_contents($path));
    }

    public function testWriteIfChangedReturnsFalseWhenContentUnchanged(): void
    {
        $path = $this->tmpDir . '/existing.txt';
        file_put_contents($path, 'same content');
        $wrote = aiWriteIfChanged($path, 'same content');
        $this->assertFalse($wrote);
    }

    public function testWriteIfChangedOverwritesWhenContentDiffers(): void
    {
        $path = $this->tmpDir . '/existing.txt';
        file_put_contents($path, 'old content');
        $wrote = aiWriteIfChanged($path, 'new content');
        $this->assertTrue($wrote);
        $this->assertSame('new content', file_get_contents($path));
    }

    public function testWriteIfChangedCreatesSubdirectories(): void
    {
        $path = $this->tmpDir . '/sub/dir/file.txt';
        aiWriteIfChanged($path, 'content');
        $this->assertFileExists($path);
    }

    public function testWriteIfChangedDoesNotModifyFileWhenUnchanged(): void
    {
        $path = $this->tmpDir . '/stable.txt';
        file_put_contents($path, 'content');
        $mtime = filemtime($path);
        sleep(1);
        aiWriteIfChanged($path, 'content');
        $this->assertSame($mtime, filemtime($path));
    }

    // ---- aiCompareOrWrite ----

    public function testCompareOrWriteReturnsOkWhenUpToDate(): void
    {
        file_put_contents($this->tmpDir . '/doc.md', 'current content');
        $messages = [];
        $result = aiCompareOrWrite($this->tmpDir, 'doc.md', 'current content', false, $messages);
        $this->assertTrue($result);
        $this->assertStringContainsString('up to date', $messages[0]);
    }

    public function testCompareOrWriteInCheckModeReportsErrorWhenStale(): void
    {
        file_put_contents($this->tmpDir . '/doc.md', 'old content');
        $messages = [];
        $result = aiCompareOrWrite($this->tmpDir, 'doc.md', 'new content', true, $messages);
        $this->assertFalse($result);
        $this->assertStringContainsString('ERROR', $messages[0]);
    }

    public function testCompareOrWriteWritesWhenNotCheckMode(): void
    {
        file_put_contents($this->tmpDir . '/doc.md', 'old content');
        $messages = [];
        aiCompareOrWrite($this->tmpDir, 'doc.md', 'new content', false, $messages);
        $this->assertSame('new content', file_get_contents($this->tmpDir . '/doc.md'));
    }

    public function testCompareOrWriteNormalizesCrlfBeforeCompare(): void
    {
        // File on disk uses CRLF; content uses LF — should be considered equal
        file_put_contents($this->tmpDir . '/doc.md', "line1\r\nline2");
        $messages = [];
        $result = aiCompareOrWrite($this->tmpDir, 'doc.md', "line1\nline2", false, $messages);
        $this->assertTrue($result);
        $this->assertStringContainsString('up to date', $messages[0]);
    }
}
