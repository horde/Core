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
use Horde\Core\Config\State as ConfigState;

/**
 * Retrieves an existing session
 *
 * We honor the relevant configs
 * $conf[session][use_only_cookies]
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class SessionMethod implements Method
{
    /**
     * @var bool
     */
     protected $paramFallback = true;

     /**
     * @var string
     */
     protected $sessionName;

     /**
     * Constructor
     *
     * @param ConfigState $conf The Horde Config (optional)
     */
    public function __construct(ConfigState $conf)
    {
        $config = $conf->toArray();
        if (empty($config['session']['use_only_cookies'])) {
            $this->paramFallback = false;
        }
        $this->sessionName = $config['session']['name'] ?? 'horde';
    }

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
        $cookies = $request->getCookieVars();
        if (!empty($cookies[$this->sessionName])) {
            $credentials->set('session', $cookies[$this->sessionName]);
        }
        if ($this->paramFallback) {
            // TODO: What's the name of the GET session parameter?
        }
        return $credentials;
    }
}