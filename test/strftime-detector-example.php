<?php

/**
 * Example usage of StrftimeDetector
 *
 * This script demonstrates how to use the Horde\Core\Prefs\StrftimeDetector
 * to scan preference definitions for deprecated strftime patterns.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Prefs\StrftimeDetector;
use Horde\Core\Prefs\StrftimeFinding;

// Example 1: Scan a PrefsState object
echo "=== Example 1: Scan PrefsState ===\n\n";

$loader = new PrefsConfigLoader(
    __DIR__ . '/../../../turba/config',
    __DIR__ . '/../../../turba'
);

try {
    $prefsState = $loader->load('turba');
    $detector = new StrftimeDetector();
    $findings = $detector->scan($prefsState, ['date_format', 'time_format']);

    if (empty($findings)) {
        echo "✅ No strftime patterns found\n";
    } else {
        echo '⚠️  Found ' . count($findings) . " strftime pattern(s):\n\n";
        foreach ($findings as $finding) {
            echo '  • ' . $finding->format() . "\n";
        }
    }
} catch (Exception $e) {
    echo 'Error loading prefs: ' . $e->getMessage() . "\n";
}

// Example 2: Scan raw array
echo "\n\n=== Example 2: Scan raw array ===\n\n";

$prefsArray = [
    'date_format' => [
        'value' => '%Y-%m-%d',
        'type' => 'enum',
        'enum' => [
            '%Y-%m-%d' => 'ISO 8601',
            '%d.%m.%Y' => 'European',
            '%m/%d/%Y' => 'US',
        ],
        'desc' => 'Date format',
    ],
    'time_format' => [
        'value' => '%H:%M:%S',
        'type' => 'text',
    ],
    'clean_pref' => [
        'value' => 'no patterns here',
    ],
];

$detector = new StrftimeDetector();
$findings = $detector->scanArray($prefsArray);

echo 'Found ' . count($findings) . " strftime pattern(s):\n\n";
foreach ($findings as $finding) {
    echo sprintf(
        "  • Pref: %s\n"
        . "    Field: %s\n"
        . "    Strftime: %s\n"
        . "    ICU: %s\n"
        . "    Confidence: %s\n\n",
        $finding->pref,
        $finding->field,
        $finding->strftime,
        $finding->getIcuString(),
        $finding->confidence
    );
}

// Example 3: Filter by confidence level
echo "\n=== Example 3: Filter by confidence ===\n\n";

$highConfidence = array_filter(
    $findings,
    fn($f) => $f->confidence === StrftimeFinding::CONFIDENCE_HIGH
);

echo 'High confidence findings: ' . count($highConfidence) . "\n";
foreach ($highConfidence as $finding) {
    echo '  • ' . $finding->format() . "\n";
}

// Example 4: JSON output
echo "\n\n=== Example 4: JSON output ===\n\n";

echo json_encode([
    'status' => 'success',
    'count' => count($findings),
    'findings' => $findings,
], JSON_PRETTY_PRINT) . "\n";
