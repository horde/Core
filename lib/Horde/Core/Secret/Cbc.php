<?php

/**
 * Copyright 2015-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2015-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 */

use Horde\Core\Secret\SessionSecret;
use Horde\Crypt\Blowfish\Blowfish;

/**
 * Horde_Secret, using single session key, with CBC based Blowfish encryption.
 *
 * This is much more secure than the default Horde_Secret algorithm. It should
 * be used for all Horde_Secret/session encryption, but for BC purposes it
 * needs to live in a separate class for now.
 *
 * Uses the additional parameter 'iv' - the IV used to seed the CBC cipher.
 *
 * @todo  Merge this class with Horde_Core_Secret.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2015-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 * @since     2.20.0
 */
class Horde_Core_Secret_Cbc extends Horde_Core_Secret implements SessionSecret
{
    /**
     * Key used for current cached cipher object.
     *
     * @var string
     */
    protected $_cachedKey = '';

    /**
     */
    protected function _getCipherOb($key)
    {
        $key = substr($key, 0, 56);

        if (!isset($this->_cipherCache[self::HORDE_KEYNAME])
            || $this->_cachedKey !== $key) {
            $this->_cipherCache[self::HORDE_KEYNAME] = Blowfish::cbc(
                $key,
                $this->_params['iv']
            );
            $this->_cachedKey = $key;
        }

        return $this->_cipherCache[self::HORDE_KEYNAME];
    }
}
