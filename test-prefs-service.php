<?php
/**
 * Quick test for PrefsService
 *
 * Tests basic prefs read/write functionality.
 */

require_once __DIR__ . '/../../../running/horde/vendor/autoload.php';

define('HORDE_BASE', '/home/i567442/running/horde/vendor/horde/horde');
define('HORDE_CONFIG_BASE', '/home/i567442/running/horde/var/config');

// Initialize DI container
$injector = new Horde_Injector(new Horde_Injector_TopLevel());
$GLOBALS['injector'] = $injector;

// Create registry to trigger factory registrations
$registry = $injector->createInstance('Horde_Registry');

try {
    // Get PrefsService from DI
    $prefsService = $injector->getInstance('Horde\\Core\\Service\\PrefsService');
    echo "✓ PrefsService loaded: " . get_class($prefsService) . "\n";

    $testUser = 'testprefs_' . time();
    $testScope = 'horde';
    $testKey = 'test_key';
    $testValue = 'test_value_' . time();

    // Test 1: Set value
    echo "\n1. Testing setValue()...\n";
    $prefsService->setValue($testUser, $testScope, $testKey, $testValue);
    echo "   Set pref: uid=$testUser, scope=$testScope, key=$testKey, value=$testValue\n";

    // Test 2: Get value
    echo "\n2. Testing getValue()...\n";
    $retrieved = $prefsService->getValue($testUser, $testScope, $testKey);
    echo "   Retrieved: $retrieved\n";
    if ($retrieved === $testValue) {
        echo "   ✓ Value matches!\n";
    } else {
        echo "   ✗ Value mismatch! Expected: $testValue, Got: $retrieved\n";
    }

    // Test 3: Exists check
    echo "\n3. Testing exists()...\n";
    $exists = $prefsService->exists($testUser, $testScope, $testKey);
    echo "   Exists: " . ($exists ? 'yes' : 'no') . "\n";
    if ($exists) {
        echo "   ✓ Pref exists\n";
    } else {
        echo "   ✗ Pref should exist\n";
    }

    // Test 4: Get all in scope
    echo "\n4. Testing getAllInScope()...\n";
    $all = $prefsService->getAllInScope($testUser, $testScope);
    echo "   Found " . count($all) . " prefs in scope\n";
    if (isset($all[$testKey])) {
        echo "   ✓ Test key found in scope\n";
    } else {
        echo "   ✗ Test key not found in scope\n";
    }

    // Test 5: Delete value
    echo "\n5. Testing deleteValue()...\n";
    $prefsService->deleteValue($testUser, $testScope, $testKey);
    $existsAfterDelete = $prefsService->exists($testUser, $testScope, $testKey);
    echo "   Exists after delete: " . ($existsAfterDelete ? 'yes' : 'no') . "\n";
    if (!$existsAfterDelete) {
        echo "   ✓ Pref deleted successfully\n";
    } else {
        echo "   ✗ Pref still exists after delete\n";
    }

    // Test 6: Serialized data (identities use case)
    echo "\n6. Testing serialized data (identities)...\n";
    $identities = [
        ['id' => 'Default', 'fullname' => 'Test User', 'from_addr' => 'test@example.com'],
        ['id' => 'Work', 'fullname' => 'Test User', 'from_addr' => 'test@work.com'],
    ];
    $prefsService->setValue($testUser, $testScope, 'identities', serialize($identities));
    $retrievedSerialized = $prefsService->getValue($testUser, $testScope, 'identities');
    $unserialized = unserialize($retrievedSerialized);
    echo "   Stored " . count($identities) . " identities\n";
    echo "   Retrieved " . count($unserialized) . " identities\n";
    if ($unserialized == $identities) {
        echo "   ✓ Serialized data matches!\n";
    } else {
        echo "   ✗ Serialized data mismatch\n";
    }

    // Cleanup
    $prefsService->deleteValue($testUser, $testScope, 'identities');

    echo "\n✓ All tests passed!\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
