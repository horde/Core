<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\LocalAssetFilesystem;
use Horde\Core\Assets\PhpThemeInfoReader;
use Horde\Core\Assets\ThemeInfoReader;
use Horde\Core\Path\PathBuilderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

#[CoversClass(PhpThemeInfoReader::class)]
class PhpThemeInfoReaderTest extends TestCase
{
    private string $root;
    private PathBuilderInterface $pathBuilder;
    private AssetFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/horde-theme-info-' . uniqid('', true);
        $this->pathBuilder = $this->createPathMock($this->root);
        $this->filesystem = new LocalAssetFilesystem();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    #[Test]
    public function implementsInterface(): void
    {
        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertInstanceOf(ThemeInfoReader::class, $reader);
    }

    #[Test]
    public function returnsDeclaredReadableScript(): void
    {
        $this->writeTheme('horde', 'silver', "<?php\n\$theme_scripts = array('theme.js');\n", ['theme.js']);

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame(['theme.js'], $reader->readScripts('horde', 'silver'));
    }

    #[Test]
    public function dropsDeclaredButMissingFile(): void
    {
        $this->writeTheme('horde', 'silver', "<?php\n\$theme_scripts = array('theme.js', 'ghost.js');\n", ['theme.js']);

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame(['theme.js'], $reader->readScripts('horde', 'silver'));
    }

    #[Test]
    public function rejectsUnsafeScriptNames(): void
    {
        $this->writeTheme(
            'horde',
            'silver',
            "<?php\n\$theme_scripts = array('../evil.js', 'a/b.js', 'http://x/y.js', 'theme.css', 'plain', '..js');\n",
            [],
        );

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame([], $reader->readScripts('horde', 'silver'));
    }

    #[Test]
    public function rejectsUnsafeThemeName(): void
    {
        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame([], $reader->readScripts('horde', '../etc'));
        self::assertSame([], $reader->readScripts('horde', 'foo/bar'));
        self::assertSame([], $reader->readScripts('horde', ''));
    }

    #[Test]
    public function missingInfoFileYieldsEmpty(): void
    {
        // Theme dir exists but no info.php.
        $dir = $this->root . '/horde/silver';
        mkdir($dir, 0777, true);

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame([], $reader->readScripts('horde', 'silver'));
    }

    #[Test]
    public function infoWithoutScriptsYieldsEmpty(): void
    {
        $this->writeTheme('horde', 'silver', "<?php\n\$theme_covers = array('imp');\n", []);

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame([], $reader->readScripts('horde', 'silver'));
    }

    #[Test]
    public function resolvesPerApp(): void
    {
        $this->writeTheme('turba', 'silver', "<?php\n\$theme_scripts = array('contacts.js');\n", ['contacts.js']);

        $reader = new PhpThemeInfoReader($this->pathBuilder, $this->filesystem);

        self::assertSame(['contacts.js'], $reader->readScripts('turba', 'silver'));
        self::assertSame([], $reader->readScripts('horde', 'silver'));
    }

    /**
     * @param list<string> $scriptFiles Script files to create alongside info.php.
     */
    private function writeTheme(string $app, string $theme, string $info, array $scriptFiles): void
    {
        $dir = $this->root . '/' . $app . '/' . $theme;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/info.php', $info);
        foreach ($scriptFiles as $file) {
            file_put_contents($dir . '/' . $file, '// js');
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        foreach ($items === false ? [] : $items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }
        rmdir($path);
    }

    private function createPathMock(string $root): PathBuilderInterface
    {
        return new class ($root) implements PathBuilderInterface {
            private string $path = '';

            public function __construct(private readonly string $root) {}

            public function withComponentRoot(): static
            {
                return $this;
            }
            public function withAppFileroot(string $app): static
            {
                return $this;
            }

            public function withAppThemesDir(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->root . '/' . $app;
                return $clone;
            }

            public function withAppJsDir(string $app): static
            {
                return $this;
            }
            public function withStaticDir(): static
            {
                return $this;
            }
            public function withConfigDir(?string $app = null): static
            {
                return $this;
            }
            public function withTmpDir(): static
            {
                return $this;
            }

            public function withSlug(string $slug): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $slug;
                return $clone;
            }

            public function withPart(string $part): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $part;
                return $clone;
            }

            public function toSplFileInfo(): SplFileInfo
            {
                return new SplFileInfo($this->path);
            }
            public function __toString(): string
            {
                return $this->path;
            }
        };
    }
}
