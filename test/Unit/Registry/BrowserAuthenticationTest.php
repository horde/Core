<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Registry;

use Horde\Browser\Browser;
use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use Horde_Auth;
use Horde_Core_Auth_Application;
use Horde_Core_Factory_Auth;
use Horde_Injector;
use Horde_Registry;
use Horde_Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_Registry::class)]
class BrowserAuthenticationTest extends TestCase
{
    private const SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15';
    private const HELPER = 'com.apple.WebKit.Networking/21622.1.22.11.14 Network/5569.1.3 macOS/26.0';

    private Horde_Registry $registry;
    private Horde_Core_Auth_Application&MockObject $auth;
    private HordeSession $session;
    private Browser $browser;

    /** @var array<string, mixed> */
    private array $savedGlobals = [];

    protected function setUp(): void
    {
        foreach (['browser', 'conf', 'injector', 'session', 'prefs', '_SESSION', '_SERVER'] as $name) {
            if (array_key_exists($name, $GLOBALS)) {
                $this->savedGlobals[$name] = $GLOBALS[$name];
            }
        }

        $this->registry = (new ReflectionClass(Horde_Registry::class))->newInstanceWithoutConstructor();
        $this->auth = $this->createMock(Horde_Core_Auth_Application::class);
        $this->session = new HordeSession(new SessionId('browser-auth-test'), []);
        $this->session->setScoped('horde', 'auth/browser', self::SAFARI);
        $this->session->setScoped('horde', 'auth/remoteAddr', '192.0.2.1');
        $this->session->setSessionBegin(time());
        $this->browser = new Browser(self::HELPER);

        $factory = $this->createStub(Horde_Core_Factory_Auth::class);
        $factory->method('create')->willReturn($this->auth);
        $injector = $this->createStub(Horde_Injector::class);
        $injector->method('get')->willReturnCallback(fn (string $class) => match ($class) {
            Browser::class => $this->browser,
            Horde_Core_Factory_Auth::class => $factory,
            default => self::fail('Unexpected injector lookup: ' . $class),
        });

        $GLOBALS['injector'] = $injector;
        $GLOBALS['session'] = new Horde_Session($this->session);
        $GLOBALS['browser'] = new \stdClass();
        $GLOBALS['conf'] = ['auth' => ['checkbrowser' => true]];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        foreach (['browser', 'conf', 'injector', 'session', 'prefs', '_SESSION', '_SERVER'] as $name) {
            if (array_key_exists($name, $this->savedGlobals)) {
                $GLOBALS[$name] = $this->savedGlobals[$name];
            } else {
                unset($GLOBALS[$name]);
            }
        }
    }

    #[DataProvider('browserRequestProvider')]
    public function testBrowserRequestPolicy(
        string $method,
        string $current,
        ?string $original,
        bool $checkBrowser,
        bool $expected
    ): void {
        $_SERVER['REQUEST_METHOD'] = $method;
        $this->browser = new Browser($current);
        $this->session->setScoped('horde', 'auth/browser', $original);
        $GLOBALS['conf']['auth']['checkbrowser'] = $checkBrowser;

        if ($expected) {
            $this->auth->expects(self::once())->method('validateAuth')->willReturn(true);
            $this->auth->expects(self::never())->method('setError');
        } else {
            $this->auth->expects(self::never())->method('validateAuth');
            $this->auth->expects(self::once())->method('setError')
                ->with(Horde_Core_Auth_Application::REASON_BROWSER);
        }

        self::assertSame($expected, $this->registry->checkExistingAuth());
        self::assertSame($original, $this->session->getScoped('horde', 'auth/browser'));
    }

    public static function browserRequestProvider(): array
    {
        return [
            'helper GET' => ['GET', self::HELPER, self::SAFARI, true, true],
            'helper HEAD' => ['HEAD', self::HELPER, self::SAFARI, true, true],
            'helper POST rejected' => ['POST', self::HELPER, self::SAFARI, true, false],
            'helper PUT rejected' => ['PUT', self::HELPER, self::SAFARI, true, false],
            'helper without request method rejected' => ['', self::HELPER, self::SAFARI, true, false],
            'ordinary POST unchanged' => ['POST', self::SAFARI, self::SAFARI, true, true],
            'ordinary browser change rejected' => ['GET', 'Different Browser/1.0', self::SAFARI, true, false],
            'helper for unknown browser rejected' => ['GET', self::HELPER, 'Different Browser/1.0', true, false],
            'missing stored browser rejected' => ['GET', self::HELPER, null, true, false],
            'disabled browser check unchanged' => ['POST', 'Different Browser/1.0', self::SAFARI, false, true],
        ];
    }

    public function testHelperDoesNotBypassSessionExpiry(): void
    {
        $GLOBALS['conf']['session']['max_time'] = 60;
        $this->session->setSessionBegin(time() - 120);
        $this->auth->expects(self::never())->method('validateAuth');
        $this->auth->expects(self::once())->method('setError')
            ->with(Horde_Core_Auth_Application::REASON_SESSIONMAXTIME);

        self::assertFalse($this->registry->checkExistingAuth());
    }

    public function testHelperDoesNotBypassIpCheck(): void
    {
        $GLOBALS['conf']['auth']['checkip'] = true;
        $_SERVER['REMOTE_ADDR'] = '192.0.2.2';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_HOST']);
        $this->auth->expects(self::never())->method('validateAuth');
        $this->auth->expects(self::once())->method('setError')
            ->with(Horde_Core_Auth_Application::REASON_SESSIONIP);

        self::assertFalse($this->registry->checkExistingAuth());
    }

    public function testHelperDoesNotBypassBackendValidation(): void
    {
        $this->auth->expects(self::once())->method('validateAuth')->willReturn(false);
        $this->auth->method('getError')->willReturn(false);
        $this->auth->expects(self::once())->method('setError')->with(Horde_Auth::REASON_SESSION);

        self::assertFalse($this->registry->checkExistingAuth());
    }

    public function testHelperStillValidatesApplicationAuth(): void
    {
        $this->auth->expects(self::exactly(2))->method('validateAuth')->willReturn(true);
        $this->auth->expects(self::never())->method('setError');

        self::assertTrue($this->registry->checkExistingAuth('imp'));
        self::assertSame(self::SAFARI, $this->session->getScoped('horde', 'auth/browser'));
    }

    public function testLoginStoresModernBrowserUserAgent(): void
    {
        $this->browser = new Browser(self::SAFARI);
        $this->auth->expects(self::never())->method('validateAuth');
        $registry = $this->getMockBuilder(Horde_Registry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuth', 'getApp', 'remoteHost', 'setAuthCredential', 'loadPrefs', 'setLanguageEnvironment'])
            ->getMock();
        $registry->method('getAuth')->willReturn(false);
        $registry->method('getApp')->willReturn('horde');
        $registry->method('remoteHost')->willReturn((object) ['addr' => '192.0.2.1']);
        $registry->expects(self::once())->method('setAuthCredential')
            ->with(['password' => 'test'], null, 'horde');
        $registry->expects(self::once())->method('loadPrefs')->with('horde');
        $registry->expects(self::once())->method('setLanguageEnvironment')->with(null, 'horde');

        $registry->setAuth('test', ['password' => 'test'], ['no_convert' => true]);

        self::assertSame(self::SAFARI, $this->session->getScoped('horde', 'auth/browser'));
    }
}
