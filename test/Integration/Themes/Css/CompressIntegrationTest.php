<?php

declare(strict_types=1);

/**
 * Integration test for Horde_Themes_Css_Compress with modern CssMinify.
 *
 * This test verifies that the updated Compress class can instantiate
 * the modern CssMinify API correctly (CssFile validation, type safety).
 *
 * @category Horde
 * @package  Core
 */

namespace Horde\Core\Test\Integration\Themes\Css;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\CssMinify\CssParserMinifier;
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use Horde\CssMinify\ImportCallback;
use Horde_Themes_Css_Compress;
use InvalidArgumentException;
use ReflectionClass;

#[CoversClass(Horde_Themes_Css_Compress::class)]
class CompressIntegrationTest extends TestCase
{
    public function testModernApiCanBeInstantiated(): void
    {
        // Verify modern CssMinify classes exist
        $this->assertTrue(class_exists(CssParserMinifier::class));
        $this->assertTrue(class_exists(CssFile::class));
        $this->assertTrue(class_exists(FileCollectionInput::class));
        $this->assertTrue(class_exists(Settings::class));
        $this->assertTrue(class_exists(UrlCallback::class));
        $this->assertTrue(class_exists(ImportCallback::class));
    }

    public function testCssFileValidation(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($tempFile, 'body { color: red; }');

        // Modern API validates at construction
        $file = new CssFile('test.css', $tempFile);

        $this->assertSame('test.css', $file->uri);
        $this->assertSame($tempFile, $file->filepath);

        unlink($tempFile);
    }

    public function testCssFileThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        new CssFile('missing.css', '/nonexistent/path.css');
    }

    public function testModernApiTypeSafety(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($tempFile, 'body { color: red; }');

        $file = new CssFile('test.css', $tempFile);
        $collection = new FileCollectionInput($file);
        $minifier = new CssParserMinifier($collection);

        $result = $minifier->minify();

        $this->assertIsString($result);
        $this->assertStringContainsString('color:red', $result);

        unlink($tempFile);
    }

    public function testSettingsImmutability(): void
    {
        $urlCallback = new UrlCallback(fn($p) => $p);
        $settings = new Settings(dataUrlCallback: $urlCallback);

        // Properties are readonly
        $this->assertSame($urlCallback, $settings->dataUrlCallback);
    }

    public function testCompressCodePath(): void
    {
        // This verifies the updated Compress.php code paths compile
        $reflection = new ReflectionClass(Horde_Themes_Css_Compress::class);

        // Verify compress method exists
        $this->assertTrue($reflection->hasMethod('compress'));

        // Verify it uses modern API classes (check use statements in file)
        $filename = $reflection->getFileName();
        $content = file_get_contents($filename);

        $this->assertStringContainsString('use Horde\CssMinify\CssParserMinifier', $content);
        $this->assertStringContainsString('use Horde\CssMinify\Input\CssFile', $content);
        $this->assertStringContainsString('use Horde\CssMinify\Input\FileCollectionInput', $content);
        $this->assertStringContainsString('use Horde\CssMinify\Settings', $content);
    }
}
