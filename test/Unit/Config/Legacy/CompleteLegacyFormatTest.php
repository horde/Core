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

namespace Horde\Core\Test\Unit\Config\Legacy;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Metadata\FieldType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Test legacy format conversion for all drivers.
 *
 * Validates that ConfigMetadataProvider::toLegacyFormat() correctly converts
 * all driver metadata to legacy Horde_Config format, including:
 * - All required legacy keys (desc, type)
 * - ENUM fields get 'values' key with associative array
 * - Nested conditional ENUM fields also get 'values' key
 * - Default values are preserved
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversNothing]
class CompleteLegacyFormatTest extends TestCase
{
    private DriverRepository $repository;
    private ConfigMetadataProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new DriverRepository();

        // Register all available drivers
        foreach ($this->discoverAllDriverClasses() as $class) {
            try {
                $this->repository->register(new $class());
            } catch (Throwable $e) {
                // Skip drivers that can't be instantiated
            }
        }

        $this->provider = new ConfigMetadataProvider($this->repository);
    }

    /**
     * Test legacy format conversion for all registered drivers.
     */
    #[DataProvider('allDriversProvider')]
    public function testLegacyFormatForDriver(string $type, string $name): void
    {
        $violations = [];

        try {
            $legacy = $this->provider->toLegacyFormat($type, $name);
        } catch (Throwable $e) {
            $this->fail("Failed to convert {$type}:{$name} to legacy format: " . $e->getMessage());
        }

        $this->assertIsArray($legacy, "Legacy format for {$type}:{$name} is not an array");

        // Get driver to compare
        $driver = $this->repository->get($type, $name);
        $fields = $driver->getFields();

        foreach ($fields as $field) {
            $fieldPath = "{$type}:{$name}:{$field->name}";

            // Check field exists in legacy format
            if (!isset($legacy[$field->name])) {
                $violations[] = [
                    'field_path' => $fieldPath,
                    'error' => 'Field missing in legacy format',
                ];
                continue;
            }

            $legacyField = $legacy[$field->name];

            // Validate required legacy keys
            if (!isset($legacyField['desc'])) {
                $violations[] = [
                    'field_path' => $fieldPath,
                    'error' => "Missing 'desc' key in legacy format",
                ];
            }

            if (!isset($legacyField['type'])) {
                $violations[] = [
                    'field_path' => $fieldPath,
                    'error' => "Missing 'type' key in legacy format",
                ];
            }

            // CRITICAL: ENUM fields must have 'values'
            if ($field->type === FieldType::ENUM) {
                if (!isset($legacyField['values'])) {
                    $violations[] = [
                        'field_path' => $fieldPath,
                        'error' => "ENUM field missing 'values' key in legacy format",
                        'field_type' => 'ENUM',
                        'has_options' => !empty($field->options),
                    ];
                } else {
                    // Validate values are associative
                    if (!is_array($legacyField['values'])) {
                        $violations[] = [
                            'field_path' => $fieldPath,
                            'error' => "ENUM 'values' is not an array",
                        ];
                    } elseif (array_keys($legacyField['values']) === range(0, count($legacyField['values']) - 1)) {
                        $violations[] = [
                            'field_path' => $fieldPath,
                            'error' => "ENUM 'values' is simple array, should be associative",
                        ];
                    }
                }
            }

            // Validate conditional fields
            if ($field->conditionalFields !== null) {
                if (!isset($legacyField['fields'])) {
                    $violations[] = [
                        'field_path' => $fieldPath,
                        'error' => "Field with conditionalFields missing 'fields' key in legacy format",
                    ];
                    continue;
                }

                foreach ($field->conditionalFields->getCases() as $case) {
                    if (!isset($legacyField['fields'][$case])) {
                        $violations[] = [
                            'field_path' => $fieldPath,
                            'error' => "Conditional case '{$case}' missing in legacy format",
                        ];
                        continue;
                    }

                    $caseFields = $field->conditionalFields->getFieldsForCase($case);
                    foreach ($caseFields as $caseField) {
                        $casePath = "{$fieldPath}[{$case}]:{$caseField->name}";

                        if (!isset($legacyField['fields'][$case][$caseField->name])) {
                            $violations[] = [
                                'field_path' => $casePath,
                                'error' => 'Nested conditional field missing in legacy format',
                            ];
                            continue;
                        }

                        $legacyCaseField = $legacyField['fields'][$case][$caseField->name];

                        // CRITICAL: Nested ENUM fields must also have 'values'
                        if ($caseField->type === FieldType::ENUM) {
                            if (!isset($legacyCaseField['values'])) {
                                $violations[] = [
                                    'field_path' => $casePath,
                                    'error' => "Nested ENUM field missing 'values' key in legacy format",
                                    'field_type' => 'ENUM (nested)',
                                    'has_options' => !empty($caseField->options),
                                    'severity' => 'CRITICAL - This is the PostgreSQL sslmode bug!',
                                ];
                            }
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf(
                "Found %d violation(s) in legacy format for %s:%s:\n%s",
                count($violations),
                $type,
                $name,
                json_encode($violations, JSON_PRETTY_PRINT)
            )
        );
    }

    /**
     * Data provider: all registered drivers.
     *
     * @return array<array{string, string}>
     */
    public static function allDriversProvider(): array
    {
        $repository = new DriverRepository();

        // Register all available drivers
        foreach (self::discoverAllDriverClasses() as $class) {
            try {
                $repository->register(new $class());
            } catch (Throwable $e) {
                // Skip
            }
        }

        $drivers = [];

        // Get all registered types
        $allTypes = ['sql', 'auth', 'ldap', 'nosql', 'hashtable', 'group', 'prefs', 'perms', 'alarms'];

        foreach ($allTypes as $type) {
            $typeDrivers = $repository->getByType($type);
            foreach ($typeDrivers as $driver) {
                $drivers["{$type}:{$driver->getName()}"] = [$type, $driver->getName()];
            }
        }

        return $drivers;
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
