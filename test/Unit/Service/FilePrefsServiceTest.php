<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Service;

use Horde\Core\Service\FilePrefsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for FilePrefsService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(FilePrefsService::class)]
class FilePrefsServiceTest extends TestCase
{
    private string $tempDir;
    private FilePrefsService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/prefs_test_' . uniqid();
        mkdir($this->tempDir, 0o700, true);
        $this->service = new FilePrefsService($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Recursively delete temp directory
        $this->recursiveDelete($this->tempDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testSetAndGetValue(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $value = $this->service->getValue('user1', 'horde', 'theme');

        $this->assertEquals('silver', $value);
    }

    public function testGetNonExistentValue(): void
    {
        $value = $this->service->getValue('user1', 'horde', 'missing');

        $this->assertNull($value);
    }

    public function testGetValueFromNonExistentFile(): void
    {
        $value = $this->service->getValue('nonexistent', 'horde', 'theme');

        $this->assertNull($value);
    }

    public function testSetMultipleValuesInScope(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->service->setValue('user1', 'horde', 'language', 'de_DE');
        $this->service->setValue('user1', 'horde', 'timezone', 'Europe/Berlin');

        $this->assertEquals('silver', $this->service->getValue('user1', 'horde', 'theme'));
        $this->assertEquals('de_DE', $this->service->getValue('user1', 'horde', 'language'));
        $this->assertEquals('Europe/Berlin', $this->service->getValue('user1', 'horde', 'timezone'));
    }

    public function testScopeSeparation(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->service->setValue('user1', 'imp', 'theme', 'gold');

        $this->assertEquals('silver', $this->service->getValue('user1', 'horde', 'theme'));
        $this->assertEquals('gold', $this->service->getValue('user1', 'imp', 'theme'));
    }

    public function testUserSeparation(): void
    {
        $this->service->setValue('alice', 'horde', 'theme', 'silver');
        $this->service->setValue('bob', 'horde', 'theme', 'gold');

        $this->assertEquals('silver', $this->service->getValue('alice', 'horde', 'theme'));
        $this->assertEquals('gold', $this->service->getValue('bob', 'horde', 'theme'));
    }

    public function testDeleteValue(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->assertTrue($this->service->exists('user1', 'horde', 'theme'));

        $this->service->deleteValue('user1', 'horde', 'theme');

        $this->assertFalse($this->service->exists('user1', 'horde', 'theme'));
        $this->assertNull($this->service->getValue('user1', 'horde', 'theme'));
    }

    public function testDeleteNonExistentValueDoesNotThrow(): void
    {
        // Should not throw even if value doesn't exist
        $this->service->deleteValue('user1', 'horde', 'nonexistent');

        $this->assertFalse($this->service->exists('user1', 'horde', 'nonexistent'));
    }

    public function testGetAllInScope(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->service->setValue('user1', 'horde', 'language', 'de_DE');
        $this->service->setValue('user1', 'horde', 'timezone', 'Europe/Berlin');

        $all = $this->service->getAllInScope('user1', 'horde');

        $this->assertCount(3, $all);
        $this->assertEquals('silver', $all['theme']);
        $this->assertEquals('de_DE', $all['language']);
        $this->assertEquals('Europe/Berlin', $all['timezone']);
    }

    public function testGetAllInScopeEmptyForNonExistent(): void
    {
        $all = $this->service->getAllInScope('nonexistent', 'horde');

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    public function testExists(): void
    {
        $this->assertFalse($this->service->exists('user1', 'horde', 'theme'));

        $this->service->setValue('user1', 'horde', 'theme', 'silver');

        $this->assertTrue($this->service->exists('user1', 'horde', 'theme'));
    }

    public function testUpdateExistingValue(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->assertEquals('silver', $this->service->getValue('user1', 'horde', 'theme'));

        $this->service->setValue('user1', 'horde', 'theme', 'gold');
        $this->assertEquals('gold', $this->service->getValue('user1', 'horde', 'theme'));
    }

    public function testSetComplexValue(): void
    {
        $complex = ['key1' => 'value1', 'key2' => ['nested' => 'data']];
        $this->service->setValue('user1', 'horde', 'complex', $complex);

        $retrieved = $this->service->getValue('user1', 'horde', 'complex');
        $this->assertEquals($complex, $retrieved);
    }

    public function testSetNullValue(): void
    {
        $this->service->setValue('user1', 'horde', 'nullable', null);

        $value = $this->service->getValue('user1', 'horde', 'nullable');
        $this->assertNull($value);
    }

    public function testSetBooleanValue(): void
    {
        $this->service->setValue('user1', 'horde', 'enabled', true);
        $this->assertTrue($this->service->getValue('user1', 'horde', 'enabled'));

        $this->service->setValue('user1', 'horde', 'disabled', false);
        $this->assertFalse($this->service->getValue('user1', 'horde', 'disabled'));
    }

    public function testFilePermissions(): void
    {
        $this->service->setValue('user1', 'horde', 'theme', 'silver');

        $file = $this->tempDir . '/horde/user1.prefs';
        $this->assertFileExists($file);

        $perms = fileperms($file) & 0o777;
        $this->assertEquals(0o600, $perms); // Owner read/write only
    }

    public function testDirectoryStructureCreation(): void
    {
        $this->service->setValue('user1', 'imp', 'theme', 'silver');

        $scopeDir = $this->tempDir . '/imp';
        $this->assertDirectoryExists($scopeDir);

        $file = $scopeDir . '/user1.prefs';
        $this->assertFileExists($file);
    }

    public function testSanitizeUsernamePreventsDotDotAttack(): void
    {
        // Attempt directory traversal attack
        $this->service->setValue('../../../etc/passwd', 'horde', 'theme', 'hack');

        // Should create file in tempDir with sanitized name
        $file = $this->tempDir . '/horde/passwd.prefs'; // basename strips ../
        $this->assertFileExists($file);

        // Verify file was created in tempDir (not traversed out)
        $realPath = realpath($file);
        $this->assertStringStartsWith(realpath($this->tempDir), $realPath);

        // Verify we can read the value back (proving it was stored safely)
        $value = $this->service->getValue('../../../etc/passwd', 'horde', 'theme');
        $this->assertEquals('hack', $value);
    }

    public function testConstructorCreatesDirectory(): void
    {
        $newDir = sys_get_temp_dir() . '/prefs_test_new_' . uniqid();

        $this->assertDirectoryDoesNotExist($newDir);

        $service = new FilePrefsService($newDir);

        $this->assertDirectoryExists($newDir);

        // Cleanup
        rmdir($newDir);
    }

    public function testHandleCorruptedFile(): void
    {
        // Manually create a corrupted prefs file
        $scopeDir = $this->tempDir . '/horde';
        mkdir($scopeDir, 0o700, true);
        file_put_contents($scopeDir . '/user1.prefs', 'corrupted data {not serialized}');

        // Should return empty array instead of throwing
        $all = $this->service->getAllInScope('user1', 'horde');
        $this->assertIsArray($all);
        $this->assertEmpty($all);

        // Should return null for specific key
        $value = $this->service->getValue('user1', 'horde', 'theme');
        $this->assertNull($value);

        // Should be able to overwrite corrupted file
        $this->service->setValue('user1', 'horde', 'theme', 'silver');
        $this->assertEquals('silver', $this->service->getValue('user1', 'horde', 'theme'));
    }
}
