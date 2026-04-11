<?php

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

namespace Horde\Core\Test\Middleware;

use Horde\Core\Config\State;
use Horde\Core\Middleware\RedirectToLogin;
use Horde\Test\TestCase;

/**
 * @coversNothing
 */
class RedirectToLoginTest extends TestCase
{
    use SetUpTrait;

    protected function getMiddleware()
    {
        return new RedirectToLogin(
            $this->registry,
            $this->responseFactory,
            new State(['auth' => []])
        );
    }

    public function testIsNotRedirectedWhenAuthenticated()
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $request = $request->withAttribute('HORDE_AUTHENTICATED_USER', 'testuser');
        $response = $middleware->process($request, $this->handler);

        // Should pass through to handler
        $this->assertEquals(200, $response->getStatusCode());

        // Verify request reached handler
        $this->assertNotNull($this->recentlyHandledRequest);
        $this->assertEquals('testuser', $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER'));
    }

    // Note: Testing actual redirect and guest user behavior requires global $registry
    // which is used by Horde::Url(). This creates a tight coupling that makes
    // unit testing difficult. These scenarios are better covered by integration tests.
}
