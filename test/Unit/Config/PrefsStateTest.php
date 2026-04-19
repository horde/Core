<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\PrefsState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PrefsState
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(PrefsState::class)]
class PrefsStateTest extends TestCase
{
    private PrefsState $state;

    protected function setUp(): void
    {
        $prefs = [
            'sync_books' => [
                'value' => 'a:0:{}',
                'type' => 'multienum',
                'desc' => 'Select address books',
            ],
            'name_format' => [
                'value' => 'last_first',
                'type' => 'enum',
                'enum' => ['last_first' => 'Last, First', 'first_last' => 'First Last'],
            ],
        ];

        $prefGroups = [
            'addressbooks' => [
                'column' => 'Address Books',
                'label' => 'Address Books',
                'members' => ['sync_books'],
            ],
            'format' => [
                'column' => 'Display',
                'label' => 'Name Format',
                'members' => ['name_format'],
            ],
        ];

        $this->state = new PrefsState($prefs, $prefGroups);
    }

    public function testGetPref(): void
    {
        $pref = $this->state->getPref('sync_books');

        $this->assertIsArray($pref);
        $this->assertEquals('a:0:{}', $pref['value']);
        $this->assertEquals('multienum', $pref['type']);
    }

    public function testGetNonexistentPref(): void
    {
        $pref = $this->state->getPref('nonexistent');

        $this->assertNull($pref);
    }

    public function testListPrefs(): void
    {
        $prefs = $this->state->listPrefs();

        $this->assertCount(2, $prefs);
        $this->assertContains('sync_books', $prefs);
        $this->assertContains('name_format', $prefs);
    }

    public function testGetPrefGroups(): void
    {
        $groups = $this->state->getPrefGroups();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('addressbooks', $groups);
        $this->assertArrayHasKey('format', $groups);
    }

    public function testGetPrefGroup(): void
    {
        $group = $this->state->getPrefGroup('addressbooks');

        $this->assertIsArray($group);
        $this->assertEquals('Address Books', $group['column']);
        $this->assertContains('sync_books', $group['members']);
    }

    public function testGetNonexistentPrefGroup(): void
    {
        $group = $this->state->getPrefGroup('nonexistent');

        $this->assertNull($group);
    }

    public function testHasPref(): void
    {
        $this->assertTrue($this->state->hasPref('sync_books'));
        $this->assertTrue($this->state->hasPref('name_format'));
        $this->assertFalse($this->state->hasPref('nonexistent'));
    }

    public function testToArray(): void
    {
        $array = $this->state->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('_prefs', $array);
        $this->assertArrayHasKey('prefGroups', $array);
        $this->assertCount(2, $array['_prefs']);
        $this->assertCount(2, $array['prefGroups']);
    }

    public function testEmptyPrefGroups(): void
    {
        $state = new PrefsState(['test' => ['value' => 'foo']]);

        $this->assertEquals([], $state->getPrefGroups());
        $this->assertNull($state->getPrefGroup('any'));
    }
}
