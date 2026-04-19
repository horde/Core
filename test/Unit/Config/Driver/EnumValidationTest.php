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

namespace Horde\Core\Test\Unit\Config\Driver;

use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Test that ENUM fields can validate their values correctly.
 *
 * This test verifies:
 * - Valid enum values pass validation
 * - Invalid enum values fail validation
 * - Error messages are informative
 *
 * Tests all real ENUM fields from all drivers to ensure the validation
 * logic works with actual driver implementations.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversNothing]
class EnumValidationTest extends TestCase
{
    /**
     * Test ENUM field validation with valid values.
     */
    #[DataProvider('enumFieldsProvider')]
    public function testEnumFieldAcceptsValidValue(
        string $driverClass,
        string $fieldName,
        PropertyMetadata $field
    ): void {
        // Use default if available, or first option
        $validValue = $field->default ?? array_key_first($field->options);

        if ($validValue === null) {
            $this->markTestSkipped("ENUM field {$fieldName} has no default or options");
        }

        $result = $field->validate($validValue);

        $this->assertTrue(
            $result->isValid(),
            sprintf(
                "Valid enum value '%s' failed validation for %s::%s. Errors: %s",
                $validValue,
                $driverClass,
                $fieldName,
                implode(', ', $result->getErrors())
            )
        );
    }

    /**
     * Test ENUM field validation with all valid values.
     */
    #[DataProvider('enumFieldsProvider')]
    public function testEnumFieldAcceptsAllValidValues(
        string $driverClass,
        string $fieldName,
        PropertyMetadata $field
    ): void {
        if (empty($field->options)) {
            $this->markTestSkipped("ENUM field {$fieldName} has no options");
        }

        foreach (array_keys($field->options) as $validValue) {
            $result = $field->validate($validValue);

            $this->assertTrue(
                $result->isValid(),
                sprintf(
                    "Valid enum value '%s' failed validation for %s::%s",
                    $validValue,
                    $driverClass,
                    $fieldName
                )
            );
        }
    }

    /**
     * Test ENUM field validation rejects invalid values.
     */
    #[DataProvider('enumFieldsProvider')]
    public function testEnumFieldRejectsInvalidValue(
        string $driverClass,
        string $fieldName,
        PropertyMetadata $field
    ): void {
        // Generate an invalid value
        $invalidValue = 'definitely_not_a_valid_option_' . uniqid();

        $result = $field->validate($invalidValue);

        $this->assertFalse(
            $result->isValid(),
            sprintf(
                "Invalid enum value '%s' should fail validation for %s::%s",
                $invalidValue,
                $driverClass,
                $fieldName
            )
        );
    }

    /**
     * Test ENUM validation error messages are informative.
     */
    #[DataProvider('enumFieldsProvider')]
    public function testEnumValidationErrorMessageIsInformative(
        string $driverClass,
        string $fieldName,
        PropertyMetadata $field
    ): void {
        $invalidValue = 'invalid_value_' . uniqid();

        $result = $field->validate($invalidValue);

        if (!$result->isValid()) {
            $error = $result->getFirstError();

            $this->assertNotEmpty($error, 'Error message should not be empty');

            // Error should mention the field name
            $this->assertStringContainsString(
                $fieldName,
                $error,
                'Error message should mention field name'
            );

            // Error should indicate it's about valid options
            $this->assertStringContainsString(
                'must be one of',
                $error,
                'Error message should list available options'
            );
        } else {
            $this->fail('Validation should have failed for invalid value');
        }
    }

    /**
     * Data provider: all ENUM fields from all drivers.
     *
     * @return array<string, array{string, string, PropertyMetadata}>
     */
    public static function enumFieldsProvider(): array
    {
        $enumFields = [];
        $driverClasses = self::discoverAllDriverClasses();

        foreach ($driverClasses as $class) {
            try {
                $driver = new $class();
                $fields = self::extractAllFieldsRecursive($driver);

                foreach ($fields as $field) {
                    if ($field->type === FieldType::ENUM) {
                        $key = basename(str_replace('\\', '/', $class)) . '::' . $field->name;
                        $enumFields[$key] = [
                            $class,
                            $field->name,
                            $field,
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Skip drivers that can't be instantiated
            }
        }

        return $enumFields;
    }

    /**
     * Extract all fields recursively including nested conditional fields.
     *
     * @param object $driver
     * @return array<PropertyMetadata>
     */
    private static function extractAllFieldsRecursive(object $driver): array
    {
        $allFields = [];

        foreach ($driver->getFields() as $field) {
            $allFields[] = $field;

            // Extract nested conditional fields
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
     * Discover all driver classes.
     *
     * @return array<string>
     */
    private static function discoverAllDriverClasses(): array
    {
        $classes = [];
        $path = __DIR__ . '/../../../../src/Config/Driver';

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

            if (preg_match('/namespace ([^;]+);/', $content, $nsMatch)
                && preg_match('/class (\w+)\s+implements\s+DriverInterface/', $content, $clsMatch)
            ) {
                $classes[] = $nsMatch[1] . '\\' . $clsMatch[1];
            }
        }

        return $classes;
    }
}
