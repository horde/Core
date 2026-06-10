<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Topbar;

use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Url\Url;
use Horde_Exception;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarBuilder::class)]
class TopbarBuilderJsConfigTest extends TestCase
{
    /**
     * Registry mock that returns the standard portal/version values, an
     * empty app list, and routes only the 'ajax' service link to a real URL.
     * The common build() reads (get, listApps, isAdmin, callByPackage,
     * getServiceLink) are pinned as `atLeastOnce`.
     */
    private function registryWithAjaxLink(): MockObject
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->atLeastOnce())->method('get')->willReturn('/horde');
        $registry->expects($this->atLeastOnce())->method('listApps')->willReturn([]);
        $registry->expects($this->atLeastOnce())->method('isAdmin')->willReturn(false);
        $registry->expects($this->atLeastOnce())->method('callByPackage')->willReturn([]);
        $registry->expects($this->atLeastOnce())
            ->method('getServiceLink')
            ->willReturnCallback(function ($service) {
                if ($service === 'ajax') {
                    return new Url('/horde/services/ajax.php');
                }
                throw new Horde_Exception('No service');
            });

        return $registry;
    }

    private function authedSession(string $uid = 'testuser'): MockObject
    {
        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())->method('getAuthId')->willReturn($uid);

        return $session;
    }

    #[Test]
    public function jsConfigContainsApp(): void
    {
        $builder = new TopbarBuilder(
            $this->registryWithAjaxLink(),
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $this->authedSession(),
        );
        $data = $builder->build('imp');

        self::assertArrayHasKey('app', $data->jsConfig);
        self::assertSame('imp', $data->jsConfig['app']);
    }

    #[Test]
    public function jsConfigContainsUriAjax(): void
    {
        $builder = new TopbarBuilder(
            $this->registryWithAjaxLink(),
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $this->authedSession(),
        );
        $data = $builder->build('horde');

        self::assertArrayHasKey('URI_AJAX', $data->jsConfig);
        self::assertStringContainsString('ajax', $data->jsConfig['URI_AJAX']);
    }

    #[Test]
    public function jsConfigContainsHash(): void
    {
        $builder = new TopbarBuilder(
            $this->registryWithAjaxLink(),
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $this->authedSession(),
        );
        $data = $builder->build('horde');

        self::assertArrayHasKey('hash', $data->jsConfig);
        self::assertSame(32, strlen($data->jsConfig['hash']));
    }

    #[Test]
    public function jsConfigContainsFormat(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->atLeastOnce())
            ->method('getValue')
            ->willReturnCallback(function ($uid, $app, $key) {
                if ($key === 'date_format') {
                    return '%B %d, %Y';
                }
                return null;
            });

        $builder = new TopbarBuilder(
            $this->registryWithAjaxLink(),
            $prefs,
            $this->createStub(PermissionService::class),
            $this->authedSession(),
        );
        $data = $builder->build('horde');

        self::assertArrayHasKey('format', $data->jsConfig);
        self::assertSame('MMMM dd, yyyy', $data->jsConfig['format']);
    }

    #[Test]
    public function jsConfigContainsRefresh(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->atLeastOnce())
            ->method('getValue')
            ->willReturnCallback(function ($uid, $app, $key) {
                if ($key === 'menu_refresh_time') {
                    return '300';
                }
                return null;
            });

        $builder = new TopbarBuilder(
            $this->registryWithAjaxLink(),
            $prefs,
            $this->createStub(PermissionService::class),
            $this->authedSession(),
        );
        $data = $builder->build('horde');

        self::assertArrayHasKey('refresh', $data->jsConfig);
        self::assertSame(300, $data->jsConfig['refresh']);
    }

    #[Test]
    public function jsConfigFiltersFalsyValues(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->atLeastOnce())->method('get')->willReturn('/horde');
        $registry->expects($this->atLeastOnce())->method('listApps')->willReturn([]);
        $registry->expects($this->atLeastOnce())->method('isAdmin')->willReturn(false);
        $registry->expects($this->atLeastOnce())->method('callByPackage')->willReturn([]);
        $registry->method('getServiceLink')->willThrowException(new Horde_Exception('No service'));

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
        $data = $builder->build('horde');

        self::assertArrayNotHasKey('URI_AJAX', $data->jsConfig);
        self::assertArrayNotHasKey('refresh', $data->jsConfig);
    }
}
