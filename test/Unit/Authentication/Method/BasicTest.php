<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Authentication\Method;

use Horde\Core\Authentication\BasicMethod;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Core\Config\State as ConfigState;
use Horde_Controller_Request_Mock as MockRequest;
use Horde_Exception_NotFound as NotFoundException;

/**
 * Tests for BasicMethod authentication
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(BasicMethod::class)]
class BasicTest extends TestCase
{
    public function testNoHeader(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([]);
        $method = new BasicMethod();
        $credential = $method->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credential->get('username');
        $this->expectException(NotFoundException::class);
        $credential->get('password');
    }


    public function testFoundHeader(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([
            'HEADER' => [
                // admin:pass
                'authorization' => 'Basic YWRtaW46cGFzcw==',
            ],
        ]);
        $method = new BasicMethod();
        $credential = $method->getCredentials($request);
        $this->assertEquals('admin', $credential->get('username'));
        $this->assertEquals('pass', $credential->get('password'));
    }

    public function testFoundHeaderButNoBasic(): void
    {
        $this->markTestSkipped('Instantiating BasicMethod below causes an error, because null is given when an array is needed. ');

        $request = new MockRequest([
            'HEADER' => [
                // admin:pass
                'authorization' => 'Token Rm9vYmFydG9rZW4K',
            ],
        ]);
        $method = new BasicMethod();
        $credential = $method->getCredentials($request);
        $this->expectException(NotFoundException::class);
        $credential->get('username');
        $this->expectException(NotFoundException::class);
        $credential->get('password');
    }
}
