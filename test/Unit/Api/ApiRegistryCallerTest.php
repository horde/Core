<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Api;

use Horde\Core\Api\ApiRegistry;
use Horde\Core\Api\ApiRegistryCaller;
use Horde\Core\Api\ApiRegistryCallerDecorator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiRegistryCaller::class)]
#[CoversClass(ApiRegistryCallerDecorator::class)]
class ApiRegistryCallerTest extends TestCase
{
    public function testCallDelegatesToRegistry(): void
    {
        $provider = new StubProvider(['export' => fn(string $format) => 'ical:' . $format]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $caller = new ApiRegistryCaller($registry, 'calendar');
        $result = $caller->export('vcal');

        $this->assertSame('ical:vcal', $result);
    }

    public function testDecoratorReturnsCallerForInterface(): void
    {
        $provider = new StubProvider(['list' => fn() => ['event1']]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $decorator = new ApiRegistryCallerDecorator($registry);
        $result = $decorator->calendar->list();

        $this->assertSame(['event1'], $result);
    }
}
