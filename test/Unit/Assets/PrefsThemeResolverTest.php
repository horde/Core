<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\PrefsThemeResolver;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Service\PrefsService;
use Horde\Identity\Identity;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

#[CoversClass(PrefsThemeResolver::class)]
class PrefsThemeResolverTest extends TestCase
{
    private function makeIdentity(string $id = 'identity-001'): Identity
    {
        return new Identity(
            id: $id,
            role: IdentityRole::Principal,
            status: IdentityStatus::Active,
            displayName: 'Test User',
            primaryEmail: 'test@example.com',
            emails: ['test@example.com'],
            supersededBy: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }

    #[Test]
    public function implementsThemeResolver(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->never())->method($this->anything());

        $resolver = new PrefsThemeResolver($prefs);

        self::assertInstanceOf(ThemeResolver::class, $resolver);
    }

    #[Test]
    public function identityWithPrefsReturnsIdentityTheme(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('identity-001', 'horde', 'theme')
            ->willReturn('silver');

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('silver', $result);
    }

    #[Test]
    public function identityWithoutPrefsFallsToAuthUid(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['identity-001', 'horde', 'theme', null],
                ['admin', 'horde', 'theme', 'dark'],
            ]);

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin');

        self::assertSame('dark', $result);
    }

    #[Test]
    public function identityWithoutPrefsNoAuthUidReturnsDefault(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('identity-001', 'horde', 'theme')
            ->willReturn(null);

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('default', $result);
    }

    #[Test]
    public function identityWithPrefsIgnoresAuthUid(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('identity-001', 'horde', 'theme')
            ->willReturn('silver');

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin');

        self::assertSame('silver', $result);
    }

    #[Test]
    public function stringUidWithPrefsReturnsTheme(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('john', 'horde', 'theme')
            ->willReturn('blue');

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve('john');

        self::assertSame('blue', $result);
    }

    #[Test]
    public function stringUidWithoutPrefsReturnsDefault(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('john', 'horde', 'theme')
            ->willReturn(null);

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve('john');

        self::assertSame('default', $result);
    }

    #[Test]
    public function customDefaultTheme(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('john', 'horde', 'theme')
            ->willReturn(null);

        $resolver = new PrefsThemeResolver($prefs, 'corporate');
        $result = $resolver->resolve('john');

        self::assertSame('corporate', $result);
    }

    #[Test]
    public function customDefaultThemeWithIdentity(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('identity-001', 'horde', 'theme')
            ->willReturn(null);

        $resolver = new PrefsThemeResolver($prefs, 'corporate');
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('corporate', $result);
    }

    #[Test]
    public function perAppScopeOverride(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('john', 'imp', 'theme')
            ->willReturn('mail-dark');

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve('john', null, 'imp');

        self::assertSame('mail-dark', $result);
    }

    #[Test]
    public function identityPerAppScopeOverride(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('identity-001', 'turba', 'theme')
            ->willReturn('contacts-light');

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity(), null, 'turba');

        self::assertSame('contacts-light', $result);
    }

    #[Test]
    public function identityAppScopeFallsToAuthUid(): void
    {
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['identity-001', 'imp', 'theme', null],
                ['admin', 'imp', 'theme', 'mail-corp'],
            ]);

        $resolver = new PrefsThemeResolver($prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin', 'imp');

        self::assertSame('mail-corp', $result);
    }

    #[Test]
    public function authUidNullDoesNotAttemptLookup(): void
    {
        $calls = [];
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->willReturnCallback(
                function (string $uid, string $scope, string $key) use (&$calls) {
                    $calls[] = $uid;
                    return null;
                }
            );

        $resolver = new PrefsThemeResolver($prefs);
        $resolver->resolve($this->makeIdentity());

        self::assertSame(['identity-001'], $calls);
    }
}
