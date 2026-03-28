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

use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use Horde_Injector;

/**
 * Factory for DriverRepository.
 *
 * Bootstraps the driver repository with all available drivers.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DriverRepositoryFactory
{
    public function __construct(
        private readonly Horde_Injector $injector,
    ) {}

    /**
     * Create and populate DriverRepository.
     */
    public function create(): DriverRepository
    {
        $repository = new DriverRepository();

        // Register built-in SQL drivers
        $repository->register(new MySQLDriver());

        // Future: Auto-discover drivers from applications
        // Future: Allow apps to register custom drivers via hooks

        return $repository;
    }
}
