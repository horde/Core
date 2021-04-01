<?php

declare(strict_types=1);

namespace Horde\Core\Authentication\Backend;
use \Horde\Core\Authentication\Credentials;
use \Horde\Core\Authentication\MockBackend;
use \PHPUnit\Framework\TestCase;
use \Horde_Exception_NotFound as NotFoundException;

class MockTest extends TestCase
{
    function testExpectedMatchesExactly()
    {
        $cred = new Credentials;
        $cred->set('username', 'hugo');
        $cred->set('password', 'gnome');

        $expectedCred = new Credentials;
        $expectedCred->set('password', 'gnome');
        $expectedCred->set('username', 'hugo');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, [$expectedCred]);
        $this->assertTrue($backend->checkCredentials($cred));
    }

    function testExpectedMatchesWithExtras()
    {
        $cred = new Credentials;
        $cred->set('username', 'hugo');
        $cred->set('password', 'gnome');
        $cred->set('secondfactor', '123456');

        $expectedCred = new Credentials;
        $expectedCred->set('password', 'gnome');
        $expectedCred->set('username', 'hugo');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, [$expectedCred]);
        $this->assertTrue($backend->checkCredentials($cred));
    }

    function testExpectedMatchesWithMultiples()
    {
        $cred = new Credentials;
        $cred->set('username', 'hugo');
        $cred->set('password', 'gnome');

        $expectedCred = [];
        $expectedCred[0] = new Credentials;
        $expectedCred[0]->set('password', 'puppet');
        $expectedCred[0]->set('username', 'spejbl');
        $expectedCred[1] = new Credentials;
        $expectedCred[1]->set('password', 'gnome');
        $expectedCred[1]->set('username', 'hugo');
        $expectedCred[2] = new Credentials;
        $expectedCred[2]->set('password', 'giant');
        $expectedCred[2]->set('username', 'rubezahl');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, $expectedCred);
        $this->assertTrue($backend->checkCredentials($cred));        
    }

    function testExpectedFailsWithMultiples()
    {
        $cred = new Credentials;
        $cred->set('username', 'loreley');
        $cred->set('password', 'rivermaid');

        $expectedCred = [];
        $expectedCred[0] = new Credentials;
        $expectedCred[0]->set('password', 'puppet');
        $expectedCred[0]->set('username', 'spejbl');
        $expectedCred[1] = new Credentials;
        $expectedCred[1]->set('password', 'gnome');
        $expectedCred[1]->set('username', 'hugo');
        $expectedCred[2] = new Credentials;
        $expectedCred[2]->set('password', 'giant');
        $expectedCred[2]->set('username', 'rubezahl');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, $expectedCred);
        $this->assertFalse($backend->checkCredentials($cred));        
    }

    function testExpectedFailsWithOne()
    {
        $cred = new Credentials;
        $cred->set('username', 'igor');
        $cred->set('password', 'igor');

        $expectedCred = new Credentials;
        $expectedCred->set('password', 'gnome');
        $expectedCred->set('username', 'hugo');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, [$expectedCred]);
        $this->assertFalse($backend->checkCredentials($cred));        
    }

    function testExpectedFailsWithPartial()
    {
        $cred = new Credentials;
        $cred->set('username', 'igor');
        $cred->set('password', 'assistant');

        $expectedCred = new Credentials;
        $expectedCred->set('password', 'igor');
        $expectedCred->set('username', 'igor');

        $expectedFields = ['username', 'password'];
        $backend = new MockBackend($expectedFields, [$expectedCred]);
        $this->assertFalse($backend->checkCredentials($cred));                
    }

    function testMissingCredential()
    {
        $cred = new Credentials;
        $cred->set('username', 'igor');
        $cred->set('password', 'igor');
        $cred->set('otp', '123456');

        $expectedCred = new Credentials;
        $expectedCred->set('password', 'igor');
        $expectedCred->set('username', 'igor');

        $expectedFields = ['username', 'password', 'otp'];
        $this->expectException(NotFoundException::class);
        $backend = new MockBackend($expectedFields, [$expectedCred]);
        $backend->checkCredentials($cred);
    }
}