<?php

declare(strict_types=1);

namespace Horde\Core\Config;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Vhost
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Vhost::class)]
class VhostTest extends TestCase
{
    public function testExplicitHostname(): void
    {
        $vhost = new Vhost('example.com');

        $this->assertEquals('example.com', $vhost->getHostname());
        $this->assertTrue($vhost->isAvailable());
    }

    public function testNullHostname(): void
    {
        // Save original $_SERVER values
        $originalServerName = $_SERVER['SERVER_NAME'] ?? null;
        $originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;

        // Clear $_SERVER
        unset($_SERVER['SERVER_NAME']);
        unset($_SERVER['HTTP_HOST']);

        $vhost = new Vhost(null);

        $this->assertNull($vhost->getHostname());
        $this->assertFalse($vhost->isAvailable());

        // Restore $_SERVER
        if ($originalServerName !== null) {
            $_SERVER['SERVER_NAME'] = $originalServerName;
        }
        if ($originalHttpHost !== null) {
            $_SERVER['HTTP_HOST'] = $originalHttpHost;
        }
    }

    public function testAutoDetectServerName(): void
    {
        // Save original
        $originalServerName = $_SERVER['SERVER_NAME'] ?? null;

        $_SERVER['SERVER_NAME'] = 'mail.example.com';

        $vhost = new Vhost(null);

        $this->assertEquals('mail.example.com', $vhost->getHostname());
        $this->assertTrue($vhost->isAvailable());

        // Restore
        if ($originalServerName !== null) {
            $_SERVER['SERVER_NAME'] = $originalServerName;
        } else {
            unset($_SERVER['SERVER_NAME']);
        }
    }

    public function testAutoDetectHttpHost(): void
    {
        // Save original
        $originalServerName = $_SERVER['SERVER_NAME'] ?? null;
        $originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;

        // Clear SERVER_NAME, set HTTP_HOST
        unset($_SERVER['SERVER_NAME']);
        $_SERVER['HTTP_HOST'] = 'web.example.com';

        $vhost = new Vhost(null);

        $this->assertEquals('web.example.com', $vhost->getHostname());
        $this->assertTrue($vhost->isAvailable());

        // Restore
        if ($originalServerName !== null) {
            $_SERVER['SERVER_NAME'] = $originalServerName;
        }
        if ($originalHttpHost !== null) {
            $_SERVER['HTTP_HOST'] = $originalHttpHost;
        } else {
            unset($_SERVER['HTTP_HOST']);
        }
    }

    public function testGetVhostFilename(): void
    {
        $vhost = new Vhost('example.com');

        $this->assertEquals('conf-example.com.php', $vhost->getVhostFilename('conf.php'));
        $this->assertEquals('backends-example.com.php', $vhost->getVhostFilename('backends.php'));
        $this->assertEquals('prefs-example.com.local.php', $vhost->getVhostFilename('prefs.local.php'));
    }

    public function testGetVhostFilenameWhenNotAvailable(): void
    {
        $vhost = new Vhost(null);

        $this->assertNull($vhost->getVhostFilename('conf.php'));
    }

    public function testFromVhostObject(): void
    {
        $original = new Vhost('example.com');
        $result = Vhost::from($original);

        $this->assertSame($original, $result);
    }

    public function testFromString(): void
    {
        $vhost = Vhost::from('example.com');

        $this->assertInstanceOf(Vhost::class, $vhost);
        $this->assertEquals('example.com', $vhost->getHostname());
    }

    public function testDefaultConstructorValue(): void
    {
        // Test that default parameter works
        $loader = function (Vhost|string $vhost = 'localhost') {
            return Vhost::from($vhost);
        };

        $vhost = $loader();

        $this->assertEquals('localhost', $vhost->getHostname());
    }
}
