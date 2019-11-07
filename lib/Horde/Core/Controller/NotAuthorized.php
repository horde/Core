<?php
/**
 * @category Horde
 * @package  Core
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
