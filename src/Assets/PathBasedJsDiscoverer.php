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

namespace Horde\Core\Assets;

use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;

class PathBasedJsDiscoverer implements JsDiscoverer
{
    public function __construct(
        private readonly PathBuilderInterface $pathBuilder,
        private readonly UriBuilderInterface $uriBuilder,
        private readonly AssetFilesystem $filesystem,
    ) {}

    public function resolve(string $file, string $app = 'horde'): ?string
    {
        $fsPath = (string) $this->pathBuilder
            ->withAppJsDir($app)
            ->withPart($file);

        if (!$this->filesystem->fileExists($fsPath)) {
            return null;
        }

        return (string) $this->uriBuilder
            ->withJsUri($app)
            ->withPart($file);
    }

    public function resolveMany(array $files, string $app = 'horde'): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[$file] = $this->resolve($file, $app);
        }

        return $result;
    }

    public function discoverTheme(JsDiscoveryRequest $request): JsDiscoveryResult
    {
        /* This discoverer has no theme knowledge; theme scripts are handled
         * by ThemeJsDiscoverer. */
        return new JsDiscoveryResult([], $request->theme, $request->app);
    }
}
