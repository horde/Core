<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use Horde_Session;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Horde_Session::$begin must keep reading through to the modern
 * HordeSession even after close() clears the shim's `_active` flag.
 *
 * Read-only service scripts (download, view) call close() right after
 * bootstrap, but close() never clears the underlying session data -
 * only session_write_close() runs. Registry::checkExistingAuth() reads
 * $session->begin for the `session.max_time` check; if that getter
 * collapsed to 0 once `_active` was false, max_time + 0 compares less
 * than the current timestamp for any non-zero max_time, incorrectly
 * failing auth for every closed, read-only request. See horde/imp#88.
 */
class HordeSessionShimBeginTest extends TestCase
{
    private function hordeSession(): HordeSession
    {
        return new HordeSession(new SessionId('begin-test'), []);
    }

    public function testBeginReadsThroughModernSessionWhenInactive(): void
    {
        $modern = $this->hordeSession();
        $modern->setSessionBegin(1700000000);

        $shim = new Horde_Session($modern);

        $active = new ReflectionProperty(Horde_Session::class, '_active');
        $active->setAccessible(true);
        $active->setValue($shim, false);

        self::assertFalse($shim->isActive());
        self::assertSame(1700000000, $shim->begin);
    }

    public function testBeginFallsBackToZeroWhenNeverStarted(): void
    {
        $shim = new Horde_Session($this->hordeSession());

        $active = new ReflectionProperty(Horde_Session::class, '_active');
        $active->setAccessible(true);
        $active->setValue($shim, false);

        self::assertSame(0, $shim->begin);
    }
}
