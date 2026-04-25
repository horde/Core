<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Path;

use Horde\Core\Config\RegistryState;
use Horde\Core\Path\PathBuilder;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Path\PathNormalizationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Stringable;

#[CoversClass(PathBuilder::class)]
class PathBuilderTest extends TestCase
{
    private RegistryState $registry;

    protected function setUp(): void
    {
        $this->registry = new RegistryState([
            'horde' => [
                'fileroot' => '/srv/www/horde',
                'staticfs' => '/srv/www/horde/static',
            ],
            'kronolith' => [
                'fileroot' => '/srv/www/horde/kronolith',
                'themesfs' => '/srv/www/horde/kronolith/themes',
                'jsfs' => '/srv/www/horde/kronolith/js',
            ],
            'turba' => [
                'fileroot' => '/srv/www/horde/turba',
            ],
        ]);
    }

    private function builder(string $path = ''): PathBuilder
    {
        return new PathBuilder($this->registry, $path);
    }

    // --- Interface compliance ---

    #[Test]
    public function implementsPathBuilderInterface(): void
    {
        self::assertInstanceOf(PathBuilderInterface::class, $this->builder());
    }

    #[Test]
    public function implementsStringable(): void
    {
        self::assertInstanceOf(Stringable::class, $this->builder());
    }

    // --- Immutability ---

    #[Test]
    public function withMethodsDoNotMutateOriginal(): void
    {
        $original = $this->builder('/base');
        $modified = $original->withAppFileroot('kronolith');

        self::assertSame('/base', (string) $original);
        self::assertSame('/srv/www/horde/kronolith', (string) $modified);
    }

    // --- Lexical normalization ---

    #[Test]
    #[DataProvider('normalizationProvider')]
    public function normalizationCases(string $input, string $expected): void
    {
        $builder = $this->builder($input);
        self::assertSame($expected, (string) $builder->withSlug('test'));
        // We verify the base path was normalized by checking through withSlug.
        // But for direct path normalization, let's just verify __toString on
        // an appFileroot-derived builder.
    }

    public static function normalizationProvider(): array
    {
        return [
            'double slashes' => ['/foo//bar', '/foo/bar/test/'],
            'dot segments' => ['/foo/./bar', '/foo/bar/test/'],
            'dotdot segments' => ['/foo/baz/../bar', '/foo/bar/test/'],
            'trailing slash preserved' => ['/foo/bar/', '/foo/bar/test/'],
        ];
    }

    #[Test]
    public function normalizesDoubleSlashesInBase(): void
    {
        $registry = new RegistryState([
            'myapp' => ['fileroot' => '/srv//www///myapp'],
        ]);
        $builder = new PathBuilder($registry);

        $result = $builder->withAppFileroot('myapp');
        self::assertSame('/srv/www/myapp', (string) $result);
    }

    #[Test]
    public function normalizesDotSegmentsInBase(): void
    {
        $registry = new RegistryState([
            'myapp' => ['fileroot' => '/srv/www/./myapp'],
        ]);
        $builder = new PathBuilder($registry);

        $result = $builder->withAppFileroot('myapp');
        self::assertSame('/srv/www/myapp', (string) $result);
    }

    #[Test]
    public function normalizesDotDotInBase(): void
    {
        $registry = new RegistryState([
            'myapp' => ['fileroot' => '/srv/www/other/../myapp'],
        ]);
        $builder = new PathBuilder($registry);

        $result = $builder->withAppFileroot('myapp');
        self::assertSame('/srv/www/myapp', (string) $result);
    }

    #[Test]
    public function normalizesEmptyPath(): void
    {
        $builder = $this->builder('');
        self::assertSame('', (string) $builder);
    }

    #[Test]
    public function normalizesRootPath(): void
    {
        $registry = new RegistryState([
            'root' => ['fileroot' => '/'],
        ]);
        $builder = new PathBuilder($registry);
        $result = $builder->withAppFileroot('root');
        self::assertSame('/', (string) $result);
    }

    // --- withAppFileroot ---

    #[Test]
    public function withAppFilerootFromRegistry(): void
    {
        $result = $this->builder()->withAppFileroot('kronolith');
        self::assertSame('/srv/www/horde/kronolith', (string) $result);
    }

    #[Test]
    public function withAppFilerootThrowsForUnknownApp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder()->withAppFileroot('nonexistent');
    }

    #[Test]
    public function withAppFilerootThrowsWhenNoFileroot(): void
    {
        $registry = new RegistryState([
            'minimal' => ['name' => 'Minimal'],
        ]);
        $builder = new PathBuilder($registry);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No fileroot');
        $builder->withAppFileroot('minimal');
    }

    // --- withAppThemesDir ---

    #[Test]
    public function withAppThemesDirFromRegistry(): void
    {
        $result = $this->builder()->withAppThemesDir('kronolith');
        self::assertSame('/srv/www/horde/kronolith/themes', (string) $result);
    }

    #[Test]
    public function withAppThemesDirDefaultsFromFileroot(): void
    {
        $result = $this->builder()->withAppThemesDir('turba');
        self::assertSame('/srv/www/horde/turba/themes', (string) $result);
    }

    // --- withAppJsDir ---

    #[Test]
    public function withAppJsDirFromRegistry(): void
    {
        $result = $this->builder()->withAppJsDir('kronolith');
        self::assertSame('/srv/www/horde/kronolith/js', (string) $result);
    }

    #[Test]
    public function withAppJsDirDefaultsFromFileroot(): void
    {
        $result = $this->builder()->withAppJsDir('turba');
        self::assertSame('/srv/www/horde/turba/js', (string) $result);
    }

    // --- withStaticDir ---

    #[Test]
    public function withStaticDirFromRegistry(): void
    {
        $result = $this->builder()->withStaticDir();
        self::assertSame('/srv/www/horde/static', (string) $result);
    }

    #[Test]
    public function withStaticDirDefaultsFromHordeFileroot(): void
    {
        $registry = new RegistryState([
            'horde' => ['fileroot' => '/opt/horde'],
        ]);
        $builder = new PathBuilder($registry);

        $result = $builder->withStaticDir();
        self::assertSame('/opt/horde/static', (string) $result);
    }

    // --- withSlug ---

    #[Test]
    public function withSlugAppendsWithTrailingSlash(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('templates');

        self::assertSame('/srv/www/horde/kronolith/templates/', (string) $result);
    }

    #[Test]
    public function withSlugTrimsInputSlashes(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('/templates/');

        self::assertSame('/srv/www/horde/kronolith/templates/', (string) $result);
    }

    #[Test]
    public function withSlugChaining(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('templates')
            ->withSlug('partials');

        self::assertSame('/srv/www/horde/kronolith/templates/partials/', (string) $result);
    }

    // --- withPart ---

    #[Test]
    public function withPartAppendsWithoutTrailingSlash(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withPart('config.php');

        self::assertSame('/srv/www/horde/kronolith/config.php', (string) $result);
    }

    #[Test]
    public function withPartTrimsLeadingSlash(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withPart('/config.php');

        self::assertSame('/srv/www/horde/kronolith/config.php', (string) $result);
    }

    #[Test]
    public function withSlugThenPart(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('config')
            ->withPart('conf.php');

        self::assertSame('/srv/www/horde/kronolith/config/conf.php', (string) $result);
    }

    // --- Base escape guard ---

    #[Test]
    public function withPartThrowsOnEscapePastBase(): void
    {
        $builder = $this->builder()->withAppFileroot('kronolith');

        $this->expectException(PathNormalizationException::class);
        $builder->withPart('../../etc/passwd');
    }

    #[Test]
    public function withSlugThrowsOnEscapePastBase(): void
    {
        $builder = $this->builder()->withAppFileroot('kronolith');

        $this->expectException(PathNormalizationException::class);
        $builder->withSlug('../../etc');
    }

    #[Test]
    public function dotDotWithinBaseIsAllowed(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('templates')
            ->withPart('../config.php');

        // /srv/www/horde/kronolith/templates/../config.php normalizes to
        // /srv/www/horde/kronolith/config.php — still within base
        self::assertSame('/srv/www/horde/kronolith/config.php', (string) $result);
    }

    #[Test]
    public function noBaseAllowsAnyPath(): void
    {
        $builder = $this->builder();
        $result = $builder->withPart('somewhere/deep');

        // No base set, so no guard — path is just appended and normalized
        self::assertSame('/somewhere/deep', (string) $result);
    }

    // --- toSplFileInfo ---

    #[Test]
    public function toSplFileInfoReturnsSplFileInfo(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->toSplFileInfo();

        self::assertInstanceOf(SplFileInfo::class, $result);
        self::assertSame('/srv/www/horde/kronolith', $result->getPathname());
    }

    // --- __toString ---

    #[Test]
    public function toStringReturnsPath(): void
    {
        $builder = $this->builder('/some/path');
        self::assertSame('/some/path', (string) $builder);
    }

    #[Test]
    public function emptyBuilderReturnsEmptyString(): void
    {
        self::assertSame('', (string) $this->builder());
    }

    // --- Base reset on base-setters ---

    #[Test]
    public function baseSetterResetsBase(): void
    {
        $builder = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('deep')
            ->withSlug('nested');

        // Now switch to a different app — base resets
        $switched = $builder->withAppFileroot('turba');
        self::assertSame('/srv/www/horde/turba', (string) $switched);

        // Deep traversal from new base should throw
        $this->expectException(PathNormalizationException::class);
        $switched->withPart('../../escape');
    }

    // --- Full chain ---

    #[Test]
    public function fullChain(): void
    {
        $result = $this->builder()
            ->withAppFileroot('kronolith')
            ->withSlug('themes')
            ->withSlug('default')
            ->withPart('screen.css');

        self::assertSame(
            '/srv/www/horde/kronolith/themes/default/screen.css',
            (string) $result
        );
    }
}
