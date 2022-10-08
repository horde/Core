<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test;

use PHPUnit\Framework\TestCase;

use Horde\Core\Test\Stub\Registryconfig as RegistryconfigStub;
use Horde_Registry_Hordeconfig_Merged;
use Horde_Registry_Hordeconfig;

/**
 * Tests for Horde_Registry.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class RegistryTest extends TestCase
{
    protected $_tmpdir;

    public function tearDown(): void
    {
        if (is_dir($this->_tmpdir)) {
            rmdir($this->_tmpdir);
            rmdir(dirname($this->_tmpdir));
        }
    }

    public function testDetectWebroot()
    {
        $this->_tmpdir = sys_get_temp_dir() . '/' . uniqid() . '/horde';
        mkdir($this->_tmpdir, 0777, true);
        $config = new RegistryconfigStub();

        $_SERVER['SCRIPT_URL'] = '/horde/foo/bar';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_URL'] = '/horde/foo/bar/horde';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_URL'] = '/horde';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_URL'] = '/foo';
        $this->assertEquals('', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_URL'] = '/';
        $this->assertEquals('', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_URL'] = '';
        $this->assertEquals('', $config->detectWebroot($this->_tmpdir));

        $_SERVER['SCRIPT_URL'] = '/horde';
        $this->assertEquals('', $config->detectWebroot(dirname($this->_tmpdir)));

        unset($_SERVER['SCRIPT_URL']);
        $_SERVER['SCRIPT_NAME'] = '/horde/foo/bar';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        unset($_SERVER['SCRIPT_NAME']);
        $_SERVER['PHP_SELF'] = '/horde/foo/bar';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['PHP_SELF'] = '/horde';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['PHP_SELF'] = '/horde/';
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));

        $_SERVER['PHP_SELF'] = '/foo';
        $this->assertEquals('', $config->detectWebroot($this->_tmpdir));

        $_SERVER['PHP_SELF'] = '/foo/';
        $this->assertEquals('', $config->detectWebroot($this->_tmpdir));

        $_SERVER['PHP_SELF'] = '/horde';
        $this->assertEquals('', $config->detectWebroot(dirname($this->_tmpdir)));

        unset($_SERVER['PHP_SELF']);
        $this->assertEquals('/horde', $config->detectWebroot($this->_tmpdir));
    }

    public function testBug10381()
    {
        $a1 = [
            'conf' => [
                'foo' => 'a',
                'bar' => 'b',
                'foobar' => [
                    'a', 'b', 'c',
                ],
                'foobar2' => [
                    'a' => 1,
                    'b' => 2,
                ],
            ],
            'a1_only' => [
                'a' => 1,
                'b' => [
                    'c' => 2,
                ],
            ],
        ];

        $a2 = [
            'conf' => [
                'bar' => 'c',
                'baz' => 'g',
                'foobar' => [
                    'd', 'e',
                ],
                'foobar2' => [
                    'a' => 3,
                    'c' => 4,
                ],
            ],
            'a2_only' => [
                'a' => 1,
                'b' => [
                    'c' => 2,
                ],
            ],
        ];

        $ob = new Horde_Registry_Hordeconfig_Merged([
            'aconfig' => new Horde_Registry_Hordeconfig([
                'app' => 'bar',
                'config' => $a2,
            ]),
            'hconfig' => new Horde_Registry_Hordeconfig([
                'app' => 'foo',
                'config' => $a1,
            ]),
        ]);

        $this->assertEquals(
            [
                'conf' => [
                    'foo' => 'a',
                    'bar' => 'c',
                    'baz' => 'g',
                    'foobar' => [
                        'd', 'e',
                    ],
                    'foobar2' => [
                        'a' => 3,
                        'b' => 2,
                        'c' => 4,
                    ],
                ],
                'a1_only' => [
                    'a' => 1,
                    'b' => [
                    'c' => 2,
                    ],
                ],
                'a2_only' => [
                    'a' => 1,
                    'b' => [
                        'c' => 2,
                    ],
                ],
            ],
            $ob->toArray()
        );
    }
}
