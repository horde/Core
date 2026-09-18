<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\ActiveSync\Ops;

use Horde\ActiveSync\Ops\HealthStatus;
use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Horde\Core\ActiveSync\Ops\SnapshotCriteria
 */
final class SnapshotCriteriaTest extends TestCase
{
    public function testRejectsUnknownHealthStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SnapshotCriteria(healthMin: 'unknown');
    }

    public function testRejectsUnknownSort(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SnapshotCriteria(sort: 'unknown');
    }

    public function testAcceptsHealthConstantsAndSortConstants(): void
    {
        $criteria = new SnapshotCriteria(
            healthMin: HealthStatus::CRITICAL,
            sort: SnapshotCriteria::SORT_HEALTH
        );

        self::assertSame(HealthStatus::CRITICAL, $criteria->healthMin);
        self::assertSame(SnapshotCriteria::SORT_HEALTH, $criteria->sort);
    }

    public function testMapsActiveWithinToHealthOptions(): void
    {
        $options = (new SnapshotCriteria(activeWithin: 42))
            ->toHealthOptions(123456);

        self::assertSame(42, $options->activeWithin);
        self::assertSame(123456, $options->now());
    }

    public function testNullActiveWithinUsesHealthDefault(): void
    {
        $options = (new SnapshotCriteria())->toHealthOptions(123456);

        self::assertSame(300, $options->activeWithin);
    }
}
