<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Translation;

use Horde\Core\Translation\DomainConfig;
use Horde\Translation\Handler;
use Horde\Translation\GettextHandler;
use Horde\Translation\NullHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainConfig::class)]
class DomainConfigTest extends TestCase
{
    public function testGetters(): void
    {
        $config = new DomainConfig('nag', '/path/to/locale');
        $this->assertSame('nag', $config->getDomain());
        $this->assertSame('/path/to/locale', $config->getLocalePath());
    }

    public function testCreateHandlerWithFactory(): void
    {
        $receivedArgs = [];
        $factory = function (string $domain, string $path, string $language) use (&$receivedArgs): Handler {
            $receivedArgs = [$domain, $path, $language];
            return new NullHandler();
        };

        $config = new DomainConfig('myapp', '/app/locale', $factory);
        $handler = $config->createHandler('de_DE');

        $this->assertInstanceOf(NullHandler::class, $handler);
        $this->assertSame(['myapp', '/app/locale', 'de_DE'], $receivedArgs);
    }

    public function testCreateHandlerDefaultsToGettextHandler(): void
    {
        $localePath = __DIR__ . '/../../fixtures/locale';

        // Only test default if we have a valid locale dir
        if (!is_dir($localePath)) {
            $this->markTestSkipped('No locale fixtures available for Core');
        }

        $config = new DomainConfig('test', $localePath);
        $handler = $config->createHandler('de');

        $this->assertInstanceOf(GettextHandler::class, $handler);
    }
}
