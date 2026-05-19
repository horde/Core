<?php
use Horde\Injector\Injector;

use Horde\Routes\Mapper;
use Horde\Routes\Matcher;

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Matcher extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        return new Matcher(
            $injector->getInstance(Mapper::class),
            $injector->getInstance('Horde_Controller_Request')
        );
    }
}
