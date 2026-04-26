<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\NullUrlSigner;
use Horde\Core\Assets\UrlSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullUrlSigner::class)]
class NullUrlSignerTest extends TestCase
{
    private NullUrlSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new NullUrlSigner();
    }

    #[Test]
    public function implementsUrlSigner(): void
    {
        self::assertInstanceOf(UrlSigner::class, $this->signer);
    }

    #[Test]
    public function signUrlReturnsUnchanged(): void
    {
        self::assertSame('/app/page?foo=bar', $this->signer->signUrl('/app/page?foo=bar'));
    }

    #[Test]
    public function verifyAlwaysReturnsInput(): void
    {
        self::assertSame('/app/page', $this->signer->verifySignedUrl('/app/page'));
    }

    #[Test]
    public function signQueryStringReturnsUnchanged(): void
    {
        self::assertSame('action=do', $this->signer->signQueryString('action=do'));
    }

    #[Test]
    public function verifyQueryStringAlwaysTrue(): void
    {
        self::assertTrue($this->signer->verifySignedQueryString('anything'));
    }
}
