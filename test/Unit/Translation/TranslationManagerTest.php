<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Translation;

use Horde\Core\Translation\TranslationManager;
use Horde\Core\Translation\DelegatingTranslationHandler;
use Horde\Core\Translation\DomainConfig;
use Horde\Core\Translation\TranslationHandler;
use Horde\Translation\Handler;
use Horde\Translation\NullHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationManager::class)]
#[UsesClass(DomainConfig::class)]
#[UsesClass(DelegatingTranslationHandler::class)]
#[UsesClass(NullHandler::class)]
class TranslationManagerTest extends TestCase
{
    public function testAddDomainAndHasDomain(): void
    {
        $manager = new TranslationManager();
        $this->assertFalse($manager->hasDomain('nag'));

        $manager->addDomain('nag', '/some/path/locale');
        $this->assertTrue($manager->hasDomain('nag'));
    }

    public function testListDomains(): void
    {
        $manager = new TranslationManager();
        $manager->addDomain('nag', '/path/nag/locale');
        $manager->addDomain('turba', '/path/turba/locale');

        $this->assertSame(['nag', 'turba'], $manager->listDomains());
    }

    public function testHandlerReturnsTranslationHandler(): void
    {
        $manager = new TranslationManager();
        $manager->addDomain('test', '/some/path', function (string $domain, string $path, string $language): Handler {
            return new NullHandler();
        });

        $handler = $manager->handler('test', 'de_DE');
        $this->assertInstanceOf(TranslationHandler::class, $handler);
    }

    public function testHandlerCachesInstances(): void
    {
        $manager = new TranslationManager();
        $callCount = 0;
        $manager->addDomain('test', '/path', function () use (&$callCount): Handler {
            $callCount++;
            return new NullHandler();
        });

        $h1 = $manager->handler('test', 'de');
        $h2 = $manager->handler('test', 'de');
        $this->assertSame($h1, $h2);
        $this->assertSame(1, $callCount);
    }

    public function testDifferentLanguagesGetDifferentHandlers(): void
    {
        $manager = new TranslationManager();
        $manager->addDomain('test', '/path', fn() => new NullHandler());

        $de = $manager->handler('test', 'de');
        $fr = $manager->handler('test', 'fr');
        $this->assertNotSame($de, $fr);
    }

    public function testUnknownDomainReturnsNullHandler(): void
    {
        $manager = new TranslationManager();

        $handler = $manager->handler('nonexistent', 'en');
        $this->assertInstanceOf(TranslationHandler::class, $handler);
        $this->assertSame('untranslated', $handler->t('untranslated'));
    }

    public function testHandlerFactoryReceivesCorrectArgs(): void
    {
        $manager = new TranslationManager();
        $received = [];
        $manager->addDomain('myapp', '/app/locale', function (string $domain, string $path, string $language) use (&$received): Handler {
            $received = [$domain, $path, $language];
            return new NullHandler();
        });

        $manager->handler('myapp', 'fr_FR');
        $this->assertSame(['myapp', '/app/locale', 'fr_FR'], $received);
    }
}
