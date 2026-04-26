<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\HmacUrlSigner;
use Horde\Core\Assets\UrlSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HmacUrlSigner::class)]
class HmacUrlSignerTest extends TestCase
{
    private HmacUrlSigner $signer;
    private string $secret = 'test-secret-key-2026';

    protected function setUp(): void
    {
        $this->signer = new HmacUrlSigner($this->secret, 30);
    }

    #[Test]
    public function implementsUrlSigner(): void
    {
        self::assertInstanceOf(UrlSigner::class, $this->signer);
    }

    #[Test]
    public function signUrlAppendsParams(): void
    {
        $signed = $this->signer->signUrl('/app/page', 1700000000);

        self::assertStringContainsString('?_t=1700000000', $signed);
        self::assertStringContainsString('&_h=', $signed);
    }

    #[Test]
    public function signUrlWithExistingQuery(): void
    {
        $signed = $this->signer->signUrl('/app/page?foo=bar', 1700000000);

        self::assertStringContainsString('&_t=1700000000', $signed);
        self::assertStringContainsString('&_h=', $signed);
        self::assertStringNotContainsString('?_t=', $signed);
    }

    #[Test]
    public function signEmptyUrlReturnsEmpty(): void
    {
        self::assertSame('', $this->signer->signUrl(''));
    }

    #[Test]
    public function signAndVerifyRoundTrip(): void
    {
        $now = 1700000000;
        $signed = $this->signer->signUrl('/app/page', $now);
        $result = $this->signer->verifySignedUrl($signed, $now);

        self::assertSame('/app/page', $result);
    }

    #[Test]
    public function signAndVerifyWithQuery(): void
    {
        $now = 1700000000;
        $signed = $this->signer->signUrl('/app/page?action=do', $now);
        $result = $this->signer->verifySignedUrl($signed, $now);

        self::assertSame('/app/page?action=do', $result);
    }

    #[Test]
    public function verifyRejectsTamperedUrl(): void
    {
        $now = 1700000000;
        $signed = $this->signer->signUrl('/app/page', $now);
        $tampered = str_replace('/app/page', '/evil/page', $signed);

        self::assertFalse($this->signer->verifySignedUrl($tampered, $now));
    }

    #[Test]
    public function verifyRejectsExpiredUrl(): void
    {
        $signTime = 1700000000;
        $signed = $this->signer->signUrl('/app/page', $signTime);

        $expired = $signTime + (31 * 60);
        self::assertFalse($this->signer->verifySignedUrl($signed, $expired));
    }

    #[Test]
    public function verifyAcceptsWithinLifetime(): void
    {
        $signTime = 1700000000;
        $signed = $this->signer->signUrl('/app/page', $signTime);

        $withinLifetime = $signTime + (29 * 60);
        self::assertSame('/app/page', $this->signer->verifySignedUrl($signed, $withinLifetime));
    }

    #[Test]
    public function verifyRejectsNoSignature(): void
    {
        self::assertFalse($this->signer->verifySignedUrl('/app/page'));
    }

    #[Test]
    public function signQueryStringRoundTrip(): void
    {
        $now = 1700000000;
        $signed = $this->signer->signQueryString('action=do&id=5', $now);
        $result = $this->signer->verifySignedQueryString($signed, $now);

        self::assertTrue($result);
    }

    #[Test]
    public function verifyQueryStringRejectsTampered(): void
    {
        $now = 1700000000;
        $signed = $this->signer->signQueryString('action=do&id=5', $now);
        $tampered = str_replace('action=do', 'action=evil', $signed);

        self::assertFalse($this->signer->verifySignedQueryString($tampered, $now));
    }

    #[Test]
    public function verifyQueryStringRejectsExpired(): void
    {
        $signTime = 1700000000;
        $signed = $this->signer->signQueryString('action=do', $signTime);

        $expired = $signTime + (31 * 60);
        self::assertFalse($this->signer->verifySignedQueryString($signed, $expired));
    }

    #[Test]
    public function verifyQueryStringRejectsNoSignature(): void
    {
        self::assertFalse($this->signer->verifySignedQueryString('action=do'));
    }

    #[Test]
    public function customLifetime(): void
    {
        $signer = new HmacUrlSigner($this->secret, 60);
        $signTime = 1700000000;
        $signed = $signer->signUrl('/app/page', $signTime);

        $at59min = $signTime + (59 * 60);
        self::assertSame('/app/page', $signer->verifySignedUrl($signed, $at59min));

        $at61min = $signTime + (61 * 60);
        self::assertFalse($signer->verifySignedUrl($signed, $at61min));
    }

    #[Test]
    public function differentKeysProduceDifferentSignatures(): void
    {
        $signer2 = new HmacUrlSigner('different-key', 30);
        $now = 1700000000;

        $signed1 = $this->signer->signUrl('/app/page', $now);
        $signed2 = $signer2->signUrl('/app/page', $now);

        self::assertNotSame($signed1, $signed2);
    }

    #[Test]
    public function crossKeyVerificationFails(): void
    {
        $signer2 = new HmacUrlSigner('different-key', 30);
        $now = 1700000000;

        $signed = $this->signer->signUrl('/app/page', $now);
        self::assertFalse($signer2->verifySignedUrl($signed, $now));
    }
}
