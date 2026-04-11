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
use Horde\Core\Config\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Test that all driver implementations can be instantiated and return valid PropertyMetadata.
 *
 * This test discovers all DriverInterface implementations and verifies:
 * - They can be instantiated without errors
 * - getFields() can be called without errors
 * - getFields() returns array of PropertyMetadata objects
 *
 * Primary purpose: Catch "Unknown named parameter" errors in PropertyMetadata constructors.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @coversNothing
 */
class AllDriversInstantiationTest extends TestCase
{
    /**
     * Test that every driver can be instantiated and getFields() called.
     */
    public function testAllDriversCanBeInstantiated(): void
    {
        $driverClasses = $this->discoverAllDriverClasses();

        $this->assertGreaterThan(
            30,
            count($driverClasses),
            'Should discover at least 30 driver classes'
        );

        $failures = [];

        foreach ($driverClasses as $class) {
            try {
                $driver = new $class();

                // Verify implements interface
                $this->assertInstanceOf(
                    DriverInterface::class,
                    $driver,
                    "{$class} does not implement DriverInterface"
                );

                // Call getFields() - this is where parameter errors occur
                $fields = $driver->getFields();

                // Verify returns array
                $this->assertIsArray(
                    $fields,
                    "{$class}::getFields() did not return array"
                );

                // Verify all elements are PropertyMetadata
                foreach ($fields as $field) {
                    if (!$field instanceof PropertyMetadata) {
                        $failures[] = [
                            'class' => $class,
                            'error' => 'getFields() returned non-PropertyMetadata object',
                            'type' => get_class($field),
                        ];
                    }
                }
            } catch (Throwable $e) {
                $failures[] = [
                    'class' => $class,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
        }

        $this->assertEmpty(
            $failures,
            "Driver instantiation failures:\n" . json_encode($failures, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Discover all driver classes that implement DriverInterface.
     *
     * @return array<string> Fully qualified class names
     */
    private function discoverAllDriverClasses(): array
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

            // Skip non-driver files
            if (in_array($filename, [
                'DriverInterface.php',
                'DriverAvailability.php',
                'DriverRepository.php',
            ])) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            // Extract namespace and class name
            if (preg_match('/namespace ([^;]+);/', $content, $nsMatch)
                && preg_match('/class (\w+)\s+implements\s+DriverInterface/', $content, $clsMatch)
            ) {
                $classes[] = $nsMatch[1] . '\\' . $clsMatch[1];
            }
        }

        sort($classes);

        return $classes;
    }
}
