<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\BackendState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BackendState
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(BackendState::class)]
class BackendStateTest extends TestCase
{
    private BackendState $state;

    protected function setUp(): void
    {
        $backends = [
            'hordesql' => [
                'disabled' => false,
                'name' => 'Horde SQL',
                'driver' => 'Sql',
                'params' => ['table' => 'users'],
            ],
            'ldap' => [
                'disabled' => true,
                'name' => 'LDAP Server',
                'driver' => 'Ldap',
                'params' => ['host' => 'localhost'],
            ],
            'poppassd' => [
                'name' => 'Poppassd',
                'driver' => 'Poppassd',
                'params' => ['port' => 106],
            ],
        ];

        $this->state = new BackendState($backends);
    }

    public function testGetBackend(): void
    {
        $backend = $this->state->getBackend('hordesql');

        $this->assertIsArray($backend);
        $this->assertEquals('Horde SQL', $backend['name']);
        $this->assertEquals('Sql', $backend['driver']);
    }

    public function testGetNonexistentBackend(): void
    {
        $backend = $this->state->getBackend('nonexistent');

        $this->assertNull($backend);
    }

    public function testListBackendsExcludesDisabled(): void
    {
        $backends = $this->state->listBackends(false);

        $this->assertCount(2, $backends);
        $this->assertArrayHasKey('hordesql', $backends);
        $this->assertArrayHasKey('poppassd', $backends);
        $this->assertArrayNotHasKey('ldap', $backends);
    }

    public function testListBackendsIncludesDisabled(): void
    {
        $backends = $this->state->listBackends(true);

        $this->assertCount(3, $backends);
        $this->assertArrayHasKey('hordesql', $backends);
        $this->assertArrayHasKey('ldap', $backends);
        $this->assertArrayHasKey('poppassd', $backends);
    }

    public function testListBackendsDefaultExcludesDisabled(): void
    {
        $backends = $this->state->listBackends();

        $this->assertCount(2, $backends);
        $this->assertArrayNotHasKey('ldap', $backends);
    }

    public function testHasBackend(): void
    {
        $this->assertTrue($this->state->hasBackend('hordesql'));
        $this->assertTrue($this->state->hasBackend('ldap'));
        $this->assertFalse($this->state->hasBackend('nonexistent'));
    }

    public function testToArray(): void
    {
        $backends = $this->state->toArray();

        $this->assertIsArray($backends);
        $this->assertCount(3, $backends);
        $this->assertArrayHasKey('hordesql', $backends);
        $this->assertArrayHasKey('ldap', $backends);
        $this->assertArrayHasKey('poppassd', $backends);
    }

    public function testBackendWithoutDisabledFlagIsIncluded(): void
    {
        // Backend without 'disabled' key should be included (not disabled)
        $backends = $this->state->listBackends(false);

        $this->assertArrayHasKey('poppassd', $backends);
    }
}
