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
use Horde\Core\UserPassport;

/**
 * AuthIsGlobalAdmin middleware
 *
 * Purpose: Identify the session as global admin
 *
 * Sets Attributes:
 * - HORDE_GLOBAL_ADMIN if the user has that privilege
 *
 */
class AuthIsGlobalAdmin implements MiddlewareInterface
{
    private Horde_Registry $registry;
    public function __construct(Horde_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->registry->isAuthenticated() && $this->registry->isAdmin()) {
            $request = $request->withAttribute('HORDE_GLOBAL_ADMIN', true);
        } else {
            $request = $request->withoutAttribute('HORDE_GLOBAL_ADMIN');
        }
        return $handler->handle($request);
    }
}
