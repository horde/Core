<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\View;

use Horde\Core\View\AccessKeyTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessKeyTracker::class)]
class AccessKeyTrackerTest extends TestCase
{
    public function testAcquireReturnsKeyFromUnderscoreMarker(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
    }

    public function testAcquireReturnsEmptyWhenNoMarker(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('', $tracker->acquire('Save'));
    }

    public function testAcquireReturnsEmptyWhenDisabled(): void
    {
        $tracker = new AccessKeyTracker(accessKeysEnabled: false);
        $this->assertSame('', $tracker->acquire('_Edit'));
    }

    public function testAcquirePreventsDuplicateKeys(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
        $this->assertSame('', $tracker->acquire('_Export'));
    }

    public function testAcquireDifferentKeysSucceed(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
        $this->assertSame('D', $tracker->acquire('_Delete'));
    }

    public function testNocheckAllowsDuplicateForSameLabel(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
        $this->assertSame('E', $tracker->acquire('_Edit', nocheck: true));
    }

    public function testNocheckDoesNotAllowDuplicateForDifferentLabel(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
        $this->assertSame('', $tracker->acquire('_Export', nocheck: true));
    }

    public function testStripRemovesUnderscoreMarker(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('Edit', $tracker->strip('_Edit'));
    }

    public function testStripPreservesLabelWithoutMarker(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('Save', $tracker->strip('Save'));
    }

    public function testStripMultibyteRemovesEntireMarkerWhenHighBytes(): void
    {
        $tracker = new AccessKeyTracker(multibyte: true);
        $label = "編集_E";
        $this->assertSame('編集', $tracker->strip($label));
    }

    public function testStripMultibyteKeepsLetterForAsciiOnly(): void
    {
        $tracker = new AccessKeyTracker(multibyte: true);
        $this->assertSame('Edit', $tracker->strip('_Edit'));
    }

    public function testHighlightWrapsAccessKey(): void
    {
        $tracker = new AccessKeyTracker();
        $result = $tracker->highlight('_Edit', 'E');
        $this->assertSame('<span class="accessKey">E</span>dit', $result);
    }

    public function testHighlightEmptyKeyReturnsStrippedLabel(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('Edit', $tracker->highlight('_Edit', ''));
    }

    public function testHighlightMultibyteAppendsKey(): void
    {
        $tracker = new AccessKeyTracker(multibyte: true);
        $result = $tracker->highlight('_Edit', 'E');
        $this->assertStringContainsString('(<span class="accessKey">E</span>)', $result);
        $this->assertStringStartsWith('Edit', $result);
    }

    public function testGetAccessKeyAndTitleReturnsArrayWithKey(): void
    {
        $tracker = new AccessKeyTracker();
        $result = $tracker->getAccessKeyAndTitle('_Edit');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('accesskey', $result);
        $this->assertSame('E', $result['accesskey']);
        $this->assertSame('Edit', substr($result['title'], 0, 4));
    }

    public function testGetAccessKeyAndTitleWithoutKey(): void
    {
        $tracker = new AccessKeyTracker(accessKeysEnabled: false);
        $result = $tracker->getAccessKeyAndTitle('_Edit');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayNotHasKey('accesskey', $result);
    }

    public function testResetClearsState(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('E', $tracker->acquire('_Edit'));
        $this->assertSame('', $tracker->acquire('_Export'));

        $tracker->reset();

        $this->assertSame('E', $tracker->acquire('_Export'));
    }

    public function testAcquireIsCaseInsensitive(): void
    {
        $tracker = new AccessKeyTracker();
        $this->assertSame('e', $tracker->acquire('_edit'));
        $this->assertSame('', $tracker->acquire('_Export'));
    }

    public function testMultipleMarkersOnlyFirstUsed(): void
    {
        $tracker = new AccessKeyTracker();
        $key = $tracker->acquire('_Edit _Document');
        $this->assertSame('E', $key);
    }
}
