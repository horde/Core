<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core;

use Horde;
use Horde_Injector;
use Horde\Injector\Injector;
use Horde_Log;
use Horde_Shutdown_Task;
use Horde\Injector\BindingMapWriter;

/**
 * Shutdown task that dumps the Injector's binding map to a PHP file.
 *
 * Activated by the HORDE_INJECTOR_PROFILE environment variable.
 * The output file is loadBindings()-compatible and opcache-friendly.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class InjectorProfilingShutdownTask implements Horde_Shutdown_Task
{
    public function __construct(
        private readonly Horde_Injector|Injector $injector,
        private readonly string $outputPath,
    ) {}

    public function shutdown(): void
    {
        $writer = new BindingMapWriter();
        $result = $writer->extractBindings($this->injector);
        $writer->write($this->outputPath, $result['cacheable']);

        if ($result['uncacheable'] !== []) {
            Horde::log(
                'Injector profiling: ' . count($result['uncacheable'])
                . ' uncacheable bindings: ' . implode(', ', $result['uncacheable']),
                Horde_Log::DEBUG
            );
        }

        Horde::log(
            'Injector profiling: wrote ' . count($result['cacheable'])
            . ' bindings to ' . $this->outputPath,
            Horde_Log::INFO
        );
    }
}
