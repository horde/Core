<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Prefs;

use Horde\Core\Prefs\StrftimeFinding;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Error;

#[CoversClass(StrftimeFinding::class)]
class StrftimeFindingTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $this->assertEquals('date_format', $finding->pref);
        $this->assertEquals('value', $finding->field);
        $this->assertEquals("date_format['value']", $finding->location);
        $this->assertEquals('%Y-%m-%d', $finding->strftime);
        $this->assertEquals('yyyy-MM-dd', $finding->icu);
        $this->assertEquals(StrftimeFinding::CONFIDENCE_HIGH, $finding->confidence);
    }

    public function testConstructorThrowsOnInvalidConfidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid confidence level: invalid');

        new StrftimeFinding(
            pref: 'test',
            field: 'value',
            location: 'test[value]',
            strftime: '%Y',
            icu: 'yyyy',
            confidence: 'invalid',
        );
    }

    public function testIsLocaleSpecificReturnsFalseForString(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $this->assertFalse($finding->isLocaleSpecific());
    }

    public function testIsLocaleSpecificReturnsTrueForArray(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%x',
            icu: ['en_US' => 'M/d/yy', 'de_DE' => 'dd.MM.yy'],
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $this->assertTrue($finding->isLocaleSpecific());
    }

    public function testGetIcuStringReturnsStringForConcretePattern(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $this->assertEquals('yyyy-MM-dd', $finding->getIcuString());
    }

    public function testGetIcuStringReturnsDescriptionForLocaleSpecific(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%x',
            icu: ['en_US' => 'M/d/yy', 'de_DE' => 'dd.MM.yy'],
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $result = $finding->getIcuString();
        $this->assertStringContainsString('[locale-specific:', $result);
        $this->assertStringContainsString('en_US', $result);
        $this->assertStringContainsString('de_DE', $result);
    }

    public function testFormatReturnsHumanReadableString(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $formatted = $finding->format();

        $this->assertStringContainsString("date_format['value']", $formatted);
        $this->assertStringContainsString('%Y-%m-%d', $formatted);
        $this->assertStringContainsString('yyyy-MM-dd', $formatted);
        $this->assertStringContainsString('high confidence', $formatted);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $array = $finding->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('date_format', $array['pref']);
        $this->assertEquals('value', $array['field']);
        $this->assertEquals("date_format['value']", $array['location']);
        $this->assertEquals('%Y-%m-%d', $array['strftime']);
        $this->assertEquals('yyyy-MM-dd', $array['icu']);
        $this->assertEquals(StrftimeFinding::CONFIDENCE_HIGH, $array['confidence']);
    }

    public function testJsonSerializeReturnsArrayStructure(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        $json = json_encode($finding);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('date_format', $decoded['pref']);
        $this->assertEquals('%Y-%m-%d', $decoded['strftime']);
        $this->assertEquals('yyyy-MM-dd', $decoded['icu']);
    }

    public function testFromArrayCreatesCorrectInstance(): void
    {
        $data = [
            'pref' => 'date_format',
            'field' => 'value',
            'location' => "date_format['value']",
            'strftime' => '%Y-%m-%d',
            'icu' => 'yyyy-MM-dd',
            'confidence' => StrftimeFinding::CONFIDENCE_HIGH,
        ];

        $finding = StrftimeFinding::fromArray($data);

        $this->assertInstanceOf(StrftimeFinding::class, $finding);
        $this->assertEquals('date_format', $finding->pref);
        $this->assertEquals('%Y-%m-%d', $finding->strftime);
        $this->assertEquals('yyyy-MM-dd', $finding->icu);
    }

    public function testReadonlyPropertiesAreImmutable(): void
    {
        $finding = new StrftimeFinding(
            pref: 'date_format',
            field: 'value',
            location: "date_format['value']",
            strftime: '%Y-%m-%d',
            icu: 'yyyy-MM-dd',
            confidence: StrftimeFinding::CONFIDENCE_HIGH,
        );

        // PHP 8.1+ readonly properties throw Error when attempting to modify
        $this->expectException(Error::class);
        $finding->pref = 'modified';
    }

    public function testAllConfidenceLevels(): void
    {
        $levels = [
            StrftimeFinding::CONFIDENCE_HIGH,
            StrftimeFinding::CONFIDENCE_MEDIUM,
            StrftimeFinding::CONFIDENCE_LOW,
        ];

        foreach ($levels as $level) {
            $finding = new StrftimeFinding(
                pref: 'test',
                field: 'value',
                location: 'test[value]',
                strftime: '%Y',
                icu: 'yyyy',
                confidence: $level,
            );

            $this->assertEquals($level, $finding->confidence);
        }
    }
}
