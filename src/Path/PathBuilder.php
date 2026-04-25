<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Path;

use Composer\InstalledVersions;
use Horde\Core\Config\RegistryState;
use InvalidArgumentException;
use SplFileInfo;

class PathBuilder implements PathBuilderInterface
{
    private string $path;
    private string $base;

    public function __construct(
        private readonly RegistryState $registryState,
        string $path = '',
    ) {
        $this->path = $path;
        $this->base = $path;
    }

    // Base-setting methods — replace path and reset base

    public function withComponentRoot(): static
    {
        $rootPackage = InstalledVersions::getRootPackage();
        $installPath = $rootPackage['install_path'] ?? '';
        return $this->cloneWithBase($installPath);
    }

    public function withAppFileroot(string $app): static
    {
        $appConfig = $this->requireApp($app);
        $fileroot = $appConfig['fileroot']
            ?? throw new InvalidArgumentException("No fileroot for application: $app");
        return $this->cloneWithBase($fileroot);
    }

    public function withAppThemesDir(string $app): static
    {
        $appConfig = $this->requireApp($app);
        $themesFs = $appConfig['themesfs']
            ?? ($appConfig['fileroot'] ?? '') . '/themes';
        return $this->cloneWithBase($themesFs);
    }

    public function withAppJsDir(string $app): static
    {
        $appConfig = $this->requireApp($app);
        $jsFs = $appConfig['jsfs']
            ?? ($appConfig['fileroot'] ?? '') . '/js';
        return $this->cloneWithBase($jsFs);
    }

    public function withStaticDir(): static
    {
        $hordeConfig = $this->requireApp('horde');
        $staticFs = $hordeConfig['staticfs']
            ?? ($hordeConfig['fileroot'] ?? '') . '/static';
        return $this->cloneWithBase($staticFs);
    }

    public function withConfigDir(?string $app = null): static
    {
        $rootPackage = InstalledVersions::getRootPackage();
        $installPath = $rootPackage['install_path'] ?? '';
        $configDir = $installPath . '/var/config';
        if ($app !== null) {
            $configDir .= '/' . $app;
        }
        return $this->cloneWithBase($configDir);
    }

    public function withTmpDir(): static
    {
        $rootPackage = InstalledVersions::getRootPackage();
        $installPath = $rootPackage['install_path'] ?? '';
        return $this->cloneWithBase($installPath . '/var/tmp');
    }

    // Segment-appending methods — append to path, guard against escape

    public function withSlug(string $slug): static
    {
        $newPath = rtrim($this->path, '/') . '/' . trim($slug, '/') . '/';
        $normalized = self::normalizeLexical($newPath);
        $this->guardAgainstEscape($normalized);
        return $this->cloneWithPath($normalized);
    }

    public function withPart(string $part): static
    {
        $newPath = rtrim($this->path, '/') . '/' . ltrim($part, '/');
        $normalized = self::normalizeLexical($newPath);
        $this->guardAgainstEscape($normalized);
        return $this->cloneWithPath($normalized);
    }

    // Emitter

    public function toSplFileInfo(): SplFileInfo
    {
        return new SplFileInfo($this->path);
    }

    // Stringable

    public function __toString(): string
    {
        return $this->path;
    }

    // Internal helpers

    private function requireApp(string $app): array
    {
        $appConfig = $this->registryState->getApplication($app);
        if ($appConfig === null) {
            throw new InvalidArgumentException("Unknown application: $app");
        }
        return $appConfig;
    }

    private function cloneWithBase(string $path): static
    {
        $normalized = self::normalizeLexical($path);
        $clone = clone $this;
        $clone->path = $normalized;
        $clone->base = $normalized;
        return $clone;
    }

    private function cloneWithPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    private function guardAgainstEscape(string $normalizedPath): void
    {
        if ($this->base === '') {
            return;
        }
        $baseNormalized = rtrim(self::normalizeLexical($this->base), '/');
        $pathCheck = rtrim($normalizedPath, '/');

        if (!str_starts_with($pathCheck, $baseNormalized)) {
            throw new PathNormalizationException(
                "Path '$normalizedPath' escapes base directory '$this->base'"
            );
        }
    }

    private static function normalizeLexical(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $isAbsolute = $path[0] === '/';
        $hadTrailingSlash = str_ends_with($path, '/') && strlen($path) > 1;

        $path = (string) preg_replace('#/{2,}#', '/', $path);
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if (count($normalized) > 0 && end($normalized) !== '..') {
                    array_pop($normalized);
                } elseif (!$isAbsolute) {
                    $normalized[] = '..';
                }
            } else {
                $normalized[] = $part;
            }
        }

        $result = implode('/', $normalized);
        if ($isAbsolute) {
            $result = '/' . $result;
        }
        if ($hadTrailingSlash && $result !== '/') {
            $result .= '/';
        }

        return $result ?: ($isAbsolute ? '/' : '.');
    }
}
