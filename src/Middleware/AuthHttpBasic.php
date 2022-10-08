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
use Horde_Auth_Base;
use Horde_Core_Auth_Application;

/**
 * AuthHttpHeader middleware
 *
 * Purpose: Authenticate using HTTP Basic header
 *
 * Use Basic Authentication against a Horde_Auth_Base implementation
 *
 * The factory will inject Horde's configured auth driver
 *
 * Sets Attributes:
 * - HORDE_AUTHENTICATED_USER the uid, if authenticated
 *
 */
class AuthHttpBasic implements MiddlewareInterface
{
    private $driver;
    private Horde_Registry $registry;
    /**
     * Constructor
     *
     * TODO: We need to type the driver or register a factory
     *
     * @param object $driver
     */
    public function __construct(Horde_Auth_Base $driver, Horde_Registry $registry)
    {
        $this->driver = $driver;
        $this->registry = $registry;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if request HAS an auth header
        if (!$request->hasHeader('Authorization')) {
            $request = $request->withAttribute('NO_AUTH_HEADER', $this->registry->getAuth());
            return $handler->handle($request);
        }
        $headerValues = $request->getHeader('Authorization');
        foreach ($headerValues as $headerValue) {
            // Ignore headers other than BASIC
            $authScheme = strtoupper(substr($headerValue, 0, 5));
            if ($authScheme !== 'BASIC') {
                continue;
            }
            $userPassword = base64_decode(substr($headerValue, 6));
            $parts = explode(':', $userPassword, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$user, $password] = $parts;
            // Check credentials
            if ($this->driver->authenticate($user, ['password' => $password])) {
                $request = $request->withAttribute('HORDE_AUTHENTICATED_USER', $user);
                return $handler->handle($request);
            }
        }
        return $handler->handle($request);
    }
}
