<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Api;

use Horde\Core\Api\ApiRegistry;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\MethodDescriptor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ApiRegistry::class)]
class ApiRegistryTest extends TestCase
{
    private function makeProvider(array $methods = [], array $descriptors = []): StubProvider
    {
        return new StubProvider($methods, $descriptors);
    }

    public function testRegisterAndHasMethod(): void
    {
        $provider = $this->makeProvider(['list' => fn() => []]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $this->assertTrue($registry->hasMethod('calendar.list'));
        $this->assertFalse($registry->hasMethod('calendar.nope'));
    }

    public function testInvokeDelegate(): void
    {
        $provider = $this->makeProvider(['add' => fn(float $a, float $b) => $a + $b]);
        $registry = new ApiRegistry();
        $registry->registerProvider('math', $provider);

        $result = $registry->invoke('math.add', [3.0, 4.0]);

        $this->assertSame(7.0, $result->value);
    }

    public function testInvokeExplicit(): void
    {
        $provider = $this->makeProvider(['list' => fn() => ['task1']]);
        $registry = new ApiRegistry();
        $registry->registerProvider('tasks', $provider, 'nag');

        $result = $registry->invokeExplicit('nag', 'tasks', 'list', []);

        $this->assertSame(['task1'], $result->value);
    }

    public function testListMethodsPrefixesWithInterface(): void
    {
        $provider = $this->makeProvider([
            'list' => fn() => [],
            'export' => fn() => '',
        ]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $methods = $registry->listMethods();
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);

        $this->assertCount(2, $methods);
        $this->assertContains('calendar.list', $names);
        $this->assertContains('calendar.export', $names);
    }

    public function testGetMethodDescriptorPrefixed(): void
    {
        $provider = $this->makeProvider(
            ['list' => fn() => []],
            ['list' => new MethodDescriptor('list', description: 'List items')],
        );
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $desc = $registry->getMethodDescriptor('calendar.list');

        $this->assertNotNull($desc);
        $this->assertSame('calendar.list', $desc->name);
        $this->assertSame('List items', $desc->description);
    }

    public function testDescriptorSchemasPreserved(): void
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => [
                'pagename' => ['type' => 'string', 'description' => 'Page to edit'],
            ],
            'required' => ['pagename'],
        ];
        $outputSchema = ['type' => 'object'];
        $provider = $this->makeProvider(
            ['edit' => fn() => null],
            ['edit' => new MethodDescriptor(
                'edit',
                description: 'Edit a page',
                inputSchema: $inputSchema,
                outputSchema: $outputSchema,
            )],
        );
        $registry = new ApiRegistry();
        $registry->registerProvider('wiki', $provider);

        $desc = $registry->getMethodDescriptor('wiki.edit');

        $this->assertNotNull($desc);
        $this->assertSame($inputSchema, $desc->inputSchema);
        $this->assertSame($outputSchema, $desc->outputSchema);

        $listed = $registry->listMethods();

        $this->assertCount(1, $listed);
        $this->assertSame('wiki.edit', $listed[0]->name);
        $this->assertSame($inputSchema, $listed[0]->inputSchema);
        $this->assertSame($outputSchema, $listed[0]->outputSchema);
    }

    public function testUnknownInterfaceHasMethodReturnsFalse(): void
    {
        $registry = new ApiRegistry();

        $this->assertFalse($registry->hasMethod('nope.method'));
    }

    public function testInvokeMalformedMethodThrows(): void
    {
        $registry = new ApiRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->invoke('nodot', []);
    }

    public function testInvokeUnknownInterfaceThrows(): void
    {
        $registry = new ApiRegistry();

        $this->expectException(RuntimeException::class);
        $registry->invoke('unknown.method', []);
    }

    public function testInvokeExplicitUnknownAppThrows(): void
    {
        $registry = new ApiRegistry();

        $this->expectException(RuntimeException::class);
        $registry->invokeExplicit('badapp', 'tasks', 'list', []);
    }

    public function testContextPassedThrough(): void
    {
        $provider = $this->makeProvider(['list' => fn() => []]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $context = new ApiCallContext(['protocol' => 'jsonrpc']);
        $registry->invoke('calendar.list', [], $context);

        $this->assertSame($context, $provider->lastContext);
    }

    public function testMultipleInterfacesAggregated(): void
    {
        $calProvider = $this->makeProvider(['list' => fn() => []]);
        $taskProvider = $this->makeProvider(['list' => fn() => [], 'add' => fn() => null]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $calProvider);
        $registry->registerProvider('tasks', $taskProvider);

        $methods = $registry->listMethods();
        $names = array_map(fn(MethodDescriptor $d) => $d->name, $methods);

        $this->assertCount(3, $methods);
        $this->assertContains('calendar.list', $names);
        $this->assertContains('tasks.list', $names);
        $this->assertContains('tasks.add', $names);
    }

    public function testGetInterfaces(): void
    {
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $this->makeProvider(['list' => fn() => []]));
        $registry->registerProvider('tasks', $this->makeProvider(['list' => fn() => []]));

        $interfaces = $registry->getInterfaces();

        $this->assertSame(['calendar', 'tasks'], $interfaces);
    }

    public function testGetProviderForInterface(): void
    {
        $provider = $this->makeProvider(['list' => fn() => []]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $this->assertSame($provider, $registry->getProviderForInterface('calendar'));
        $this->assertNull($registry->getProviderForInterface('unknown'));
    }

    public function testHasMethodNoDotReturnsFalse(): void
    {
        $registry = new ApiRegistry();

        $this->assertFalse($registry->hasMethod('nodot'));
    }

    public function testGetMethodDescriptorNoDotReturnsNull(): void
    {
        $registry = new ApiRegistry();

        $this->assertNull($registry->getMethodDescriptor('nodot'));
    }

    public function testGetMethodDescriptorUnknownInterfaceReturnsNull(): void
    {
        $registry = new ApiRegistry();

        $this->assertNull($registry->getMethodDescriptor('unknown.method'));
    }

    public function testGetMethodDescriptorUnknownMethodReturnsNull(): void
    {
        $provider = $this->makeProvider(['list' => fn() => []]);
        $registry = new ApiRegistry();
        $registry->registerProvider('calendar', $provider);

        $this->assertNull($registry->getMethodDescriptor('calendar.nope'));
    }
}
