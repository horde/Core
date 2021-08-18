<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;
use \Horde_Application;
use Horde_Controller;
use Horde_Routes_Mapper as Router;
use \Horde_String;
use \Horde;
use Horde\Core\UserPassport;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * RedirectToLogin middleware
 *
 * Purpose: Redirect to login if not authenticated
 * 
 * Reads attribute: 
 * - HORDE_AUTHENTICATED_USER the uid, if authenticated
 * 
 */
class RedirectToLogin implements MiddlewareInterface
{
    private Horde_Registry $registry;
    private ResponseFactoryInterface $responseFactory;
    public function __construct(Horde_Registry $registry, ResponseFactoryInterface $responseFactory)
    {
        $this->registry = $registry;
        $this->responseFactory = $responseFactory;
    }
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('HORDE_AUTHENTICATED_USER')) {
            return $handler->handle($request);
        }
        $redirect = (string)Horde::Url($this->registry->getInitialPage('horde'), true);
        return $this->responseFactory->createResponse(302)->withHeader('Location', $redirect);
    }
}
