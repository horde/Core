<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;
use Horde_Application;
use Horde_Controller;
use Horde_Routes_Mapper as Router;
use Horde_String;
use Horde;

/**
 * AuthHordeSession middleware
 *
 * Purpose: Identify the session as either user or a guest
 *
 *
 *
 * Sets Attributes:
 * - HORDE_AUTHENTICATED_USER the uid, if authenticated
 * - HORDE_GUEST true if not authenticated
 *
 */
class AuthHordeSession implements MiddlewareInterface
{
    private Horde_Registry $registry;
    public function __construct(Horde_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->registry->isAuthenticated()) {
            $request = $request->withAttribute('HORDE_AUTHENTICATED_USER', $this->registry->getAuth());
            $request = $request->withoutAttribute('HORDE_GUEST');
        } else {
            $request = $request->withAttribute('HORDE_GUEST', true);
            $request = $request->withoutAttribute('HORDE_AUTHENTICATED_USER');
        }
        return $handler->handle($request);
    }
}
