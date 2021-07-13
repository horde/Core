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
 * Check for a valid session
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class HordeSessionBackend implements Backend
{

    /**
     * @var string[]
     */
    protected $expectedKeys = [];

    /**
     * @var
     */
    protected $expected = [];

    /**
     * Constructor
     *
     * Sets up a list of expected keys
     *
     * @param string[] The name of credentials to check for
     * @param Credentials[] An array of Credentials objects to accept
     */
    public function __construct(
        array $expectedKeys = [],
        array $credentials = []
    )
    {
        $this->expectedKeys = $expectedKeys;
        $this->expected = $credentials;
    }

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
    public function checkCredentials(Credentials $credentials): bool
    {
        foreach ($this->expected as $valid) {
            foreach ($this->expectedKeys as $key) {
                if ($credentials->get($key) != $valid->get($key)) {
                    break;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * The mock backend currently does NOT really do authentication
     *
     * For integration tests, we should establish some mock session
     *
     * @param Credentials $credentials A set of credentials
     *
     * @return bool True if succeeded
     */
    public function authenticate(Credentials $credentials): bool
    {
        return $this->checkCredentials($credentials);
    }
}