<?php

declare(strict_types=1);

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Config\RegistryState;
use Horde\Core\Middleware\AppFinder;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AppFinder::class)]
class AppFinderTest extends TestCase
{
    use SetUpTrait;

    private function buildState(array $apps, string $baseUrl): RegistryState
    {
        $definitions = [];
        foreach ($apps as $app => $extra) {
            if (is_int($app)) {
                $app = $extra;
                $extra = [];
            }
            $definitions[$app] = array_merge([
                'status' => 'active',
                'webroot' => $baseUrl . $app,
            ], $extra);
        }
        return new RegistryState($definitions);
    }

    protected function getMiddleware(RegistryState $state): AppFinder
    {
        return new AppFinder(
            $state,
            new ResponseFactory(),
            new StreamFactory()
        );
    }

    public function testAppFound()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            $baseUrl
        );
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'bar');

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('bar', $this->recentlyHandledRequest->getAttribute('app'));
    }

    public function testNoValidAppInPath()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            $baseUrl
        );
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'amount');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testLongestMatchPath()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState(['foobar', 'foo'], $baseUrl);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'foobar');

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('foobar', $this->recentlyHandledRequest->getAttribute('app'));
    }

    public function testNoAppAvailable()
    {
        $state = new RegistryState([]);
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.ex/amount');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRouterPrefixAttribute()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            $baseUrl
        );
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'barfoo');

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('/barfoo', $this->recentlyHandledRequest->getAttribute('routerPrefix'));
    }

    public function testDifferntScheme()
    {
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            'http://example.ex/'
        );
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.ex/bar');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDifferentHost()
    {
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            'http://test.ex/'
        );
        $request = $this->requestFactory->createServerRequest('GET', 'https://example.ex/bar');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEmptyPath()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState(
            ['foobar', 'bla', 'foo', 'barfoo', 'bar'],
            $baseUrl
        );
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl);

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testFindAppBehindDifferentApp()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState([
            'foobar',
            'foo',
            'barfoo',
            'bar',
            'bla' => ['webroot' => $baseUrl . 'foo/bla'],
        ], $baseUrl);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'foo/bla/xyz');

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('bla', $this->recentlyHandledRequest->getAttribute('app'));
    }

    public function testFindAppInDocRoot()
    {
        $baseUrl = 'https://example.ex/';
        $state = $this->buildState([
            'foobar',
            'foo',
            'barfoo',
            'bar',
            'bla' => ['webroot' => $baseUrl],
        ], $baseUrl);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl);

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('bla', $this->recentlyHandledRequest->getAttribute('app'));
    }

    public function testFindWebrootAlias()
    {
        $baseUrl = 'https://example.ex/';
        $state = new RegistryState([
            'foobar' => ['status' => 'active', 'webroot' => $baseUrl . 'foobar'],
            'bar' => [
                'status' => 'active',
                'webroot' => $baseUrl . 'bar',
                'webroot_aliases' => [$baseUrl . '/barV2'],
            ],
        ]);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'barV2');

        $middleware = $this->getMiddleware($state);
        $middleware->process($request, $this->handler);

        $this->assertSame('bar', $this->recentlyHandledRequest->getAttribute('app'));
    }

    public function testDoNotFindWithoutAlias()
    {
        $baseUrl = 'https://example.ex/';
        $state = new RegistryState([
            'foobar' => ['status' => 'active', 'webroot' => $baseUrl . 'foobar'],
            'bar' => ['status' => 'active', 'webroot' => $baseUrl . 'bar'],
        ]);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'barV2');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testInactiveAppsAreSkipped()
    {
        $baseUrl = 'https://example.ex/';
        $state = new RegistryState([
            'foo' => ['status' => 'active', 'webroot' => $baseUrl . 'foo'],
            'bar' => ['status' => 'inactive', 'webroot' => $baseUrl . 'bar'],
        ]);
        $request = $this->requestFactory->createServerRequest('GET', $baseUrl . 'bar');

        $middleware = $this->getMiddleware($state);
        $response = $middleware->process($request, $this->handler);
        $this->assertSame(404, $response->getStatusCode());
    }
}
