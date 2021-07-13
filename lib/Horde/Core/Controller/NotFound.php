<?php
/**
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

/**
 * The Horde_Core_Controller_NotFound class provides 
 * a premade controller for scenarios where a resource
 * either does not exist or is not visible to the requester
 *
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @package  Package
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class Horde_Core_Controller_NotFound implements Horde_Controller
{
    /**
     */
    public function processRequest(Horde_Controller_Request $request,
                                   Horde_Controller_Response $response)
    {
        $response->setHeader('HTTP/1.0 404 ', 'Not Found');
        $response->setBody('<!DOCTYPE html><html><head><title>404 File Not Found</title></head><body><h1>404 File Not Found</h1></body></html>');
    }
}
