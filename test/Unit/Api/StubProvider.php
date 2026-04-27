<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Api;

use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProviderInterface;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvokerInterface;
use Horde\Rpc\Dispatch\Result;

class StubProvider implements ApiProviderInterface, MethodInvokerInterface
{
    /** @var array<string, callable> */
    private array $methods;

    /** @var array<string, MethodDescriptor> */
    private array $descriptors;

    public ?ApiCallContext $lastContext = null;

    /**
     * @param array<string, callable> $methods
     * @param array<string, MethodDescriptor> $descriptors
     */
    public function __construct(array $methods = [], array $descriptors = [])
    {
        $this->methods = $methods;
        $this->descriptors = $descriptors;
        foreach ($methods as $name => $callable) {
            if (!isset($this->descriptors[$name])) {
                $this->descriptors[$name] = new MethodDescriptor($name);
            }
        }
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        $this->lastContext = $context;

        return isset($this->methods[$method]);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        $this->lastContext = $context;

        return $this->descriptors[$method] ?? null;
    }

    /**
     * @return list<MethodDescriptor>
     */
    public function listMethods(?ApiCallContext $context = null): array
    {
        $this->lastContext = $context;

        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        $this->lastContext = $context;

        return new Result(($this->methods[$method])(...$params));
    }
}
