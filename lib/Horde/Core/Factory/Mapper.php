<?php

use Horde\Injector\Injector;
use Horde\Routes\Mapper;

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Mapper extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        return new Mapper();
    }

}
