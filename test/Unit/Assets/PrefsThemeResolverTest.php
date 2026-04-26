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
    private PrefsService $prefs;

    protected function setUp(): void
    {
        $this->prefs = $this->createMock(PrefsService::class);
    }

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
        $resolver = new PrefsThemeResolver($this->prefs);

        self::assertInstanceOf(ThemeResolver::class, $resolver);
    }

    #[Test]
    public function identityWithPrefsReturnsIdentityTheme(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['identity-001', 'horde', 'theme', 'silver'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('silver', $result);
    }

    #[Test]
    public function identityWithoutPrefsFallsToAuthUid(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['identity-001', 'horde', 'theme', null],
            ['admin', 'horde', 'theme', 'dark'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin');

        self::assertSame('dark', $result);
    }

    #[Test]
    public function identityWithoutPrefsNoAuthUidReturnsDefault(): void
    {
        $this->prefs->method('getValue')->willReturn(null);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('default', $result);
    }

    #[Test]
    public function identityWithPrefsIgnoresAuthUid(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['identity-001', 'horde', 'theme', 'silver'],
            ['admin', 'horde', 'theme', 'dark'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin');

        self::assertSame('silver', $result);
    }

    #[Test]
    public function stringUidWithPrefsReturnsTheme(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['john', 'horde', 'theme', 'blue'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve('john');

        self::assertSame('blue', $result);
    }

    #[Test]
    public function stringUidWithoutPrefsReturnsDefault(): void
    {
        $this->prefs->method('getValue')->willReturn(null);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve('john');

        self::assertSame('default', $result);
    }

    #[Test]
    public function customDefaultTheme(): void
    {
        $this->prefs->method('getValue')->willReturn(null);

        $resolver = new PrefsThemeResolver($this->prefs, 'corporate');
        $result = $resolver->resolve('john');

        self::assertSame('corporate', $result);
    }

    #[Test]
    public function customDefaultThemeWithIdentity(): void
    {
        $this->prefs->method('getValue')->willReturn(null);

        $resolver = new PrefsThemeResolver($this->prefs, 'corporate');
        $result = $resolver->resolve($this->makeIdentity());

        self::assertSame('corporate', $result);
    }

    #[Test]
    public function perAppScopeOverride(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['john', 'imp', 'theme', 'mail-dark'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve('john', null, 'imp');

        self::assertSame('mail-dark', $result);
    }

    #[Test]
    public function identityPerAppScopeOverride(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['identity-001', 'turba', 'theme', 'contacts-light'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity(), null, 'turba');

        self::assertSame('contacts-light', $result);
    }

    #[Test]
    public function identityAppScopeFallsToAuthUid(): void
    {
        $this->prefs->method('getValue')->willReturnMap([
            ['identity-001', 'imp', 'theme', null],
            ['admin', 'imp', 'theme', 'mail-corp'],
        ]);

        $resolver = new PrefsThemeResolver($this->prefs);
        $result = $resolver->resolve($this->makeIdentity(), 'admin', 'imp');

        self::assertSame('mail-corp', $result);
    }

    #[Test]
    public function authUidNullDoesNotAttemptLookup(): void
    {
        $calls = [];
        $this->prefs->method('getValue')->willReturnCallback(
            function (string $uid, string $scope, string $key) use (&$calls) {
                $calls[] = $uid;
                return null;
            }
        );

        $resolver = new PrefsThemeResolver($this->prefs);
        $resolver->resolve($this->makeIdentity());

        self::assertSame(['identity-001'], $calls);
    }
}
