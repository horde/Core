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

namespace Horde\Core\Factory;

use Horde\Http\Server\RequestBuilder;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Injector-managed binding for Psr\Http\Message\ServerRequestInterface.
 *
 * The rampage bootstrap (Horde\Core\RampageBootstrap) pre-populates the
 * request on the injector before dispatch. Legacy entry points using
 * Horde_Registry::appInit() do not. Wiring this factory into the default
 * bindings makes ServerRequestInterface resolvable on every code path so
 * modern PSR-4 classes can accept it through the constructor without
 * touching superglobals themselves.
 *
 * The build path delegates the actual superglobal read to
 * Horde\Http\Server\RequestBuilder::withGlobalVariables(), which is the
 * same builder the rampage bootstrap uses.
 */
class ServerRequestFactory
{
    public function create(Injector $injector): ServerRequestInterface
    {
        return (new RequestBuilder())->withGlobalVariables()->build();
    }
}
