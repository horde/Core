<?php
/**
 * Copyright 2019-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

/**
 * The Horde_Core_Controller_NotAuthorized class provides 
 * a premade controller for scenarios where a requester
 * has not provided sufficient authentication to access
 * a resource
 *
 * Copyright 2019-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @package  Package
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class Horde_Core_Controller_NotAuthorized implements Horde_Controller
{
    /**
     */
    public function processRequest(Horde_Controller_Request $request,
                                   Horde_Controller_Response $response)
    {
        $response->setHeader('HTTP/1.0 401 ', 'Not Authorized');
        $response->setBody('<!DOCTYPE html><html><head><title>401 Not Authorized</title></head><body><h1>401 Not Authorized</h1></body></html>');
    }
}
