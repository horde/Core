<?php
declare(strict_types=1);
namespace Horde\Core\Authentication\Method;
use \Horde\Core\Authentication\BasicMethod;
use \PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    function testNoCookie(): void
    {

    }


    function testFoundCookie(): void
    {

    }

    function testCookiesButNotExpected(): void
    {

    }
    <?php
    declare(strict_types=1);
    namespace Horde\Core\Authentication\Method;
    use \Horde\Core\Authentication\BasicMethod;
    use \Phpunit\Framework\TestCase;
    
    class BasicTest implements TestCase
    {
        function testNoHeader(): void
        {
    
        }
    
    
        function testFoundHeader(): void
        {
    
        }
    
        function testFoundGetParam(): void
        {
    
        }

        function testPreferCookieOverParam(): void
        {
    
        }   
    }
}