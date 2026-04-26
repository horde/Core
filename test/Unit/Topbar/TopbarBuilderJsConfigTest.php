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
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarBuilder::class)]
class TopbarBuilderJsConfigTest extends TestCase
{
    private function createBuilder(
        ?Horde_Registry $registry = null,
        ?PrefsService $prefs = null,
        ?PermissionService $permissions = null,
        ?HordeSession $session = null,
    ): TopbarBuilder {
        $registry ??= $this->createMock(Horde_Registry::class);
        $prefs ??= $this->createMock(PrefsService::class);
        $permissions ??= $this->createMock(PermissionService::class);
        $session ??= $this->createMock(HordeSession::class);

        return new TopbarBuilder($registry, $prefs, $permissions, $session);
    }

    private function registryWithAjaxLink(): Horde_Registry
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('callByPackage')->willReturn([]);
        $registry->method('getServiceLink')->willReturnCallback(function ($service) {
            if ($service === 'ajax') {
                return new Url('/horde/services/ajax.php');
            }
            throw new Horde_Exception('No service');
        });
        return $registry;
    }

    #[Test]
    public function jsConfigContainsApp(): void
    {
        $registry = $this->registryWithAjaxLink();
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build('imp');

        self::assertArrayHasKey('app', $data->jsConfig);
        self::assertSame('imp', $data->jsConfig['app']);
    }

    #[Test]
    public function jsConfigContainsUriAjax(): void
    {
        $registry = $this->registryWithAjaxLink();
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build('horde');

        self::assertArrayHasKey('URI_AJAX', $data->jsConfig);
        self::assertStringContainsString('ajax', $data->jsConfig['URI_AJAX']);
    }

    #[Test]
    public function jsConfigContainsHash(): void
    {
        $registry = $this->registryWithAjaxLink();
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build('horde');

        self::assertArrayHasKey('hash', $data->jsConfig);
        self::assertSame(32, strlen($data->jsConfig['hash']));
    }

    #[Test]
    public function jsConfigContainsFormat(): void
    {
        $registry = $this->registryWithAjaxLink();
        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturnCallback(function ($uid, $app, $key) {
            if ($key === 'date_format') {
                return '%B %d, %Y';
            }
            return null;
        });
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $builder = $this->createBuilder(registry: $registry, prefs: $prefs, session: $session);
        $data = $builder->build('horde');

        self::assertArrayHasKey('format', $data->jsConfig);
        self::assertSame('MMMM dd, yyyy', $data->jsConfig['format']);
    }

    #[Test]
    public function jsConfigContainsRefresh(): void
    {
        $registry = $this->registryWithAjaxLink();
        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturnCallback(function ($uid, $app, $key) {
            if ($key === 'menu_refresh_time') {
                return '300';
            }
            return null;
        });
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $builder = $this->createBuilder(registry: $registry, prefs: $prefs, session: $session);
        $data = $builder->build('horde');

        self::assertArrayHasKey('refresh', $data->jsConfig);
        self::assertSame(300, $data->jsConfig['refresh']);
    }

    #[Test]
    public function jsConfigFiltersFalsyValues(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('callByPackage')->willReturn([]);
        $registry->method('getServiceLink')->willThrowException(new Horde_Exception('No service'));

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build('horde');

        self::assertArrayNotHasKey('URI_AJAX', $data->jsConfig);
        self::assertArrayNotHasKey('refresh', $data->jsConfig);
    }
}
