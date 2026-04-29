<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Translation;

use Horde\Core\Translation\DelegatingTranslationHandler;
use Horde\Core\Translation\TranslationHandler;
use Horde\Translation\Handler;
use Horde\Translation\NullHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DelegatingTranslationHandler::class)]
#[UsesClass(NullHandler::class)]
class DelegatingTranslationHandlerTest extends TestCase
{
    public function testImplementsTranslationHandler(): void
    {
        $handler = new DelegatingTranslationHandler(new NullHandler());
        $this->assertInstanceOf(TranslationHandler::class, $handler);
        $this->assertInstanceOf(Handler::class, $handler);
    }

    public function testTDelegatesToInnerHandler(): void
    {
        $inner = $this->createMock(Handler::class);
        $inner->expects($this->once())
            ->method('t')
            ->with('Hello')
            ->willReturn('Hallo');

        $handler = new DelegatingTranslationHandler($inner);
        $this->assertSame('Hallo', $handler->t('Hello'));
    }

    public function testUnderscoreDelegatesToT(): void
    {
        $inner = $this->createMock(Handler::class);
        $inner->expects($this->once())
            ->method('t')
            ->with('Goodbye')
            ->willReturn('Tschüss');

        $handler = new DelegatingTranslationHandler($inner);
        $this->assertSame('Tschüss', $handler->_('Goodbye'));
    }

    public function testNgettextDelegates(): void
    {
        $inner = $this->createMock(Handler::class);
        $inner->expects($this->once())
            ->method('ngettext')
            ->with('%d item', '%d items', 5)
            ->willReturn('%d Einträge');

        $handler = new DelegatingTranslationHandler($inner);
        $this->assertSame('%d Einträge', $handler->ngettext('%d item', '%d items', 5));
    }

    public function testFormatDelegates(): void
    {
        $inner = $this->createMock(Handler::class);
        $inner->expects($this->once())
            ->method('format')
            ->with('{count, plural, one {# item} other {# items}}', ['count' => 3], 'de')
            ->willReturn('3 Einträge');

        $handler = new DelegatingTranslationHandler($inner);
        $result = $handler->format('{count, plural, one {# item} other {# items}}', ['count' => 3], 'de');
        $this->assertSame('3 Einträge', $result);
    }

    public function testWithNullHandlerReturnsInput(): void
    {
        $handler = new DelegatingTranslationHandler(new NullHandler());

        $this->assertSame('Hello', $handler->t('Hello'));
        $this->assertSame('Hello', $handler->_('Hello'));
        $this->assertSame('1 item', $handler->ngettext('1 item', '2 items', 1));
        $this->assertSame('2 items', $handler->ngettext('1 item', '2 items', 2));

        $msg = '{count, plural, one {# item} other {# items}}';
        $this->assertSame($msg, $handler->format($msg, ['count' => 5], 'de'));
    }
}
