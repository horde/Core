<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde\Core\Factory\OAuthHttpClientServiceFactory;
use Horde\Core\Service\NullOAuthHttpClientService;
use Horde\Core\Service\OAuthHttpClientService;
use Horde_Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthHttpClientServiceFactory::class)]
final class OAuthHttpClientServiceFactoryTest extends TestCase
{
    public function testCreateReturnsNullImplementation(): void
    {
        $injector = $this->createMock(Horde_Injector::class);
        $factory = new OAuthHttpClientServiceFactory();

        $service = $factory->create($injector);

        self::assertInstanceOf(OAuthHttpClientService::class, $service);
        self::assertInstanceOf(NullOAuthHttpClientService::class, $service);
    }
}
