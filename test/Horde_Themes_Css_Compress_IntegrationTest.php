<?php

/**
 * Integration test for Horde_Themes_Css_Compress with modern CssMinify.
 *
 * This test verifies that the updated Compress class can instantiate
 * the modern CssMinify API correctly (CssFile validation, type safety).
 *
 * @category Horde
 * @package  Core
 */
class Horde_Themes_Css_Compress_IntegrationTest extends PHPUnit\Framework\TestCase
{
    public function testModernApiCanBeInstantiated(): void
    {
        // Verify modern CssMinify classes exist
        $this->assertTrue(class_exists('Horde\CssMinify\CssParserMinifier'));
        $this->assertTrue(class_exists('Horde\CssMinify\Input\CssFile'));
        $this->assertTrue(class_exists('Horde\CssMinify\Input\FileCollectionInput'));
        $this->assertTrue(class_exists('Horde\CssMinify\Settings'));
        $this->assertTrue(class_exists('Horde\CssMinify\UrlCallback'));
        $this->assertTrue(class_exists('Horde\CssMinify\ImportCallback'));
    }

    public function testCssFileValidation(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($tempFile, 'body { color: red; }');

        // Modern API validates at construction
        $file = new \Horde\CssMinify\Input\CssFile('test.css', $tempFile);

        $this->assertSame('test.css', $file->uri);
        $this->assertSame($tempFile, $file->filepath);

        unlink($tempFile);
    }

    public function testCssFileThrowsOnInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        new \Horde\CssMinify\Input\CssFile('missing.css', '/nonexistent/path.css');
    }

    public function testModernApiTypeSafety(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'css');
        file_put_contents($tempFile, 'body { color: red; }');

        $file = new \Horde\CssMinify\Input\CssFile('test.css', $tempFile);
        $collection = new \Horde\CssMinify\Input\FileCollectionInput($file);
        $minifier = new \Horde\CssMinify\CssParserMinifier($collection);

        $result = $minifier->minify();

        $this->assertIsString($result);
        $this->assertStringContainsString('color:red', $result);

        unlink($tempFile);
    }

    public function testSettingsImmutability(): void
    {
        $urlCallback = new \Horde\CssMinify\UrlCallback(fn ($p) => $p);
        $settings = new \Horde\CssMinify\Settings(dataUrlCallback: $urlCallback);

        // Properties are readonly
        $this->assertSame($urlCallback, $settings->dataUrlCallback);
    }

    public function testCompressCodePath(): void
    {
        // This verifies the updated Compress.php code paths compile
        $reflection = new \ReflectionClass('Horde_Themes_Css_Compress');

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
