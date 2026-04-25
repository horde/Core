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

namespace Horde\Core\Uri;

use Horde\Url\Url;
use Psr\Http\Message\UriInterface;

interface UriBuilderInterface extends UriInterface
{
    public function withAppWebroot(string $app): static;

    public function withThemesUri(string $app): static;

    public function withJsUri(string $app): static;

    public function withStaticUri(): static;

    public function withNamedRoute(string $app, string $name, array $params = []): static;

    public function withSlug(string $slug): static;

    public function withPart(string $part): static;

    public function toHordeUrl(): Url;
}
