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

class CascadeGraphicDiscoverer implements GraphicDiscoverer
{
    public function __construct(
        private readonly PathBuilderInterface $pathBuilder,
        private readonly UriBuilderInterface $uriBuilder,
        private readonly AssetFilesystem $filesystem,
    ) {}

    public function resolve(string $file, string $theme = 'default', string $app = 'horde'): ?string
    {
        $candidates = [];

        if ($app !== 'horde' && $theme !== 'default') {
            $candidates[] = [$app, $theme];
        }
        if ($app !== 'horde') {
            $candidates[] = [$app, 'default'];
        }
        if ($theme !== 'default') {
            $candidates[] = ['horde', $theme];
        }
        $candidates[] = ['horde', 'default'];

        $graphicPart = 'graphics/' . $file;

        foreach ($candidates as [$candidateApp, $candidateTheme]) {
            $fsPath = (string) $this->pathBuilder
                ->withAppThemesDir($candidateApp)
                ->withSlug($candidateTheme)
                ->withPart($graphicPart);

            if ($this->filesystem->fileExists($fsPath)) {
                return (string) $this->uriBuilder
                    ->withThemesUri($candidateApp)
                    ->withSlug($candidateTheme)
                    ->withPart($graphicPart);
            }
        }

        return null;
    }
}
