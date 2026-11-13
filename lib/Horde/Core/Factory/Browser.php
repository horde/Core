<?php

use Horde\Injector\Injector;

/**
 * @category Horde
 * @package  Core
 * @todo Framework 6.1 migrate to Horde\Browser\Browser or Horde\Browser\HttpUtils
 */
class Horde_Core_Factory_Browser extends Horde_Core_Factory_Injector
{
    /**
     */
    public function create(Horde_Injector|Injector $injector)
    {
        return new Horde_Core_Browser();
    }

}
