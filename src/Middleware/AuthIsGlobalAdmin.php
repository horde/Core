<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthIsGlobalAdmin middleware
 *
 * Sets the HORDE_GLOBAL_ADMIN request attribute when the authenticated
 * user is in the admin list.  Relies on HORDE_AUTHENTICATED_USER being
 * set by an earlier auth middleware (AuthHordeSession, AuthHttpBasic).
 */
class AuthIsGlobalAdmin implements MiddlewareInterface
{
    /** @param string[] $admins Admin usernames from conf[auth][admins] */
    public function __construct(
        private readonly array $admins,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        if ($user !== null && in_array($user, $this->admins, true)) {
            $request = $request->withAttribute('HORDE_GLOBAL_ADMIN', true);
        } else {
            $request = $request->withoutAttribute('HORDE_GLOBAL_ADMIN');
        }
        return $handler->handle($request);
    }
}
