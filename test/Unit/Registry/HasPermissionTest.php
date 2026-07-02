<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\Registry;

use Horde_Injector;
use Horde_Perms;
use Horde_Perms_Null;
use Horde_Registry;
use Horde_Core_Auth_Application;
use Horde_Core_Factory_Auth;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Horde_Registry::hasPermission() and listApps() auth behaviour.
 *
 * Guards against regressions where SHOW (menu-appearance) checks trigger
 * per-app transparent authentication (e.g. futile IMAP logins for IMP).
 */
class HasPermissionTest extends TestCase
{
    private Horde_Registry&MockObject $registry;

    private Horde_Core_Factory_Auth&MockObject $authFactory;

    private Horde_Perms_Null&MockObject $perms;

    /** @var array<string, mixed> */
    private array $savedGlobals = [];

    protected function setUp(): void
    {
        $this->savedGlobals = [
            'injector' => $GLOBALS['injector'] ?? null,
        ];

        $this->registry = $this->getMockBuilder(Horde_Registry::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isAuthenticated', 'isAdmin', 'getAuth', 'isInactive'])
            ->getMock();
        $this->registry->applications = [
            'imp' => [
                'status' => 'active',
                'name' => 'Mail',
            ],
            'kronolith' => [
                'status' => 'active',
                'name' => 'Calendar',
            ],
        ];

        $this->registry->method('isAdmin')->willReturn(false);
        $this->registry->method('getAuth')->willReturn('alice@example.com');
        $this->registry->method('isInactive')->willReturn(false);

        $this->authFactory = $this->createMock(Horde_Core_Factory_Auth::class);
        $this->perms = $this->getMockBuilder(Horde_Perms_Null::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['exists', 'hasPermission'])
            ->getMock();

        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')
            ->willReturnCallback(function (string $class) {
                return match ($class) {
                    'Horde_Core_Factory_Auth' => $this->authFactory,
                    'Horde_Perms' => $this->perms,
                    default => $this->fail('Unexpected injector lookup: ' . $class),
                };
            });

        $GLOBALS['injector'] = $injector;
    }

    protected function tearDown(): void
    {
        if ($this->savedGlobals['injector'] === null) {
            unset($GLOBALS['injector']);
        } else {
            $GLOBALS['injector'] = $this->savedGlobals['injector'];
        }
    }

    private function stubAppRequiresAuth(string $app, bool $requireAuth = true): Horde_Core_Auth_Application&MockObject
    {
        $auth = $this->createMock(Horde_Core_Auth_Application::class);
        $auth->method('requireAuth')->willReturn($requireAuth);

        return $auth;
    }

    /**
     * @param array<string, bool> $apps
     */
    private function stubAppsRequireAuth(array $apps): void
    {
        $map = [];
        foreach ($apps as $app => $requireAuth) {
            $map[] = [$app, $this->stubAppRequiresAuth($app, $requireAuth)];
        }

        $this->authFactory->method('create')->willReturnMap($map);
    }

    private function stubExplicitPerms(bool $allowed): void
    {
        $this->perms->method('exists')->willReturn(true);
        $this->perms->method('hasPermission')
            ->with(
                $this->anything(),
                'alice@example.com',
                $this->anything(),
            )
            ->willReturn($allowed);
    }

    public function testShowPermissionSkipsIsAuthenticatedWhenAppRequiresAuth(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->never())->method('isAuthenticated');

        $this->stubExplicitPerms(true);

        self::assertTrue(
            $this->registry->hasPermission('imp', Horde_Perms::SHOW),
        );
    }

    public function testReadPermissionInvokesIsAuthenticatedWhenAppRequiresAuth(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->with([
                'app' => 'imp',
                'notransparent' => false,
            ])
            ->willReturn(true);

        $this->stubExplicitPerms(true);

        self::assertTrue(
            $this->registry->hasPermission('imp', Horde_Perms::READ),
        );
    }

    public function testReadPermissionHonorsNotransparentParameter(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->with([
                'app' => 'imp',
                'notransparent' => true,
            ])
            ->willReturn(false);

        self::assertFalse(
            $this->registry->hasPermission(
                'imp',
                Horde_Perms::READ,
                ['notransparent' => true],
            ),
        );
    }

    public function testReadPermissionDeniedWithoutAppAuthentication(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->method('isAuthenticated')->willReturn(false);

        $this->perms->expects($this->never())->method('hasPermission');

        self::assertFalse(
            $this->registry->hasPermission('imp', Horde_Perms::READ),
        );
    }

    public function testShowPermissionGrantedViaHordePermsWithoutAppAuthentication(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->never())->method('isAuthenticated');

        $this->perms->method('exists')->willReturn(true);
        $this->perms->method('hasPermission')
            ->with('imp', 'alice@example.com', Horde_Perms::SHOW)
            ->willReturn(true);

        self::assertTrue(
            $this->registry->hasPermission('imp', Horde_Perms::SHOW),
        );
    }

    public function testCombinedShowAndReadPermissionStillRequiresAuthentication(): void
    {
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        self::assertFalse(
            $this->registry->hasPermission('imp', Horde_Perms::SHOW | Horde_Perms::READ),
        );
    }

    public function testListAppsShowEnumerationSkipsIsAuthenticated(): void
    {
        $this->stubAppsRequireAuth([
            'imp' => true,
            'kronolith' => false,
        ]);

        $this->registry->expects($this->never())->method('isAuthenticated');

        $this->perms->method('exists')->willReturn(true);
        $this->perms->method('hasPermission')->willReturn(true);

        self::assertSame(
            ['imp', 'kronolith'],
            $this->registry->listApps(),
        );
    }

    public function testListAppsReadPassesNotransparentToHasPermission(): void
    {
        $this->registry->applications = [
            'imp' => [
                'status' => 'active',
                'name' => 'Mail',
            ],
        ];
        $this->stubAppsRequireAuth(['imp' => true]);

        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->with([
                'app' => 'imp',
                'notransparent' => true,
            ])
            ->willReturn(false);

        self::assertSame(
            [],
            $this->registry->listApps(null, false, Horde_Perms::READ),
        );
    }
}
