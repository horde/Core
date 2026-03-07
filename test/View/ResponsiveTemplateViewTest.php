<?php

declare(strict_types=1);

namespace Horde\Core\Test\View;

use Horde\Core\View\ResponsiveTemplateView;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for ResponsiveTemplateView class
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Claude Code Assistant
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(ResponsiveTemplateView::class)]
class ResponsiveTemplateViewTest extends TestCase
{
    private string $tempTemplate;

    protected function setUp(): void
    {
        // Create temporary template file
        $this->tempTemplate = tempnam(sys_get_temp_dir(), 'tpl_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempTemplate)) {
            unlink($this->tempTemplate);
        }
    }

    public function testConstructorThrowsExceptionForMissingTemplate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template not found');

        new ResponsiveTemplateView('/nonexistent/template.php', []);
    }

    public function testRenderSimpleTemplate(): void
    {
        // Create simple template
        file_put_contents($this->tempTemplate, '<?php echo $message; ?>');

        // Create view with data
        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'message' => 'Hello World'
        ]);

        // Render
        $output = $view->render();

        $this->assertEquals('Hello World', $output);
    }

    public function testRenderWithMultipleVariables(): void
    {
        file_put_contents($this->tempTemplate, '<?php echo $greeting . " " . $name; ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'greeting' => 'Hello',
            'name' => 'Horde'
        ]);

        $output = $view->render();
        $this->assertEquals('Hello Horde', $output);
    }

    public function testRenderWithHtmlTemplate(): void
    {
        $template = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title><?php echo $title; ?></title></head>
<body><h1><?php echo $heading; ?></h1></body>
</html>
HTML;
        file_put_contents($this->tempTemplate, $template);

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'title' => 'Test Page',
            'heading' => 'Welcome'
        ]);

        $output = $view->render();
        $this->assertStringContainsString('<title>Test Page</title>', $output);
        $this->assertStringContainsString('<h1>Welcome</h1>', $output);
    }

    public function testMagicGetterAccess(): void
    {
        file_put_contents($this->tempTemplate, '<?= $this->property ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'property' => 'value123'
        ]);

        $output = $view->render();
        $this->assertEquals('value123', $output);
    }

    public function testMagicGetterReturnsNullForMissingProperty(): void
    {
        file_put_contents($this->tempTemplate, '<?php var_export($this->missing); ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, []);

        $output = $view->render();
        $this->assertEquals('NULL', $output);
    }

    public function testMagicIsset(): void
    {
        file_put_contents($this->tempTemplate, '<?php echo isset($this->exists) ? "yes" : "no"; ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'exists' => 'value'
        ]);

        $output = $view->render();
        $this->assertEquals('yes', $output);
    }

    public function testMagicIssetReturnsFalseForMissing(): void
    {
        file_put_contents($this->tempTemplate, '<?php echo isset($this->missing) ? "yes" : "no"; ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, []);

        $output = $view->render();
        $this->assertEquals('no', $output);
    }

    public function testViewDataIsImmutable(): void
    {
        file_put_contents($this->tempTemplate, '<?php echo "test"; ?>');
        $view = new ResponsiveTemplateView($this->tempTemplate, ['key' => 'value']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $view->key = 'new value';
    }

    public function testGetData(): void
    {
        $data = [
            'name' => 'Test',
            'value' => 123,
            'array' => [1, 2, 3]
        ];

        file_put_contents($this->tempTemplate, '<?php echo "test"; ?>');
        $view = new ResponsiveTemplateView($this->tempTemplate, $data);

        $this->assertEquals($data, $view->getData());
    }

    public function testEscapeHelper(): void
    {
        file_put_contents($this->tempTemplate, '<?= $this->escape($html) ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'html' => '<script>alert("xss")</script>'
        ]);

        $output = $view->render();
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testEscapeHelperWithNull(): void
    {
        file_put_contents($this->tempTemplate, '<?= $this->escape(null) ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, []);

        $output = $view->render();
        $this->assertEquals('', $output);
    }

    public function testEscapeHelperWithNumber(): void
    {
        file_put_contents($this->tempTemplate, '<?= $this->escape(123) ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, []);

        $output = $view->render();
        $this->assertEquals('123', $output);
    }

    public function testEscapeAttrHelper(): void
    {
        file_put_contents($this->tempTemplate, '<div data-value="<?= $this->escapeAttr($attr) ?>"></div>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'attr' => 'value"onclick="alert(1)'
        ]);

        $output = $view->render();
        // The quotes should be escaped to &quot;
        $this->assertStringContainsString('&quot;', $output);
        $this->assertStringContainsString('value&quot;onclick=&quot;alert(1)', $output);
    }

    public function testEscapeUrlHelper(): void
    {
        file_put_contents($this->tempTemplate, '<a href="<?= $this->escapeUrl($url) ?>">Link</a>');

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'url' => 'http://example.com?foo=bar&baz=qux'
        ]);

        $output = $view->render();
        // Ampersands should be escaped
        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('foo=bar&amp;baz=qux', $output);
    }

    public function testRenderThrowsExceptionOnTemplateError(): void
    {
        // Template with syntax error
        file_put_contents($this->tempTemplate, '<?php throw new \Exception("Template error"); ?>');

        $view = new ResponsiveTemplateView($this->tempTemplate, []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template rendering failed');

        $view->render();
    }

    public function testRenderCapturesAllOutput(): void
    {
        $template = <<<'PHP'
<?php
echo "Line 1\n";
echo "Line 2\n";
echo "Line 3";
?>
PHP;
        file_put_contents($this->tempTemplate, $template);

        $view = new ResponsiveTemplateView($this->tempTemplate, []);
        $output = $view->render();

        $this->assertStringContainsString('Line 1', $output);
        $this->assertStringContainsString('Line 2', $output);
        $this->assertStringContainsString('Line 3', $output);
    }

    public function testRenderWithLoop(): void
    {
        $template = <<<'PHP'
<?php foreach ($items as $item): ?>
<li><?= $this->escape($item) ?></li>
<?php endforeach; ?>
PHP;
        file_put_contents($this->tempTemplate, $template);

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'items' => ['Item 1', 'Item 2', 'Item 3']
        ]);

        $output = $view->render();
        $this->assertStringContainsString('<li>Item 1</li>', $output);
        $this->assertStringContainsString('<li>Item 2</li>', $output);
        $this->assertStringContainsString('<li>Item 3</li>', $output);
    }

    public function testRenderWithConditional(): void
    {
        $template = <<<'PHP'
<?php if ($show): ?>
<p>Visible</p>
<?php else: ?>
<p>Hidden</p>
<?php endif; ?>
PHP;
        file_put_contents($this->tempTemplate, $template);

        $view = new ResponsiveTemplateView($this->tempTemplate, [
            'show' => true
        ]);

        $output = $view->render();
        $this->assertStringContainsString('<p>Visible</p>', $output);
        $this->assertStringNotContainsString('<p>Hidden</p>', $output);
    }
}
