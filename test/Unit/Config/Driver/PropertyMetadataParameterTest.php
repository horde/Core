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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Test that all PropertyMetadata instances use correct parameter names.
 *
 * Validates that driver implementations only use documented parameter names
 * in PropertyMetadata constructors. Catches common mistakes like:
 * - enumValues: (should be options:)
 * - allowedValues: (should be options:)
 * - values: (should be options:)
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversNothing]
class PropertyMetadataParameterTest extends TestCase
{
    /**
     * Valid parameter names for PropertyMetadata constructor.
     */
    private const VALID_PARAMETERS = [
        'name',
        'type',
        'description',
        'required',
        'default',
        'options',
        'conditionalFields',
        'validationRules',
    ];

    /**
     * Invalid parameter names that should trigger errors.
     */
    private const INVALID_PARAMETERS = [
        'enumValues' => 'options',
        'allowedValues' => 'options',
        'values' => 'options',
        'enum' => 'type',
        'allowedOptions' => 'options',
    ];

    /**
     * Test that no driver uses invalid parameter names in PropertyMetadata.
     */
    public function testAllPropertyMetadataUseCorrectParameters(): void
    {
        $violations = [];
        $driverClasses = $this->discoverAllDriverClasses();

        foreach ($driverClasses as $class) {
            $reflection = new ReflectionClass($class);
            $file = $reflection->getFileName();
            $content = file_get_contents($file);

            // Check for each invalid parameter
            foreach (self::INVALID_PARAMETERS as $invalidParam => $correctParam) {
                // Match: new PropertyMetadata( ... invalidParam: ...
                $pattern = '/new\s+PropertyMetadata\s*\([^)]*\b' . preg_quote($invalidParam) . '\s*:/s';

                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                        $violations[] = [
                            'class' => basename($file),
                            'line' => $line,
                            'invalid_param' => $invalidParam,
                            'should_be' => $correctParam,
                            'full_class' => $class,
                        ];
                    }
                }
            }
        }

        // Sort violations by file and line for consistent output
        usort($violations, function ($a, $b) {
            $fileCompare = strcmp($a['class'], $b['class']);
            return $fileCompare !== 0 ? $fileCompare : $a['line'] <=> $b['line'];
        });

        $this->assertEmpty(
            $violations,
            sprintf(
                "Found %d invalid parameter name(s) in PropertyMetadata constructors:\n%s",
                count($violations),
                json_encode($violations, JSON_PRETTY_PRINT)
            )
        );
    }

    /**
     * Test that we can detect all parameter names used.
     *
     * This is a diagnostic test to see what parameters are actually being used.
     */
    public function testDiagnosticParameterUsage(): void
    {
        $parameterCounts = [];
        $driverClasses = $this->discoverAllDriverClasses();

        foreach ($driverClasses as $class) {
            $reflection = new ReflectionClass($class);
            $file = $reflection->getFileName();
            $content = file_get_contents($file);

            // Extract all parameter names from PropertyMetadata constructors
            if (preg_match_all(
                '/new\s+PropertyMetadata\s*\(([^)]+)\)/s',
                $content,
                $matches
            )) {
                foreach ($matches[1] as $constructorArgs) {
                    // Extract parameter names (word before colon)
                    if (preg_match_all('/\b(\w+)\s*:/', $constructorArgs, $paramMatches)) {
                        foreach ($paramMatches[1] as $param) {
                            $parameterCounts[$param] = ($parameterCounts[$param] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        arsort($parameterCounts);

        // This test always passes - it's just diagnostic
        // Output parameter counts as a note
        fwrite(STDERR, "\nParameter usage across all drivers:\n");
        fwrite(STDERR, json_encode($parameterCounts, JSON_PRETTY_PRINT) . "\n");

        // Add assertion to prevent risky test warning
        $this->assertNotEmpty($parameterCounts, 'Should have found parameter usage in drivers');
    }

    /**
     * Discover all driver classes that implement DriverInterface.
     *
     * @return array<string> Fully qualified class names
     */
    private function discoverAllDriverClasses(): array
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
