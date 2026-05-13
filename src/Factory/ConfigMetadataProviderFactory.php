<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Injector\Injector;

/**
 * Factory for ConfigMetadataProvider.
 *
 * Creates the metadata provider with a fully populated driver repository.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ConfigMetadataProviderFactory
{
    public function __construct(
        private readonly Injector $injector,
    ) {}

    /**
     * Create ConfigMetadataProvider.
     */
    public function create(): ConfigMetadataProvider
    {
        $repository = $this->injector->getInstance(DriverRepository::class);
        return new ConfigMetadataProvider($repository);
    }
}
