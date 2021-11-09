<?php
namespace Horde\Core\Mock;
use \Horde_Core_ActiveSync_Connector;
/**
 * Mock Connector. Can't mock it since it contain type hints for objects from
 * other libraries (which causes PHPUnit to have a fit).
 *
 */
class MockConnector extends Horde_Core_ActiveSync_Connector
{
    public function __construct()
    {
    }

    public function horde_listApis()
    {
        return array('mail');
    }

}
