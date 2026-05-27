<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Uri;

use Horde\Core\Uri\PortUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PortUtil::class)]
class PortUtilTest extends TestCase
{
    /**
     * @return array<string, array{string, int|string|null, bool}>
     */
    public static function isIanaDefaultProvider(): array
    {
        return [
            'https 443 is default' => ['https', 443, true],
            'http 80 is default' => ['http', 80, true],
            'https 80 is NOT default' => ['https', 80, false],
            'http 443 is NOT default' => ['http', 443, false],
            'https 8443 is NOT default' => ['https', 8443, false],
            'http 8080 is NOT default' => ['http', 8080, false],
            'null port is default' => ['https', null, true],
            'empty string port is default' => ['http', '', true],
            'string 443 on https' => ['https', '443', true],
            'string 80 on http' => ['http', '80', true],
            'string 443 on http' => ['http', '443', false],
            'ftp 21 unknown scheme' => ['ftp', 21, false],
        ];
    }

    #[Test]
    #[DataProvider('isIanaDefaultProvider')]
    public function isIanaDefault(string $scheme, int|string|null $port, bool $expected): void
    {
        $this->assertSame($expected, PortUtil::isIanaDefault($scheme, $port));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function stripDefaultPortProvider(): array
    {
        return [
            'https host:443 stripped' => ['https', 'example.com:443', 'example.com'],
            'http host:80 stripped' => ['http', 'example.com:80', 'example.com'],
            'https host:80 kept' => ['https', 'example.com:80', 'example.com:80'],
            'http host:443 kept' => ['http', 'example.com:443', 'example.com:443'],
            'https host:8443 kept' => ['https', 'example.com:8443', 'example.com:8443'],
            'no port unchanged' => ['https', 'example.com', 'example.com'],
            'http host:8080 kept' => ['http', 'example.com:8080', 'example.com:8080'],
        ];
    }

    #[Test]
    #[DataProvider('stripDefaultPortProvider')]
    public function stripDefaultPort(string $scheme, string $host, string $expected): void
    {
        $this->assertSame($expected, PortUtil::stripDefaultPort($scheme, $host));
    }
}
