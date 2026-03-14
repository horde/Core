<?php

declare(strict_types=1);

namespace Horde\Core\Config;

use PHPUnit\Framework\TestCase;

/**
 * Tests for RegistryState
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[\PHPUnit\Framework\Attributes\CoversClass(RegistryState::class)]
class RegistryStateTest extends TestCase
{
    private RegistryState $state;

    protected function setUp(): void
    {
        $applications = [
            'horde' => [
                'name' => 'Horde',
                'initial_page' => 'services/portal/index.php',
                'provides' => 'horde',
            ],
            'turba' => [
                'name' => 'Address Book',
                'provides' => ['contacts', 'clients/getClientSource'],
            ],
            'imp' => [
                'name' => 'Mail',
                'provides' => ['mail', 'contacts/favouriteRecipients'],
            ],
        ];

        $this->state = new RegistryState($applications);
    }

    public function testGetApplication(): void
    {
        $app = $this->state->getApplication('turba');

        $this->assertIsArray($app);
        $this->assertEquals('Address Book', $app['name']);
        $this->assertIsArray($app['provides']);
    }

    public function testGetNonexistentApplication(): void
    {
        $app = $this->state->getApplication('nonexistent');

        $this->assertNull($app);
    }

    public function testListApplications(): void
    {
        $apps = $this->state->listApplications();

        $this->assertCount(3, $apps);
        $this->assertContains('horde', $apps);
        $this->assertContains('turba', $apps);
        $this->assertContains('imp', $apps);
    }

    public function testHasApplication(): void
    {
        $this->assertTrue($this->state->hasApplication('horde'));
        $this->assertTrue($this->state->hasApplication('turba'));
        $this->assertFalse($this->state->hasApplication('nonexistent'));
    }

    public function testToArray(): void
    {
        $array = $this->state->toArray();

        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        $this->assertArrayHasKey('horde', $array);
        $this->assertArrayHasKey('turba', $array);
        $this->assertArrayHasKey('imp', $array);
    }
}
