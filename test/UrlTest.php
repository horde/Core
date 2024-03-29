<?php

namespace Horde\Core\Test;

use PHPUnit\Framework\TestCase;
use Horde;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Core
 * @subpackage UnitTests
 */
class UrlTest extends TestCase
{
    public function testUrl()
    {
        $sname = session_name();

        $expected = [
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php',
            '/hordeurl/test.php?' . $sname,
            'http://example.com/hordeurl/test.php',
            'http://example.com/hordeurl/test.php?' . $sname,
            'http://example.com/hordeurl/test.php',
            'http://example.com/hordeurl/test.php?' . $sname,
            'http://example.com:443/hordeurl/test.php',
            'http://example.com:443/hordeurl/test.php?' . $sname,
            'http://example.com:443/hordeurl/test.php',
            'http://example.com:443/hordeurl/test.php?' . $sname,
            'https://example.com:80/hordeurl/test.php',
            'https://example.com:80/hordeurl/test.php?' . $sname,
            'https://example.com:80/hordeurl/test.php',
            'https://example.com:80/hordeurl/test.php?' . $sname,
            'https://example.com/hordeurl/test.php',
            'https://example.com/hordeurl/test.php?' . $sname,
            'https://example.com/hordeurl/test.php',
            'https://example.com/hordeurl/test.php?' . $sname,
            'http://example.com/hordeurl/test.php',
            'http://example.com/hordeurl/test.php?' . $sname,
            'http://example.com/hordeurl/test.php',
            'http://example.com/hordeurl/test.php?' . $sname,
            'http://example.com:443/hordeurl/test.php',
            'http://example.com:443/hordeurl/test.php?' . $sname,
            'http://example.com:443/hordeurl/test.php',
            'http://example.com:443/hordeurl/test.php?' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            '/hordeurl/test.php?foo=1',
            '/hordeurl/test.php?foo=1&amp;' . $sname,
            'http://example.com/hordeurl/test.php?foo=1',
            'http://example.com/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1',
            'http://example.com/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1',
            'http://example.com:443/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1',
            'http://example.com:443/hordeurl/test.php?foo=1&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1',
            'https://example.com:80/hordeurl/test.php?foo=1&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1',
            'https://example.com:80/hordeurl/test.php?foo=1&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1',
            'https://example.com/hordeurl/test.php?foo=1&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1',
            'https://example.com/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1',
            'http://example.com/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1',
            'http://example.com/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1',
            'http://example.com:443/hordeurl/test.php?foo=1&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1',
            'http://example.com:443/hordeurl/test.php?foo=1&' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3',
            '/hordeurl/test.php?foo=1&amp;bar=2&amp;baz=3&amp;' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&baz=3',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&baz=3',
            'https://example.com:80/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'https://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'https://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3',
            'http://example.com:443/hordeurl/test.php?foo=1&bar=2&baz=3&' . $sname,
        ];

        $uris = [
            'test.php',
            'test.php?foo=1',
            'test.php?foo=1&bar=2',
            'test.php?foo=1&amp;bar=2',
            'test.php?foo=1&amp;bar=2&amp;baz=3',
        ];
        $fulls = [false, true];
        $ssls = [0, 1, 3];
        $ports = [80, 443];
        $expect = 0;
        $GLOBALS['registry'] = new Registry();
        $GLOBALS['conf']['server']['name'] = 'example.com';

        foreach ($uris as $uri) {
            foreach ($fulls as $full) {
                foreach ($ssls as $ssl) {
                    $GLOBALS['conf']['use_ssl'] = $ssl;
                    foreach ($ports as $port) {
                        $GLOBALS['conf']['server']['port'] = $port;
                        $this->assertEquals($expected[$expect++], (string)Horde::url($uri, $full, ['append_session' => -1]), sprintf('URI: %s, full: %s, SSL: %s, port: %d, session: -1', $uri, var_export($full, true), $ssl, $port));
                        unset($_COOKIE[session_name()]);
                        $this->assertEquals($expected[$expect++], (string)Horde::url($uri, $full, ['append_session' => 0]), sprintf('URI: %s, full: %s, SSL: %s, port: %d, session: 0, cookie: false', $uri, var_export($full, true), $ssl, $port));
                        $_COOKIE[session_name()] = [];
                        $this->assertEquals($expected[$expect++], (string)Horde::url($uri, $full, ['append_session' => 0]), sprintf('URI: %s, full: %s, SSL: %s, port: %d, session: 0, cookie: true', $uri, var_export($full, true), $ssl, $port));
                        $this->assertEquals($expected[$expect++], (string)Horde::url($uri, $full, ['append_session' => 1]), sprintf('URI: %s, full: %s, SSL: %s, port: %d, session: 1, cookie: true', $uri, var_export($full, true), $ssl, $port));
                    }
                }
            }
        }
    }

    public function testFullUrl()
    {
        $GLOBALS['registry'] = new RegistryFull();
        $GLOBALS['conf']['server']['name'] = 'www.example.com';
        $GLOBALS['conf']['use_ssl'] = 1;

        $this->assertEquals(
            'http://www.example.com/hordeurl/foo',
            (string)Horde::url('foo', true, ['append_session' => -1])
        );
        $this->assertEquals(
            'http://www.example.com/hordeurl/foo',
            (string)Horde::url('/hordeurl/foo', true, ['append_session' => -1])
        );
        $this->assertEquals(
            'http://www.example.com/hordeurl/foo/bar',
            (string)Horde::url('http://www.example.com/hordeurl/foo/bar', true, ['append_session' => -1])
        );
    }

    public function testBug9712()
    {
        $GLOBALS['registry'] = new Registry();

        $GLOBALS['conf']['server']['name'] = 'example.com';
        $GLOBALS['conf']['server']['port'] = 1443;
        $GLOBALS['conf']['use_ssl'] = 2;

        $this->assertEquals(
            'https://example.com:1443/foo',
            strval(Horde::url('https://example.com:1443/foo', true, ['append_session' => -1]))
        );
    }

    public function testSelfUrl()
    {
        $GLOBALS['registry'] = new Registry();
        $GLOBALS['browser'] = new Browser();
        $GLOBALS['conf']['server']['name'] = 'example.com';
        $GLOBALS['conf']['server']['port'] = 80;
        $GLOBALS['conf']['use_ssl'] = 3;
        $_COOKIE[session_name()] = 'foo';

        // Simple script access.
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test.php';
        $_SERVER['QUERY_STRING'] = '';
        $this->assertEquals('/hordeurl/test.php', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/test.php', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/test.php', (string)Horde::selfUrl(true, false, true));
        $this->assertEquals('https://example.com/hordeurl/test.php', (string)Horde::selfUrl(true, false, true, true));

        // No SCRIPT_NAME.
        unset($_SERVER['SCRIPT_NAME']);
        $_SERVER['PHP_SELF'] = '/hordeurl/test.php';
        $_SERVER['QUERY_STRING'] = '';
        $this->assertEquals('/hordeurl/test.php', (string)Horde::selfUrl());

        // With parameters.
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test.php';
        $_SERVER['QUERY_STRING'] = 'foo=bar&x=y';
        $this->assertEquals('/hordeurl/test.php', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/test.php?foo=bar&amp;x=y', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/test.php?foo=bar&x=y', (string)Horde::selfUrl(true, false, true));
        $this->assertEquals('https://example.com/hordeurl/test.php?foo=bar&x=y', (string)Horde::selfUrl(true, false, true, true));

        // index.php script name.
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/index.php';
        $_SERVER['QUERY_STRING'] = 'foo=bar&x=y';
        $this->assertEquals('/hordeurl/', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/?foo=bar&amp;x=y', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/?foo=bar&x=y', (string)Horde::selfUrl(true, false, true));

        // Directory access.
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/';
        $_SERVER['QUERY_STRING'] = 'foo=bar&x=y';
        $this->assertEquals('/hordeurl/', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/?foo=bar&amp;x=y', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/?foo=bar&x=y', (string)Horde::selfUrl(true, false, true));

        // Path info.
        $_SERVER['REQUEST_URI'] = '/hordeurl/test.php/foo/bar?foo=bar&x=y';
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test.php';
        $_SERVER['QUERY_STRING'] = 'foo=bar&x=y';
        $this->assertEquals('/hordeurl/test.php', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/test.php/foo/bar?foo=bar&amp;x=y', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/test.php/foo/bar?foo=bar&x=y', (string)Horde::selfUrl(true, false, true));

        // URL rewriting.
        $_SERVER['REQUEST_URI'] = '/hordeurl/test/foo/bar?foo=bar&x=y';
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test/index.php';
        $_SERVER['QUERY_STRING'] = 'foo=bar&x=y';
        $this->assertEquals('/hordeurl/test/', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/test/foo/bar?foo=bar&amp;x=y', (string)Horde::selfUrl(true));
        $this->assertEquals('http://example.com/hordeurl/test/foo/bar?foo=bar&x=y', (string)Horde::selfUrl(true, false, true));
        $_SERVER['REQUEST_URI'] = '/hordeurl/foo/bar?foo=bar&x=y';
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test.php';
        $this->assertEquals('/hordeurl/', (string)Horde::selfUrl());
        $this->assertEquals('/hordeurl/foo/bar?foo=bar&amp;x=y', (string)Horde::selfUrl(true));

        // Special cases.
        $_SERVER['REQUEST_URI'] = '/test/42?id=42';
        $_SERVER['SCRIPT_NAME'] = '/test/index.php';
        $_SERVER['QUERY_STRING'] = 'id=42&id=42';
        $this->assertEquals('/test/42?id=42', (string)Horde::selfUrl(true));

        // Non-standard port
        $_SERVER['REQUEST_URI'] = '/hordeurl/test/foo/bar?foo=bar&x=y';
        $_SERVER['SCRIPT_NAME'] = '/hordeurl/test/index.php';
        $GLOBALS['conf']['use_ssl'] = 0;
        $GLOBALS['conf']['server']['port'] = 1234;
        $this->assertEquals('http://example.com:1234/hordeurl/test/', Horde::selfUrl(false, true, true));

        $GLOBALS['conf']['use_ssl'] = 1;
        $GLOBALS['conf']['server']['port'] = 1234;
        $this->assertEquals('https://example.com:1234/hordeurl/test/', Horde::selfUrl(false, true, true));

        $GLOBALS['conf']['use_ssl'] = 2;
        $GLOBALS['conf']['server']['port'] = 1234;
        $this->assertEquals('http://example.com:1234/hordeurl/test/', Horde::selfUrl(false, true, true));

        // This fails since the port is always ignored. Not sure if this is intended or not.
        $GLOBALS['conf']['use_ssl'] = 3;
        $GLOBALS['conf']['server']['port'] = 1234;
        $this->assertEquals('http://example.com:1234/hordeurl/test/', Horde::selfUrl(false, true, true));
    }

    public function testSignUrl()
    {
        $now = 1000000000;
        $query = 'foo=42&bar=xyz';
        $signedQuery = 'foo=42&bar=xyz&_t=1000000000&_h=Zt9M0io4vBpM2dA2gMpiPiDKTUA';
        $url = 'http://www.example.com';
        $signedUrl = 'http://www.example.com?_t=1000000000&_h=UcbwFn6pLKHh2U35cK-GHwGT6_Q';
        $urlQuery = 'http://www.example.com?hello=world';
        $signedUrlQuery = 'http://www.example.com?hello=world&_t=1000000000&_h=_wQyvcO90UF7S2sdhRr-X4rRT9k';

        $signed = Horde::signQueryString($query, $now);
        $this->assertEquals($query, $signed);

        $signed = Horde::signUrl($url, $now);
        $this->assertEquals($url, $signed);
        $signed = Horde::signUrl($urlQuery, $now);
        $this->assertEquals($urlQuery, $signed);

        $GLOBALS['conf']['secret_key'] = 'abcdefghijklmnopqrstuvwxyz';
        $GLOBALS['conf']['urls']['hmac_lifetime'] = '30';

        $signed = Horde::signQueryString($query, $now);
        $this->assertEquals($signedQuery, $signed);
        $this->assertTrue(Horde::verifySignedQueryString($signedQuery, $now));
        $this->assertFalse(Horde::verifySignedQueryString($query, $now));
        $this->assertFalse(Horde::verifySignedQueryString($signedQuery, $now + $GLOBALS['conf']['urls']['hmac_lifetime'] * 60 + 1));

        $signed = Horde::signUrl($url, $now);
        $this->assertEquals($signedUrl, $signed);
        $this->assertEquals($url, Horde::verifySignedUrl($signedUrl, $now));
        $this->assertFalse(Horde::verifySignedUrl($url, $now));
        $this->assertFalse(Horde::verifySignedUrl($signedUrl, $now + $GLOBALS['conf']['urls']['hmac_lifetime'] * 60 + 1));

        $signed = Horde::signUrl($urlQuery, $now);
        $this->assertEquals($signedUrlQuery, $signed);
        $this->assertEquals($urlQuery, Horde::verifySignedUrl($signedUrlQuery, $now));
        $this->assertFalse(Horde::verifySignedUrl($urlQuery, $now));
        $this->assertFalse(Horde::verifySignedUrl($signedUrlQuery, $now + $GLOBALS['conf']['urls']['hmac_lifetime'] * 60 + 1));
    }
}

class Registry
{
    public function get()
    {
        return '/hordeurl';
    }
}

class RegistryFull
{
    public function get()
    {
        return 'http://www.example.com/hordeurl';
    }
}

class Browser
{
    public function hasQuirk()
    {
        return false;
    }

    public function usingSSLConnection()
    {
        return false;
    }
}
