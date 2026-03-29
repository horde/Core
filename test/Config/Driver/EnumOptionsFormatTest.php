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

namespace Horde\Core\Test\Config\Driver;

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Test that all ENUM fields use associative arrays with labels.
 *
 * PropertyMetadata expects ENUM options in this format:
 *   options: ['key' => 'Label', ...]
 *
 * Not simple arrays:
 *   options: ['key1', 'key2', ...]
 *
 * This test validates:
 * - ENUM fields have non-empty options
 * - Options are associative arrays (not simple numeric-indexed arrays)
 * - Keys are strings (the actual values)
 * - Values are strings (the display labels)
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class EnumOptionsFormatTest extends TestCase
{
    /**
     * Test that all ENUM fields have properly formatted options arrays.
     */
    #[DataProvider('allDriversProvider')]
    public function testEnumFieldsHaveAssociativeOptionsArray(DriverInterface $driver): void
    {
        $violations = [];
        $fields = $this->extractAllFields($driver);
        $driverClass = get_class($driver);

        foreach ($fields as $field) {
            if ($field->type !== FieldType::ENUM) {
                continue;
            }

            // Check options is not empty
            if (empty($field->options)) {
                $violations[] = [
                    'driver' => $driverClass,
                    'field' => $field->name,
                    'error' => 'ENUM field has empty options array',
                ];
                continue;
            }

            // Check it's associative (not simple array with numeric keys 0,1,2...)
            $keys = array_keys($field->options);
            $expectedNumericKeys = range(0, count($field->options) - 1);

            if ($keys === $expectedNumericKeys) {
                $violations[] = [
                    'driver' => $driverClass,
                    'field' => $field->name,
                    'error' => 'ENUM field uses simple array instead of associative',
                    'current_values' => array_values($field->options),
                    'expected_format' => "['key' => 'Label', ...]",
                    'hint' => 'Convert ["value1", "value2"] to ["value1" => "Label 1", "value2" => "Label 2"]',
                ];
                continue;
            }

            // Check all keys and values are strings
            foreach ($field->options as $key => $label) {
                if (!is_string($key)) {
                    $violations[] = [
                        'driver' => $driverClass,
                        'field' => $field->name,
                        'error' => 'ENUM option key is not a string',
                        'key' => $key,
                        'key_type' => gettype($key),
                    ];
                }
                if (!is_string($label) && $label !== null) {
                    $violations[] = [
                        'driver' => $driverClass,
                        'field' => $field->name,
                        'error' => 'ENUM option label is not a string',
                        'key' => $key,
                        'label' => $label,
                        'label_type' => gettype($label),
                    ];
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf(
                "Found %d ENUM field(s) with invalid options format in %s:\n%s",
                count($violations),
                $driverClass,
                json_encode($violations, JSON_PRETTY_PRINT)
            )
        );
    }

    /**
     * Extract all fields from a driver, including nested conditional fields.
     *
     * @param DriverInterface $driver
     * @return array<PropertyMetadata>
     */
    private function extractAllFields(DriverInterface $driver): array
    {
        $allFields = [];

        foreach ($driver->getFields() as $field) {
            $allFields[] = $field;

            // Also extract nested fields from ConditionalFields
            if ($field->conditionalFields !== null) {
                foreach ($field->conditionalFields->getCases() as $case) {
                    $caseFields = $field->conditionalFields->getFieldsForCase($case);
                    $allFields = array_merge($allFields, $caseFields);
                }
            }
        }

        return $allFields;
    }

    /**
     * Data provider: all driver instances.
     *
     * @return array<array{DriverInterface}>
     */
    public static function allDriversProvider(): array
    {
        try {
            $drivers = [];
            $classes = self::discoverAllDriverClasses();

            foreach ($classes as $class) {
                try {
                    $driver = new $class();
                    $drivers[$class] = [$driver];
                } catch (\Throwable $e) {
                    // Skip drivers that can't be instantiated
                    // (they'll be caught by AllDriversInstantiationTest)
                }
            }

            if (empty($drivers)) {
                throw new \RuntimeException('No drivers discovered - check path');
            }

            return $drivers;
        } catch (\Throwable $e) {
            // If data provider fails, PHPUnit silently returns empty array
            // Force a visible error by throwing
            throw new \RuntimeException(
                'Data provider failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                0,
                $e
            );
        }
    }

    /**
     * Discover all driver classes.
     *
     * @return array<string>
     */
    private static function discoverAllDriverClasses(): array
    {
        $classes = [];
        $path = __DIR__ . '/../../../src/Config/Driver';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();

            if (in_array($filename, [
                'DriverInterface.php',
                'DriverAvailability.php',
                'DriverRepository.php',
            ])) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match('/namespace ([^;]+);/', $content, $nsMatch) &&
                preg_match('/class (\w+)\s+implements\s+DriverInterface/', $content, $clsMatch)
            ) {
                $classes[] = $nsMatch[1] . '\\' . $clsMatch[1];
            }
        }

        sort($classes);

        return $classes;
    }
}
