<?php
/**
 * Copyright 1999-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
declare(strict_types=1);
namespace Horde\Core\Authentication;
use \Horde_Controller_Request as Request;

/**
 * Gets username/password from a HTTP Basic header
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class BasicMethod implements Method
{
    /**
     * Parse Auth header from a request
     *
     * @param Request An authentication request
     *
     * @return Credentials A credentials object
     */
    public function getCredentials(Request $request): Credentials
    {
        $credentials = new Credentials;
        // We don't rely on globals here but on the request.
        $auth = $request->getHeader('Authorization');
        if (empty($auth)) {
            // This will make the backend throw a NotFoundException
            return $credentials;
        }
        // Split the base64 code from the AuthType
        list($scheme, $base64) = explode(' ', $auth, 2);
        $decoded = base64_decode($base64);
        list($username, $password) = explode(':', $decoded);
        $credentials->set('username', $username);
        $credentials->set('password', $password);
        return $credentials;
    }
}