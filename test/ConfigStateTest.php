<?php
/**
 * Copyright 2016-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test;

use Horde\Test\TestCase;
use Horde\Core\Config\State;

/**
 * Tests for Horde\Core\Config\State.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class ConfigStateTest extends TestCase
{
    public function testFailsWhenNoGlobalsOrParam()
    {
        if (isset($GLOBALS['conf'])) {
            unset($GLOBALS['conf']);
        }
        $this->expectException(\Horde_Exception::class);
        $state = new State();
        $this->assertEquals([], $state->toArray());
    }

    public function testPassedEqualsDump()
    {
        if (isset($GLOBALS['conf'])) {
            unset($GLOBALS['conf']);
        }
        $param = ['foo' => 'bar'];
        $state = new State($param);
        $this->assertEquals($param, $state->toArray());
    }
}
