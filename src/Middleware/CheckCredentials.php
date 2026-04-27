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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Middleware;

use Horde\Core\Auth\CredentialCheckResult;
use Horde\Core\Factory\CheckCredentialsFactory;
use Horde\Injector\Attribute\Factory;
use Horde_Core_Auth_Application;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validate HTTP Basic credentials without establishing a session.
 *
 * On success sets HORDE_VERIFIED_USER (distinct from
 * HORDE_AUTHENTICATED_USER which implies an active session).
 * Always sets HORDE_CREDENTIAL_CHECK with the typed result.
 */
#[Factory(factory: CheckCredentialsFactory::class, method: 'create')]
class CheckCredentials implements MiddlewareInterface
{
    public function __construct(
        private Horde_Core_Auth_Application $auth,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$request->hasHeader('Authorization')) {
            return $handler->handle($request);
        }

        foreach ($request->getHeader('Authorization') as $headerValue) {
            if (strtoupper(substr($headerValue, 0, 5)) !== 'BASIC') {
                continue;
            }

            $decoded = base64_decode(substr($headerValue, 6), true);
            if ($decoded === false) {
                continue;
            }

            $parts = explode(':', $decoded, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$user, $password] = $parts;

            $result = $this->auth->checkCredentials($user, [
                'password' => $password,
            ]);

            $request = $request->withAttribute('HORDE_CREDENTIAL_CHECK', $result);

            if ($result === CredentialCheckResult::Valid) {
                $request = $request->withAttribute('HORDE_VERIFIED_USER', $user);
            }

            return $handler->handle($request);
        }

        return $handler->handle($request);
    }
}
