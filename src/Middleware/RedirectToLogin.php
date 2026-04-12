<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Horde\Core\Config\State;
use Horde\Core\Horde;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;

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
    private State $conf;
    private Horde_Registry $registry;
    private ResponseFactoryInterface $responseFactory;
    public function __construct(Horde_Registry $registry, ResponseFactoryInterface $responseFactory, State $conf)
    {
        $this->registry = $registry;
        $this->responseFactory = $responseFactory;
        $this->conf = $conf;
    }
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('HORDE_AUTHENTICATED_USER')) {
            return $handler->handle($request);
        }

        $requestUrl = (string) $request->getUri();
        $signedRequestUrl = Horde::signUrl($requestUrl);

        // set baseurl: check if alternative login is set and use it as baseurl
        $configArray = $this->conf->toArray();
        $configArray['auth']['alternate_login'] ?? null;

        if (!empty($alternateLogin)) {
            $baseUrl = $alternateLogin;
        } else {
            // set baseurl: if no alternative login, use Horde login as baseurl
            $baseUrl = $this->registry->getServiceLink('login');
        };

        $redirectUrl = (string) Horde::url($baseUrl, true)->add('url', $signedRequestUrl);

        return $this->responseFactory->createResponse(302)->withHeader('Location', $redirectUrl);
    }
}
