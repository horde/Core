<?php

/**
 * Tests for Horde_Themes_Css_Compress using modern CssMinify API.
 *
 * @category Horde
 * @package  Core
 */
class Horde_Themes_Css_CompressTest extends PHPUnit\Framework\TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        // Use stubs for globals - they just need to exist, no expectations
        $GLOBALS['browser'] = $this->createStub(Horde_Browser::class);
        $GLOBALS['browser']->method('hasFeature')->willReturn(false);

        $GLOBALS['registry'] = $this->createStub(Horde_Registry::class);

        // Use stubs for logger and injector
        $logger = $this->createStub(\Psr\Log\LoggerInterface::class);
        $GLOBALS['injector'] = $this->createStub(Horde_Injector::class);
        $GLOBALS['injector']->method('get')->willReturn($logger);

        $this->fixturesPath = __DIR__ . '/fixtures/';

        // Create fixtures directory if it doesn't exist
        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0o777, true);
        }

        // Create test CSS file
        file_put_contents(
            $this->fixturesPath . 'test.css',
            'body { color: red; margin: 10px; }'
        );

        file_put_contents(
            $this->fixturesPath . 'with-import.css',
            '@import "shared.css"; body { background: white; }'
        );

        file_put_contents(
            $this->fixturesPath . 'shared.css',
            '.header { padding: 20px; }'
        );

        file_put_contents(
            $this->fixturesPath . 'with-url.css',
            'body { background-image: url(../images/bg.png); }'
        );
    }

    protected function tearDown(): void
    {
        // Cleanup globals
        unset($GLOBALS['browser'], $GLOBALS['registry'], $GLOBALS['injector']);

        // Cleanup fixtures
        $files = glob($this->fixturesPath . '*.css');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->fixturesPath)) {
            rmdir($this->fixturesPath);
        }
    }

    public function testCompressBasicCss(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        $css = [
            ['uri' => 'test.css', 'fs' => $this->fixturesPath . 'test.css'],
        ];

        $result = $compress->compress($css);

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('margin:10px', $result);
    }

    public function testCompressMultipleFiles(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        $css = [
            ['uri' => 'test.css', 'fs' => $this->fixturesPath . 'test.css'],
            ['uri' => 'shared.css', 'fs' => $this->fixturesPath . 'shared.css'],
        ];

        $result = $compress->compress($css);

        $this->assertStringContainsString('color:red', $result);
        $this->assertStringContainsString('.header', $result);
    }

    public function testCompressHandlesInvalidFiles(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        $css = [
            ['uri' => 'missing.css', 'fs' => $this->fixturesPath . 'nonexistent.css'],
        ];

        // Should not throw, returns empty string
        $result = $compress->compress($css);

        $this->assertSame('', $result);
    }

    public function testCompressHandlesMissingKeys(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        $css = [
            ['uri' => 'test.css'], // Missing 'fs'
            ['fs' => $this->fixturesPath . 'test.css'], // Missing 'uri'
            [], // Missing both
        ];

        // Should not throw, skips invalid entries
        $result = $compress->compress($css);

        $this->assertSame('', $result);
    }

    public function testCompressHandlesEmptyInput(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        $result = $compress->compress([]);

        $this->assertSame('', $result);
    }

    public function testDataurlCallback(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        // Test callback is callable
        $result = $compress->dataurlCallback('/path/to/image.png');

        // Should return something (actual implementation depends on Horde_Themes_Image)
        $this->assertIsString($result);
    }

    public function testImportCallback(): void
    {
        $compress = new Horde_Themes_Css_Compress();

        // Test callback returns array with uri and fs
        $result = $compress->importCallback('test.css');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
}
