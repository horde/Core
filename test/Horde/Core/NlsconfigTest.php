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
namespace Horde\Core;
use \PHPUnit\Framework\TestCase;
use \Horde_Test_Case as HordeTestCase;
use \Horde_Session;
use \Horde_Support_Stub;
use \Horde_Test_Stub_Registry;
use \Horde_Test_Stub_Registry_Loadconfig;
use \Horde_Registry_Nlsconfig;

/**
 * Tests for Horde_Registry_Nlsconfig.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class NlsconfigTest extends HordeTestCase
{
    public function setUp(): void
    {
        $GLOBALS['session'] = new Horde_Session();
        $GLOBALS['session']->sessionHandler = new Horde_Support_Stub();
        $GLOBALS['registry'] = new Horde_Test_Stub_Registry('john', 'horde');
        $config = new Horde_Test_Stub_Registry_Loadconfig(
            'horde', 'nls.php', 'horde_nls_config'
        );
        foreach ($this->providerForTestGet() as $values) {
            $config->config['horde_nls_config'][$values[0]] = $values[1];
        }
        $GLOBALS['registry']->setConfigFile(
            $config, 'nls.php', 'horde_nls_config', 'horde'
        );
    }

    public function providerForTestGet()
    {
        return array(
            'languages' => array(
                'languages', array('en_US' => '&#x202d;English (American)')
            ),
            'aliases' => array(
                'aliases',
                array('ar' => 'ar_SY', 'bg' => 'bg_BG')
            ),
            'charsets' => array(
                'charsets',
                array('bg_BG' => 'windows-1251', 'bs_BA' => 'ISO-8859-2')
            ),
        );
    }

    /**
     * @dataProvider providerForTestGet
     */
    public function testGet($key, $expected)
    {
        $nls = new Horde_Registry_Nlsconfig();
        $this->markTestIncomplete();
    }

    public function testValidLang()
    {
        $nls = new Horde_Registry_Nlsconfig();
        $this->assertTrue($nls->validLang('en_US'));
        $this->assertFalse($nls->validLang('xy_XY'));
    }
}
