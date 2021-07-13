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
 * An interface for an authentication method
 *
 * A method describes how the authentication information is provided
 * - A session cookie
 * - A basic auth header
 * - A digest auth header
 * - A custom header
 * - A GET request parameter
 * - A POST form field
 * - A POST/PUT body containing a valid JSON document with a certain key/value
 * - A POST/PUT body containing a valid XML document with a certain key/value
 *
 * An AuthMethod does not care about validating this credential.
 * This is up to an AuthSource.
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
interface Method
{
    /**
     * Get authentication credentials from a request
     *
     * @param Request An authentication request
     *
     * @return Credentials A credentials object
     */
    public function getCredentials(Request $request): Credentials;
}