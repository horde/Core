<?php
/**
 * Copyright 2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Stub;

use \Horde_Registry_Registryconfig;

/**
 * Wrapper around Horde_Registry to get access to _detectWebroot().
 *
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class Registryconfig extends Horde_Registry_Registryconfig
{
    public function __construct()
    {
    }

    public function detectWebroot($basedir)
    {
        return $this->_detectWebroot($basedir);
    }
}
