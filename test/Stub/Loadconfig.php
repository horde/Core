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

namespace Horde\Core\Test\Stub;

/**
 * Minimal substitute for Horde_Registry_Loadconfig used in unit tests.
 *
 * Replaces horde/test's Horde_Test_Stub_Registry_Loadconfig: holds a
 * preloaded $config map keyed by variable name, which is what
 * Horde_Registry_Nlsconfig and similar consumers dereference.
 */
class Loadconfig
{
    public string $app;

    public string $confFile;

    public mixed $vars;

    /** @var array<string,mixed> */
    public array $config = [];

    public function __construct(string $app, string $confFile, mixed $vars)
    {
        $this->app = $app;
        $this->confFile = $confFile;
        $this->vars = $vars;
    }
}
