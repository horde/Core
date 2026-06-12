<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Auth\Jwt;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Core\Auth\Jwt\JwtService;
use Horde\Core\Auth\Jwt\JwtServiceFactory;
use Horde\Injector\Injector;
use InvalidArgumentException;

/**
 * Unit Test: JwtServiceFactory
 *
 * The factory reads its configuration from `$GLOBALS['conf']` and never
 * touches the injector. Each test owns its injector stub locally so
 * the contract is explicit per test rather than implied by setUp.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(JwtServiceFactory::class)]
class JwtServiceFactoryTest extends TestCase
{
    private array $originalConf;
    private array $originalServer;
    private string $testSecretFile;

    protected function setUp(): void
    {
        $this->originalConf = $GLOBALS['conf'] ?? [];
        $this->originalServer = $_SERVER;
        $this->testSecretFile = sys_get_temp_dir() . '/jwt_test_secret_' . uniqid();
    }

    protected function tearDown(): void
    {
        $GLOBALS['conf'] = $this->originalConf;
        $_SERVER = $this->originalServer;
        if (file_exists($this->testSecretFile)) {
            unlink($this->testSecretFile);
        }
    }

    private function createSecretFile(string $content): void
    {
        file_put_contents($this->testSecretFile, $content);
        chmod($this->testSecretFile, 0o600);
    }

    /**
     * Build an injector stub. The factory under test reads
     * `$GLOBALS['conf']` directly and never calls any injector
     * method, so a stub is the truthful descriptor of the test
     * contract.
     */
    private function injectorStub(): Injector
    {
        return $this->createStub(Injector::class);
    }

    public function testCreateReturnsNullWhenJwtNotEnabled(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => false,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertNull($result);
    }

    public function testCreateReturnsNullWhenJwtConfigMissing(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertNull($result);
    }

    public function testCreateReturnsJwtServiceWhenConfigured(): void
    {
        $this->createSecretFile(str_repeat('a', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                    'issuer' => 'test.example.com',
                    'access_ttl' => 1800,
                    'refresh_ttl' => 86400,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultIssuerFromServerName(): void
    {
        $this->createSecretFile(str_repeat('b', 32));

        $_SERVER['SERVER_NAME'] = 'horde.test.com';
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultIssuerWhenServerNameMissing(): void
    {
        $this->createSecretFile(str_repeat('c', 32));

        unset($_SERVER['SERVER_NAME']);
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultTtlValues(): void
    {
        $this->createSecretFile(str_repeat('d', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateThrowsWhenSecretMissing(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => '/nonexistent/path/to/secret',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT is enabled but secret file does not exist');

        $factory = new JwtServiceFactory();
        $factory->create($this->injectorStub());
    }

    public function testCreateThrowsWhenSecretEmpty(): void
    {
        $this->createSecretFile('');

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret file is empty');

        $factory = new JwtServiceFactory();
        $factory->create($this->injectorStub());
    }

    public function testCreateThrowsWhenSecretTooShort(): void
    {
        $this->createSecretFile('short');

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must be at least 256 bits (32 bytes)');

        $factory = new JwtServiceFactory();
        $factory->create($this->injectorStub());
    }

    public function testCreateThrowsWhenSecret31Bytes(): void
    {
        $this->createSecretFile(str_repeat('x', 31));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must be at least 256 bits (32 bytes)');

        $factory = new JwtServiceFactory();
        $factory->create($this->injectorStub());
    }

    public function testCreateSucceedsWithExactly32ByteSecret(): void
    {
        $this->createSecretFile(str_repeat('x', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateSucceedsWithLongerSecret(): void
    {
        $this->createSecretFile(str_repeat('x', 64));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injectorStub());

        $this->assertInstanceOf(JwtService::class, $result);
    }
}
