<?php
/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

 namespace Horde\Core\Test\Middleware;

 use Horde\Core\Middleware\AuthIsGlobalAdmin;

 use Horde\Test\TestCase;

 use Horde_Session;
 use Horde_Exception;
 use Horde_Registry;

 class AuthIsGlobalAdminTest extends TestCase
 {
     use SetUpTrait;

     protected function getMiddleware()
     {
         return new AuthIsGlobalAdmin($this->registry);
     }
     
     public function testIsAdmin()
     {
        $username = 'testuser01';
        $middleware = $this->getMiddleware();
        $this->registry->method('isAuthenticated')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);
        $this->registry->method('isAdmin')->willReturn(true); // will set true
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
        // assert that $authAdminUser has the correct Value

        $this->assertTrue($authAdminUser); // tests if $authAdminUser is set to true -> Admin
        $this->assertEquals(200, $response->getStatusCode());
     }

     public function testIsNotAdmin()
     {
        $username = 'testuser01';
        $middleware = $this->getMiddleware();
        $this->registry->method('isAuthenticated')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);
        $this->registry->method('isAdmin')->willReturn(false); //will set false
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
        // assert that $authAdminUser has the correct Value

        $this->assertNull($authAdminUser); // asserTrue/False before, resulted in failing to assert that null is false 
        $this->assertEquals(200, $response->getStatusCode());
     }

     public function testUserIsNotAuthenticated()
     {
         $username = 'testuser01';
         $middleware = $this->getMiddleware();
         $this->registry->method('isAuthenticated')->willReturn(false);
         $this->registry->method('getAuth')->willReturn($username);
         $this->registry->method('isAdmin')->willReturn(true);
         $request = $this->requestFactory->createServerRequest('GET', '/test');
         $response = $middleware->process($request, $this->handler);

         $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
         // assert that $authAdminUser has the correct Value

         $this->assertNull($authAdminUser);
         $this->assertEquals(200, $response->getStatusCode());
     }
 }