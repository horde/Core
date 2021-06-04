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
/**
 * An interface for an authentication backend
 *
 * A backend takes credentials and verifies them by any method
 *
 * - Horde's configured user auth backend
 * - An application's auth backend
 * - A custom, secondary store of credentials (access tokens etc)
 * - An algorithm checking certificate information
 *
 * A backend does not care about validating this credential.
 * This is up to a Source.
 *
 * A backend is not a driver, it may use a driver.
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
interface Backend
{
    /**
     * Checks if a set of credentials is currently valid
     *
     * This method should not create side effects like 
     * establishing a session or setting a session to authenticated
     *
     * @param Credentials $credentials A set of credentials
     *
     * @return bool True if succeeded
     */
    public function checkCredentials(Credentials $credentials): bool;

    /**
     * Perform authentication
     *
     * This should establish or restore a session
     * and set authentication status accordingly
     *
     * @param Credentials $credentials A set of credentials
     *
     * @return bool True if succeeded
     */
    public function authenticate(Credentials $credentials): bool;
}