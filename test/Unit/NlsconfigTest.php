<?php

declare(strict_types=1);

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Session;
use Horde_Support_Stub;
use Horde_Test_Stub_Registry;
use Horde_Test_Stub_Registry_Loadconfig;
use Horde_Registry_Nlsconfig;

/**
 * Tests for Horde_Registry_Nlsconfig.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
#[CoversClass(Horde_Registry_Nlsconfig::class)]
class NlsconfigTest extends TestCase
{
    public function setUp(): void
    {
        $GLOBALS['session'] = new Horde_Session();
        $GLOBALS['session']->sessionHandler = new Horde_Support_Stub();
        $GLOBALS['registry'] = new Horde_Test_Stub_Registry('john', 'horde');
        $config = new Horde_Test_Stub_Registry_Loadconfig(
            'horde',
            'nls.php',
            'horde_nls_config'
        );
        foreach ($this->providerForTestGet() as $values) {
            $config->config['horde_nls_config'][$values[0]] = $values[1];
        }
        $GLOBALS['registry']->setConfigFile(
            $config,
            'nls.php',
            'horde_nls_config',
            'horde'
        );
    }

    public static function providerForTestGet()
    {
        return [
            'languages' => [
                'languages', ['en_US' => '&#x202d;English (American)'],
            ],
            'aliases' => [
                'aliases',
                ['ar' => 'ar_SY', 'bg' => 'bg_BG'],
            ],
            'charsets' => [
                'charsets',
                ['bg_BG' => 'windows-1251', 'bs_BA' => 'ISO-8859-2'],
            ],
        ];
    }

    /**
     * @todo Implement test for accessing language/alias/charset data via __get()
     */
    public function testGet()
    {
        $this->markTestIncomplete(
            'Test for Nlsconfig::__get() not yet implemented. '
            . 'Should test accessing $nls->languages, $nls->aliases, and $nls->charsets.'
        );
    }

    public function testValidLang()
    {
        $nls = new Horde_Registry_Nlsconfig();
        $this->assertTrue($nls->validLang('en_US'));
        $this->assertFalse($nls->validLang('xy_XY'));
    }
}
