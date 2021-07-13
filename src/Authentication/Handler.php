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
 * Interface for controllers handling their own authentication
 *
 * The Controller Framework will automatically perform 
 * existing session auth unless this interface is present.
 *
 * The Framework will not perform any default authentication
 * if this interface is present.
 *
 * The controller may even allow unauthenticated access or multiple methods.
 */
interface Handler
{
    /**
     * Perform authentication in any way.
     *
     * Return null or void if processing should continue.
     * Return a Response to prevent controller access.
     *
     * @param Request $request The request to authenticate
     *
     * @return Response|null If we return a response, stop further processing
     */
    public function authenticate(Request $request):? Response;
}