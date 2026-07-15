<?php

declare(strict_types=1);

namespace Horde\Core\Api;

use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;
use InvalidArgumentException;
use RuntimeException;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
class ApiRegistry implements ApiProvider, MethodInvoker
{
    /** @var array<string, ApiProvider&MethodInvoker> */
    private array $providers = [];

    /** @var array<string, array<string, ApiProvider&MethodInvoker>> */
    private array $appProviders = [];

    /**
     * @param ApiProvider&MethodInvoker $provider
     */
    public function registerProvider(
        string $interface,
        ApiProvider&MethodInvoker $provider,
        ?string $app = null,
    ): void {
        $this->providers[$interface] = $provider;
        if ($app !== null) {
            $this->appProviders[$app][$interface] = $provider;
        }
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        $parts = $this->splitMethod($method);
        if ($parts === null) {
            return false;
        }
        [$interface, $localMethod] = $parts;
        $provider = $this->providers[$interface] ?? null;
        if ($provider === null) {
            return false;
        }

        return $provider->hasMethod($localMethod, $context);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        $parts = $this->splitMethod($method);
        if ($parts === null) {
            return null;
        }
        [$interface, $localMethod] = $parts;
        $provider = $this->providers[$interface] ?? null;
        if ($provider === null) {
            return null;
        }
        $descriptor = $provider->getMethodDescriptor($localMethod, $context);
        if ($descriptor === null) {
            return null;
        }

        return $this->prefixDescriptor($interface, $descriptor);
    }

    /**
     * @return list<MethodDescriptor>
     */
    public function listMethods(?ApiCallContext $context = null): array
    {
        $all = [];
        foreach ($this->providers as $interface => $provider) {
            foreach ($provider->listMethods($context) as $descriptor) {
                $all[] = $this->prefixDescriptor($interface, $descriptor);
            }
        }

        return $all;
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        $parts = $this->splitMethod($method);
        if ($parts === null) {
            throw new InvalidArgumentException(
                sprintf('Method string must contain a dot separator: "%s"', $method)
            );
        }
        [$interface, $localMethod] = $parts;
        $provider = $this->providers[$interface] ?? null;
        if ($provider === null) {
            throw new RuntimeException(
                sprintf('No provider registered for interface "%s"', $interface)
            );
        }

        return $provider->invoke($localMethod, $params, $context);
    }

    public function invokeExplicit(
        string $app,
        string $interface,
        string $method,
        array $params,
        ?ApiCallContext $context = null,
    ): Result {
        $provider = $this->appProviders[$app][$interface] ?? null;
        if ($provider === null) {
            throw new RuntimeException(
                sprintf('No provider registered for app "%s" interface "%s"', $app, $interface)
            );
        }

        return $provider->invoke($method, $params, $context);
    }

    /**
     * @return list<string>
     */
    public function getInterfaces(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @return (ApiProvider&MethodInvoker)|null
     */
    public function getProviderForInterface(string $interface): ApiProvider|MethodInvoker|null
    {
        return $this->providers[$interface] ?? null;
    }

    /**
     * Re-wrap a provider-local descriptor with the interface-prefixed name,
     * preserving all other metadata (including the JSON schemas needed by
     * MCP tool listings).
     */
    private function prefixDescriptor(string $interface, MethodDescriptor $descriptor): MethodDescriptor
    {
        return new MethodDescriptor(
            name: $interface . '.' . $descriptor->name,
            description: $descriptor->description,
            parameters: $descriptor->parameters,
            returnType: $descriptor->returnType,
            inputSchema: $descriptor->inputSchema,
            outputSchema: $descriptor->outputSchema,
            permissions: $descriptor->permissions,
        );
    }

    /**
     * @return array{string, string}|null
     */
    private function splitMethod(string $method): ?array
    {
        $dotPos = strpos($method, '.');
        if ($dotPos === false) {
            return null;
        }

        return [substr($method, 0, $dotPos), substr($method, $dotPos + 1)];
    }
}
