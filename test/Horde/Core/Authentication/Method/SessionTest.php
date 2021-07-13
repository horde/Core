<?php
declare(strict_types=1);
namespace Horde\Core\Authentication\Method;
use \Horde\Core\Authentication\SessionMethod;
use \Horde\Core\Config\State as ConfigState;
use \PHPUnit\Framework\TestCase;
use \Horde_Controller_Request_Mock as MockRequest;
use \Horde_Exception_NotFound as NotFoundException;

class SessionTest extends TestCase
{

    public function setUp(): void
    {
        $this->onlyCookiesConfig = new ConfigState(
            [
                'session' => [
                    'name' => 'hordesession',
                    'use_only_cookies' => true
                ]
            ]   
        );

        $this->allowGetSessionsConfig = new ConfigState(
            [
                'session' => [
                    'name' => 'hordesession',
                    'use_only_cookies' => true
                ]
            ]
        );
    }

    function testNoCookie(): void
    {
        $request = new MockRequest;
        $uut = new SessionMethod($this->onlyCookiesConfig);
        $credentials = $uut->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credentials->get('session');
    }

    function testFoundCookie(): void
    {
        $request = new MockRequest([
            'COOKIE' => [
                'hordesession' => 'somesessionkey'
            ]
        ]);
        $uut = new SessionMethod($this->onlyCookiesConfig);
        $credentials = $uut->getCredentials($request);
        $this->assertStringContainsString('somesessionkey', $credentials->get('session'));
    }

    function testCookiesButNotExpected(): void
    {
        $request = new MockRequest([
            'COOKIE' => [
                'othercookie' => 'somesessionkey'
            ]
        ]);
        $uut = new SessionMethod($this->onlyCookiesConfig);
        $credentials = $uut->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credentials->get('session');
    }
   

    function testFoundGetParam(): void
    {
        $this->markTestIncomplete('Get-based session not implemented in UUT');
    }

    function testPreferCookieOverParam(): void
    {
        $this->markTestIncomplete('Get-based session not implemented in UUT');
    }   
}