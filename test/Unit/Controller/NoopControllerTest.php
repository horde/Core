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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Controller;

use Horde\Core\Controller\NoopController;
use Horde\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoopController::class)]
final class NoopControllerTest extends TestCase
{
    #[Test]
    public function returns204NoContent(): void
    {
        $controller = new NoopController();
        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $controller->handle($request);

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function returnsEmptyBody(): void
    {
        $controller = new NoopController();
        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $controller->handle($request);

        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function ignoresRequestEntirely(): void
    {
        // The controller must produce the same response regardless of
        // request method, body, headers, or attributes. Mounted at
        // arbitrary routes, it has no domain logic.
        $controller = new NoopController();

        $getRequest = new ServerRequest('GET', 'http://localhost/health');
        $postRequest = (new ServerRequest('POST', 'http://localhost/csrf-token'))
            ->withHeader('X-Csrf-Token', 'arbitrary')
            ->withAttribute('session', 'arbitrary');

        $getResp = $controller->handle($getRequest);
        $postResp = $controller->handle($postRequest);

        self::assertSame(204, $getResp->getStatusCode());
        self::assertSame(204, $postResp->getStatusCode());
        self::assertSame('', (string) $getResp->getBody());
        self::assertSame('', (string) $postResp->getBody());
    }
}
