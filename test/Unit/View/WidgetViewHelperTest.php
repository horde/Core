<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\View;

use Horde\Core\View\AccessKeyTracker;
use Horde\Core\View\WidgetViewHelper;
use Horde\Url\Url;
use Horde_View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetViewHelper::class)]
class WidgetViewHelperTest extends TestCase
{
    private function createHelper(
        bool $accessKeysEnabled = true,
        bool $multibyte = false,
    ): WidgetViewHelper {
        $view = new Horde_View();
        $tracker = new AccessKeyTracker($accessKeysEnabled, $multibyte);
        return new WidgetViewHelper($view, $tracker);
    }

    public function testHordeWidgetProducesAnchorWithAccessKey(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidget('/edit', '_Edit');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('href="/edit"', $result);
        $this->assertStringContainsString('accesskey="E"', $result);
        $this->assertStringContainsString('<span class="accessKey">E</span>dit', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testHordeWidgetWithClassAndTarget(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidget('/path', '_Link', class: 'nav-item', target: '_blank');

        $this->assertStringContainsString('class="nav-item"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    public function testHordeWidgetWithOnclick(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidget('#', '_Action', onclick: 'doStuff()');

        $this->assertStringContainsString('onclick="doStuff()"', $result);
    }

    public function testHordeWidgetWithUrlObject(): void
    {
        $helper = $this->createHelper();
        $url = new Url('/tasks');
        $result = $helper->hordeWidget($url, '_Tasks');

        $this->assertStringContainsString('href="/tasks"', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testHordeWidgetWithExtraAttributes(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidget('/path', '_Link', attributes: ['id' => 'my-link', 'data-action' => 'open']);

        $this->assertStringContainsString('id="my-link"', $result);
        $this->assertStringContainsString('data-action="open"', $result);
    }

    public function testHordeWidgetDisabledAccessKeys(): void
    {
        $helper = $this->createHelper(accessKeysEnabled: false);
        $result = $helper->hordeWidget('/edit', '_Edit');

        $this->assertStringNotContainsString('accesskey=', $result);
        $this->assertStringContainsString('Edit', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testHordeWidgetIfTrueReturnsWidget(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidgetIf(true, '/edit', '_Edit');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testHordeWidgetIfFalseReturnsEmpty(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeWidgetIf(false, '/edit', '_Edit');

        $this->assertSame('', $result);
    }

    public function testHordeLabelProducesLabelElement(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLabel('username', '_Username');

        $this->assertStringContainsString('<label', $result);
        $this->assertStringContainsString('for="username"', $result);
        $this->assertStringContainsString('accesskey="U"', $result);
        $this->assertStringContainsString('<span class="accessKey">U</span>sername', $result);
        $this->assertStringContainsString('</label>', $result);
    }

    public function testHordeLabelWithExplicitAccessKey(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLabel('field', '_Name', 'x');

        $this->assertStringContainsString('accesskey="x"', $result);
    }

    public function testHordeLabelDisabledAccessKeys(): void
    {
        $helper = $this->createHelper(accessKeysEnabled: false);
        $result = $helper->hordeLabel('field', '_Name');

        $this->assertStringNotContainsString('accesskey=', $result);
        $this->assertStringContainsString('Name', $result);
    }

    public function testHelperIsCallableFromView(): void
    {
        $view = new Horde_View();
        $tracker = new AccessKeyTracker();
        new WidgetViewHelper($view, $tracker);

        $result = $view->hordeWidget('/test', '_Test');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    // --- hordeLink() tests ---

    public function testHordeLinkProducesOpeningAnchorTag(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page');

        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('href="/page"', $result);
        $this->assertStringEndsWith('>', $result);
        $this->assertStringNotContainsString('</a>', $result);
    }

    public function testHordeLinkWithTitle(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', 'My Title');

        $this->assertStringContainsString('title="My Title"', $result);
    }

    public function testHordeLinkEscapesTitleEntities(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', 'Tom & Jerry');

        $this->assertStringContainsString('title="', $result);
        $this->assertStringNotContainsString('&amp;amp;', $result);
        $this->assertStringContainsString('Tom &amp; Jerry', $result);
    }

    public function testHordeLinkStripsNewlinesFromTitle(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', "Line1\nLine2");

        $this->assertStringNotContainsString("\n", $result);
    }

    public function testHordeLinkRawTitleSkipsEscaping(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', '<b>bold</b>', escape: false);

        $this->assertStringContainsString('title="<b>bold</b>"', $result);
    }

    public function testHordeLinkWithClassTargetOnclick(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', '', 'nav', '_blank', 'go()');

        $this->assertStringContainsString('class="nav"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('onclick="go()"', $result);
    }

    public function testHordeLinkWithAccessKey(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', 'Edit', accesskey: 'e');

        $this->assertStringContainsString('accesskey="e"', $result);
    }

    public function testHordeLinkWithExtraAttributes(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page', '', attributes: ['id' => 'link1', 'rel' => 'noreferrer']);

        $this->assertStringContainsString('id="link1"', $result);
        $this->assertStringContainsString('rel="noreferrer"', $result);
    }

    public function testHordeLinkWithUrlObject(): void
    {
        $helper = $this->createHelper();
        $url = new Url('/typed');
        $result = $helper->hordeLink($url, 'Test');

        $this->assertStringContainsString('href="/typed"', $result);
    }

    public function testHordeLinkEmptyTitleOmitsAttribute(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLink('/page');

        $this->assertStringNotContainsString('title=', $result);
    }

    // --- hordeLinkTooltip() tests ---

    public function testHordeLinkTooltipAddsNicetitleAttribute(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page', title: 'Tooltip text');

        $this->assertStringContainsString('nicetitle="', $result);
        $this->assertStringContainsString('Tooltip text', $result);
    }

    public function testHordeLinkTooltipSplitsMultilineTitle(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page', title: "Line1\nLine2");

        $this->assertStringContainsString('nicetitle="', $result);
        $this->assertStringContainsString('Line1', $result);
        $this->assertStringContainsString('Line2', $result);
    }

    public function testHordeLinkTooltipConvertsBrToNewlines(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page', title: 'A<br>B');

        $this->assertStringContainsString('nicetitle="', $result);
    }

    public function testHordeLinkTooltipEmptyTitleSkipsNicetitle(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page');

        $this->assertStringNotContainsString('nicetitle=', $result);
    }

    public function testHordeLinkTooltipPassesClassAndTarget(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page', class: 'tip', target: '_blank', title: 'Info');

        $this->assertStringContainsString('class="tip"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    public function testHordeLinkTooltipDoesNotSetTitleAttribute(): void
    {
        $helper = $this->createHelper();
        $result = $helper->hordeLinkTooltip('/page', title: 'Tooltip');

        $this->assertMatchesRegularExpression('/nicetitle=/', $result);
        $this->assertDoesNotMatchRegularExpression('/ title=/', $result);
    }
}
