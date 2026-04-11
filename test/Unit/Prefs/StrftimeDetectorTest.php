<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Prefs;

use Horde\Core\Config\PrefsState;
use Horde\Core\Prefs\StrftimeDetector;
use Horde\Core\Prefs\StrftimeFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrftimeDetector::class)]
class StrftimeDetectorTest extends TestCase
{
    private StrftimeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new StrftimeDetector();
    }

    public function testScanReturnsEmptyForCleanPrefs(): void
    {
        $prefs = new PrefsState([
            'some_pref' => [
                'value' => 'not a strftime pattern',
                'type' => 'text',
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertIsArray($findings);
        $this->assertEmpty($findings);
    }

    public function testScanDetectsStrftimeInValue(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'value' => '%Y-%m-%d',
                'type' => 'text',
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertCount(1, $findings);
        $this->assertContainsOnlyInstancesOf(StrftimeFinding::class, $findings);
        $this->assertEquals('date_format', $findings[0]->pref);
        $this->assertEquals('value', $findings[0]->field);
        $this->assertEquals('%Y-%m-%d', $findings[0]->strftime);
        $this->assertEquals('yyyy-MM-dd', $findings[0]->icu);
    }

    public function testScanDetectsStrftimeInEnumKeys(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'type' => 'enum',
                'enum' => [
                    '%Y-%m-%d' => 'ISO Format',
                    '%d.%m.%Y' => 'European Format',
                ],
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertCount(2, $findings);
        $this->assertEquals('enum.key', $findings[0]->field);
        $this->assertEquals('%Y-%m-%d', $findings[0]->strftime);
        $this->assertEquals('%d.%m.%Y', $findings[1]->strftime);
    }

    public function testScanIgnoresEnumKeysWhenDisabled(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'type' => 'enum',
                'enum' => [
                    '%Y-%m-%d' => 'ISO Format',
                ],
            ],
        ]);

        $findings = $this->detector->scan($prefs, null, [
            'check_enum_keys' => false,
        ]);

        $this->assertEmpty($findings);
    }

    public function testScanDetectsEnumValuesWhenEnabled(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'type' => 'enum',
                'enum' => [
                    'iso' => '%Y-%m-%d',
                ],
            ],
        ]);

        $findings = $this->detector->scan($prefs, null, [
            'check_enum_keys' => false,
            'check_enum_values' => true,
        ]);

        $this->assertCount(1, $findings);
        $this->assertEquals('enum.value', $findings[0]->field);
        $this->assertEquals('%Y-%m-%d', $findings[0]->strftime);
    }

    public function testScanIgnoresValueWhenDisabled(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'value' => '%Y-%m-%d',
            ],
        ]);

        $findings = $this->detector->scan($prefs, null, [
            'check_value' => false,
        ]);

        $this->assertEmpty($findings);
    }

    public function testScanFiltersSpecificPrefs(): void
    {
        $prefs = new PrefsState([
            'date_format' => ['value' => '%Y-%m-%d'],
            'time_format' => ['value' => '%H:%M'],
            'other_pref' => ['value' => '%s discount'],
        ]);

        $findings = $this->detector->scan($prefs, ['date_format', 'time_format']);

        $this->assertCount(2, $findings);
        $prefNames = array_map(fn($f) => $f->pref, $findings);
        $this->assertContains('date_format', $prefNames);
        $this->assertContains('time_format', $prefNames);
        $this->assertNotContains('other_pref', $prefNames);
    }

    public function testScanIgnoresClosures(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'value' => fn() => '%Y-%m-%d',  // Closure
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEmpty($findings);
    }

    public function testScanIgnoresNonStringValues(): void
    {
        $prefs = new PrefsState([
            'some_pref' => [
                'value' => 12345,  // Integer
            ],
            'other_pref' => [
                'value' => ['array'],  // Array
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEmpty($findings);
    }

    public function testScanPrefDetectsMultiplePatterns(): void
    {
        $prefDef = [
            'value' => '%Y-%m-%d',
            'enum' => [
                '%d.%m.%Y' => 'European',
                '%m/%d/%Y' => 'US',
            ],
        ];

        $findings = $this->detector->scanPref('date_format', $prefDef);

        $this->assertCount(3, $findings);
        $this->assertEquals('value', $findings[0]->field);
        $this->assertEquals('enum.key', $findings[1]->field);
        $this->assertEquals('enum.key', $findings[2]->field);
    }

    public function testScanArrayConvenienceMethod(): void
    {
        $prefsArray = [
            'date_format' => ['value' => '%Y-%m-%d'],
        ];

        $findings = $this->detector->scanArray($prefsArray);

        $this->assertCount(1, $findings);
        $this->assertEquals('date_format', $findings[0]->pref);
    }

    public function testConfidenceLevelHigh(): void
    {
        $prefs = new PrefsState([
            'date_format' => ['value' => '%Y-%m-%d'],  // 3 specifiers
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEquals(StrftimeFinding::CONFIDENCE_HIGH, $findings[0]->confidence);
    }

    public function testConfidenceLevelMedium(): void
    {
        $prefs = new PrefsState([
            'date_format' => ['value' => '%Y-%m'],  // 2 specifiers
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEquals(StrftimeFinding::CONFIDENCE_MEDIUM, $findings[0]->confidence);
    }

    public function testConfidenceLevelLow(): void
    {
        $prefs = new PrefsState([
            'date_format' => ['value' => '%Y'],  // 1 specifier
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEquals(StrftimeFinding::CONFIDENCE_LOW, $findings[0]->confidence);
    }

    public function testHandlesLocaleSpecificFormats(): void
    {
        $prefs = new PrefsState([
            'date_format' => ['value' => '%x'],  // Locale-specific date
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertCount(1, $findings);
        $this->assertTrue($findings[0]->isLocaleSpecific());
        $this->assertIsArray($findings[0]->icu);
    }

    public function testLocationStringsAreDescriptive(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'value' => '%Y-%m-%d',
                'enum' => [
                    '%d.%m.%Y' => 'European',
                ],
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEquals("date_format['value']", $findings[0]->location);
        $this->assertStringContainsString("date_format['enum']", $findings[1]->location);
    }

    public function testIgnoresNonStrftimePercents(): void
    {
        $prefs = new PrefsState([
            'discount' => ['value' => '50% off'],  // Not strftime
            'tax_rate' => ['value' => '8.5%'],     // Not strftime
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertEmpty($findings);
    }

    public function testMultiplePrefsWithMultipleFindings(): void
    {
        $prefs = new PrefsState([
            'date_format' => [
                'value' => '%Y-%m-%d',
                'enum' => ['%d/%m/%Y' => 'DD/MM/YYYY'],
            ],
            'time_format' => [
                'value' => '%H:%M:%S',
            ],
            'clean_pref' => [
                'value' => 'no patterns here',
            ],
        ]);

        $findings = $this->detector->scan($prefs);

        $this->assertCount(3, $findings);

        $prefCounts = array_count_values(array_map(fn($f) => $f->pref, $findings));
        $this->assertEquals(2, $prefCounts['date_format']);
        $this->assertEquals(1, $prefCounts['time_format']);
        $this->assertArrayNotHasKey('clean_pref', $prefCounts);
    }
}
