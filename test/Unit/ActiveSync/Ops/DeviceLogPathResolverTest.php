<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\ActiveSync\Ops;

use Horde\Core\ActiveSync\Ops\DeviceLogPathResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Horde\Core\ActiveSync\Ops\DeviceLogPathResolver
 */
final class DeviceLogPathResolverTest extends TestCase
{
    public function testPerDevicePathUsesUppercaseIdAndTrimsSlash(): void
    {
        $resolver = new DeviceLogPathResolver('perdevice', '/var/log/as///');

        self::assertSame(
            '/var/log/as/DEVICE1.txt',
            $resolver->resolve('device1')
        );
    }

    /**
     * @dataProvider disabledConfigurationProvider
     */
    public function testOtherLoggingConfigurationsReturnNull(
        ?string $type,
        ?string $path
    ): void {
        self::assertNull(
            (new DeviceLogPathResolver($type, $path))->resolve('device1')
        );
    }

    public static function disabledConfigurationProvider(): iterable
    {
        yield 'one file' => ['onefile', '/var/log/activesync.log'];
        yield 'false type' => ['false', '/var/log'];
        yield 'null type' => [null, '/var/log'];
        yield 'null path' => ['perdevice', null];
    }
}
