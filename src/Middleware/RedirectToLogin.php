<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Horde\Core\Config\State;
use Horde\Core\Horde;
use Horde\Core\Service\PermissionService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;

/**
 * RedirectToLogin middleware
 *
 * Purpose: Redirect to login if not authenticated or if the user lacks
 * top level read permission on the target app.
 *
 * Reads attribute:
 * - HORDE_AUTHENTICATED_USER the uid, if authenticated
 * - app the target application name
 *
 */
class RedirectToLogin implements MiddlewareInterface
{
    private State $conf;
    private Horde_Registry $registry;
    private ResponseFactoryInterface $responseFactory;
    private ?PermissionService $permissionService;

    public function __construct(
        Horde_Registry $registry,
        ResponseFactoryInterface $responseFactory,
        State $conf,
        ?PermissionService $permissionService = null,
    ) {
        $this->registry = $registry;
        $this->responseFactory = $responseFactory;
        $this->conf = $conf;
        $this->permissionService = $permissionService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $app = $request->getAttribute('app');

        // Admins bypass all permission checks
        if ($user && $this->registry->isAdmin(['user' => $user])) {
            return $handler->handle($request);
        }

        // Check app-level read permission if PermissionService is available
        if ($this->permissionService !== null && $app) {
            if ($this->permissionService->exists($app)) {
                $checkUser = $user ?: '';
                if (!$this->permissionService->hasPermission($app, $checkUser, ['read'])) {
                    return $this->redirectToLogin($request);
                }
                return $handler->handle($request);
            }
        }

        // No permission defined or no service — fall back to authentication check
        if ($user) {
            return $handler->handle($request);
        }

        return $this->redirectToLogin($request);
    }

    private function redirectToLogin(ServerRequestInterface $request): ResponseInterface
    {
        $requestUrl = (string) $request->getUri();
        $signedRequestUrl = Horde::signUrl($requestUrl);

        $configArray = $this->conf->toArray();
        $alternateLogin = $configArray['auth']['alternate_login'] ?? null;

        if (!empty($alternateLogin)) {
            $baseUrl = $alternateLogin;
        } else {
            $baseUrl = $this->registry->getServiceLink('login');
        }

        $redirectUrl = (string) Horde::url($baseUrl, true)->add('url', $signedRequestUrl);

        return $this->responseFactory->createResponse(302)->withHeader('Location', $redirectUrl);
    }
}
