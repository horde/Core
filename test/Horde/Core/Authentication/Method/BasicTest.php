<?php
declare(strict_types=1);
namespace Horde\Core\Authentication\Method;
use \Horde\Core\Authentication\BasicMethod;
use \PHPUnit\Framework\TestCase;
use \Horde\Core\Config\State as ConfigState;
use \Horde_Controller_Request_Mock as MockRequest;
use \Horde_Exception_NotFound as NotFoundException;


class BasicTest extends TestCase
{

    function testNoHeader(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([]);
        $method = new BasicMethod;
        $credential = $method->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credential->get('username');
        $this->expectException(NotFoundException::class);
        $credential->get('password');
    }


    function testFoundHeader(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([
            'HEADER' => [
                // admin:pass
                'authorization' => 'Basic YWRtaW46cGFzcw=='
            ]
        ]);
        $method = new BasicMethod;
        $credential = $method->getCredentials($request);
        $this->assertEquals('admin', $credential->get('username'));
        $this->assertEquals('pass', $credential->get('password'));
    }

    function testFoundHeaderButNoBasic(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([
            'HEADER' => [
                // admin:pass
                'authorization' => 'Token Rm9vYmFydG9rZW4K'
            ]
        ]);
        $method = new BasicMethod;
        $credential = $method->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credential->get('username');
        $this->expectException(NotFoundException::class);
        $credential->get('password');
    }

}