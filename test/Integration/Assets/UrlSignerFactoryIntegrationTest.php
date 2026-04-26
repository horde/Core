<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Assets;

use Horde\Core\Assets\HmacUrlSigner;
use Horde\Core\Assets\NullUrlSigner;
use Horde\Core\Assets\UrlSigner;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class UrlSignerFactoryIntegrationTest extends TestCase
{
    #[Test]
    public function injectorResolvesHmacWhenSecretConfigured(): void
    {
        $GLOBALS['conf']['secret_key'] = 'integration-test-key';
        $GLOBALS['conf']['urls']['hmac_lifetime'] = 30;

        try {
            $injector = new Injector(new TopLevel());
            $signer = $injector->getInstance(UrlSigner::class);

            self::assertInstanceOf(UrlSigner::class, $signer);
            self::assertInstanceOf(HmacUrlSigner::class, $signer);
        } finally {
            unset($GLOBALS['conf']);
        }
    }

    #[Test]
    public function injectorResolvesNullWhenNoSecret(): void
    {
        unset($GLOBALS['conf']);

        $injector = new Injector(new TopLevel());
        $signer = $injector->getInstance(UrlSigner::class);

        self::assertInstanceOf(NullUrlSigner::class, $signer);
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $GLOBALS['conf']['secret_key'] = 'test-key';

        try {
            $injector = new Injector(new TopLevel());
            $first = $injector->getInstance(UrlSigner::class);
            $second = $injector->getInstance(UrlSigner::class);

            self::assertSame($first, $second);
        } finally {
            unset($GLOBALS['conf']);
        }
    }

    #[Test]
    public function factoryProducedSignerWorksEndToEnd(): void
    {
        $GLOBALS['conf']['secret_key'] = 'e2e-test-key';
        $GLOBALS['conf']['urls']['hmac_lifetime'] = 30;

        try {
            $injector = new Injector(new TopLevel());
            $signer = $injector->getInstance(UrlSigner::class);

            $now = 1700000000;
            $signed = $signer->signUrl('/test/page', $now);
            $verified = $signer->verifySignedUrl($signed, $now);

            self::assertSame('/test/page', $verified);
        } finally {
            unset($GLOBALS['conf']);
        }
    }
}
