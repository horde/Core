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
 * Minimal substitute for Horde_Registry used in unit tests.
 *
 * Replaces horde/test's Horde_Test_Stub_Registry: covers only the surface
 * Nlsconfig and similar legacy consumers actually invoke (getAuth(),
 * getApp(), and the loadConfigFile()/setConfigFile() pair).
 */
class Registry
{
    public bool $hordeInit = false;

    /** @var array<string, Loadconfig> */
    private array $configObjects = [];

    public function __construct(
        private readonly string $user,
        private readonly string $app,
    ) {}

    public function getAuth(?string $format = null): string
    {
        return $this->user;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    /**
     * Pre-register a Loadconfig object so loadConfigFile() will return it
     * for matching ($confFile, $vars, $app) coordinates.
     */
    public function setConfigFile(
        Loadconfig $loadconfig,
        string $confFile,
        mixed $vars = null,
        ?string $app = null,
    ): void {
        $this->configObjects[$this->configKey($confFile, $vars, $app)] = $loadconfig;
    }

    /**
     * Mirror Horde_Registry::loadConfigFile() signature so consumers that
     * reach into globals can find a pre-seeded Loadconfig.
     */
    public function loadConfigFile(
        string $confFile,
        mixed $vars = null,
        ?string $app = null,
    ): Loadconfig {
        $key = $this->configKey($confFile, $vars, $app);

        return $this->configObjects[$key]
            ?? new Loadconfig((string) $app, $confFile, $vars);
    }

    private function configKey(string $confFile, mixed $vars, ?string $app): string
    {
        return serialize([$confFile, $vars, $app]);
    }
}
