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
use \Horde_Exception_NotFound as NotFoundException;

/**
 * A set of credentials
 *
 * Most likely, username and password or some token
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
final class Credentials
{
    /**
     * @var array
     */
    protected $credentials = [];

    /**
     * Retrieve a credential
     *
     * @param string $key   The name of the credential
     *
     * @return string The credential content
     *
     * @throws NotFoundException
     */
    public function get(string $key): string
    {
        if (isset($this->credentials[$key])) {
            return $this->credentials[$key];
        }
        throw new NotFoundException(_('Tried to retrieve unset credential: ') . $key);
    }

    /**
     * Set or overwrite a credential
     *
     * @param string $key   The name of the credential
     * @param string $value The content of the credential
     *
     * @return void
     */
    public function set(string $key, string $value): void
    {
        $this->credentials[$key] = $value;
    }
}