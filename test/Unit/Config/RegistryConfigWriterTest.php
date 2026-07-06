<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\RegistryConfigWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for RegistryConfigWriter.
 *
 * The writer serializes a compiled registry to a PHP `return`-style
 * file, atomically. Tests cover:
 * - Round-trip via `require` (write, require, compare).
 * - Parent-directory creation on first write.
 * - Atomicity: no stale temp files after a successful write.
 * - Overwrite semantics: existing destination is replaced, not merged.
 * - Error handling for unwritable directories.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(RegistryConfigWriter::class)]
class RegistryConfigWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/horde-registry-writer-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        // Restore write bits in case a test removed them.
        @chmod($dir, 0o755);
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // ===================================================================
    // Round-trip
    // ===================================================================

    public function testWriteProducesRequireableFile(): void
    {
        $compiled = [
            'default' => [
                'horde' => ['name' => 'Horde', 'status' => 'active'],
                'imp' => ['name' => 'IMP', 'params' => ['timeout' => 30]],
            ],
            'example.com' => [
                'imp' => ['webroot' => '/mail'],
            ],
        ];

        $path = $this->tempDir . '/registry.php';
        (new RegistryConfigWriter())->write($compiled, $path);

        $this->assertFileExists($path);

        $loaded = require $path;
        $this->assertSame($compiled, $loaded);
    }

    public function testWriteHandlesEmptyDefaultOnly(): void
    {
        $compiled = ['default' => []];
        $path = $this->tempDir . '/registry.php';

        (new RegistryConfigWriter())->write($compiled, $path);

        $this->assertSame($compiled, require $path);
    }

    public function testWriteHandlesNestedStructures(): void
    {
        // Registry data can be arbitrarily deep. Verify var_export
        // survives realistic depth without losing keys.
        $compiled = [
            'default' => [
                'imp' => [
                    'name' => 'IMP',
                    'params' => [
                        'nested' => [
                            'deeper' => [
                                'value' => 42,
                                'list' => [1, 2, 3],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $path = $this->tempDir . '/registry.php';

        (new RegistryConfigWriter())->write($compiled, $path);

        $this->assertSame($compiled, require $path);
    }

    // ===================================================================
    // Directory handling
    // ===================================================================

    public function testWriteCreatesMissingParentDirectory(): void
    {
        $path = $this->tempDir . '/cache/compiled/registry.php';
        $this->assertDirectoryDoesNotExist(dirname($path));

        (new RegistryConfigWriter())->write(['default' => []], $path);

        $this->assertDirectoryExists(dirname($path));
        $this->assertFileExists($path);
    }

    public function testWriteCreatesDeeplyNestedParentDirectories(): void
    {
        $path = $this->tempDir . '/a/b/c/d/registry.php';
        (new RegistryConfigWriter())->write(['default' => []], $path);

        $this->assertFileExists($path);
    }

    public function testWriteWithExistingDirectoryIsNoop(): void
    {
        $dir = $this->tempDir . '/cache/compiled';
        mkdir($dir, 0o755, true);
        // Sentinel file that must survive the write.
        file_put_contents($dir . '/sibling.txt', 'do-not-touch');

        (new RegistryConfigWriter())
            ->write(['default' => []], $dir . '/registry.php');

        $this->assertSame('do-not-touch', file_get_contents($dir . '/sibling.txt'));
    }

    // ===================================================================
    // Atomicity
    // ===================================================================

    public function testWriteLeavesNoStaleTempFiles(): void
    {
        $path = $this->tempDir . '/registry.php';
        (new RegistryConfigWriter())->write(['default' => []], $path);

        $entries = array_diff(scandir($this->tempDir), ['.', '..']);
        // Only the final file should remain. The atomic-rename step
        // moves the temp file into place. A successful write leaves
        // nothing else behind.
        $this->assertSame(['registry.php'], array_values($entries));
    }

    public function testWriteOverwritesExistingFile(): void
    {
        $path = $this->tempDir . '/registry.php';

        $writer = new RegistryConfigWriter();
        $writer->write(['default' => ['horde' => ['name' => 'Old']]], $path);
        $writer->write(['default' => ['horde' => ['name' => 'New']]], $path);

        $loaded = require $path;
        $this->assertSame('New', $loaded['default']['horde']['name']);
    }

    // ===================================================================
    // Error handling
    // ===================================================================

    public function testWriteFailsOnUnwritableParent(): void
    {
        // r-x on the parent: tempnam() can't create a temp file there,
        // and the writer surfaces that as a RuntimeException rather
        // than silently succeeding or leaving a broken state.
        $parent = $this->tempDir . '/readonly';
        mkdir($parent, 0o755);
        chmod($parent, 0o555);

        try {
            $this->expectException(RuntimeException::class);
            (new RegistryConfigWriter())
                ->write(['default' => []], $parent . '/registry.php');
        } finally {
            chmod($parent, 0o755);
        }
    }
}
