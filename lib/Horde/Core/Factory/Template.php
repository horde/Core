<?php
use Horde\Injector\Injector;

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Template extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        return new Horde_Template([
            'cacheob' => $injector->getInstance('Horde_Cache'),
            'logger' => $injector->getInstance('Horde_Log_Logger'),
        ]);
    }

}
