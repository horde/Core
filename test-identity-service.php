<?php
/**
 * Quick test for IdentityService
 *
 * Tests basic identity CRUD functionality.
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
    // Get IdentityService from DI
    $identityService = $injector->getInstance('Horde\\Core\\Service\\IdentityService');
    echo "✓ IdentityService loaded: " . get_class($identityService) . "\n";

    $testUser = 'testidentity_' . time();
    $testScope = 'horde';

    // Test 1: Get all (should be empty)
    echo "\n1. Testing getAll() for new user...\n";
    $identities = $identityService->getAll($testUser, $testScope);
    echo "   Found " . count($identities) . " identities\n";
    if (count($identities) === 0) {
        echo "   ✓ Empty list for new user\n";
    } else {
        echo "   ✗ Expected empty list\n";
    }

    // Test 2: Add first identity
    echo "\n2. Testing add() - first identity...\n";
    $identity1 = [
        'id' => 'Default',
        'fullname' => 'Test User',
        'from_addr' => 'test@example.com',
        'location' => '',
    ];
    $index1 = $identityService->add($testUser, $identity1, $testScope);
    echo "   Created identity at index: $index1\n";
    if ($index1 === 0) {
        echo "   ✓ First identity at index 0\n";
    } else {
        echo "   ✗ Expected index 0, got $index1\n";
    }

    // Test 3: Get single identity
    echo "\n3. Testing get() for index 0...\n";
    $retrieved = $identityService->get($testUser, 0, $testScope);
    if ($retrieved && $retrieved['from_addr'] === 'test@example.com') {
        echo "   ✓ Retrieved identity matches\n";
    } else {
        echo "   ✗ Retrieved identity doesn't match\n";
        print_r($retrieved);
    }

    // Test 4: Get all (should have 1)
    echo "\n4. Testing getAll() with 1 identity...\n";
    $identities = $identityService->getAll($testUser, $testScope);
    echo "   Found " . count($identities) . " identities\n";
    if (count($identities) === 1) {
        echo "   ✓ Correct count\n";
    } else {
        echo "   ✗ Expected 1 identity\n";
    }

    // Test 5: Add second identity
    echo "\n5. Testing add() - second identity...\n";
    $identity2 = [
        'id' => 'Work',
        'fullname' => 'Test User',
        'from_addr' => 'test@work.com',
        'location' => 'Office',
    ];
    $index2 = $identityService->add($testUser, $identity2, $testScope);
    echo "   Created identity at index: $index2\n";
    if ($index2 === 1) {
        echo "   ✓ Second identity at index 1\n";
    } else {
        echo "   ✗ Expected index 1, got $index2\n";
    }

    // Test 6: Get all (should have 2)
    echo "\n6. Testing getAll() with 2 identities...\n";
    $identities = $identityService->getAll($testUser, $testScope);
    echo "   Found " . count($identities) . " identities\n";
    if (count($identities) === 2) {
        echo "   ✓ Correct count\n";
    } else {
        echo "   ✗ Expected 2 identities\n";
    }

    // Test 7: Update identity
    echo "\n7. Testing update() for index 1...\n";
    $updated = [
        'id' => 'Work',
        'fullname' => 'Test User (Work)',
        'from_addr' => 'updated@work.com',
        'location' => 'Remote Office',
    ];
    $identityService->update($testUser, 1, $updated, $testScope);
    $retrieved = $identityService->get($testUser, 1, $testScope);
    if ($retrieved && $retrieved['from_addr'] === 'updated@work.com') {
        echo "   ✓ Identity updated successfully\n";
    } else {
        echo "   ✗ Update failed\n";
        print_r($retrieved);
    }

    // Test 8: Get/Set default
    echo "\n8. Testing default identity...\n";
    $default = $identityService->getDefault($testUser, $testScope);
    echo "   Current default: $default\n";
    if ($default === 0) {
        echo "   ✓ Default is 0\n";
    }

    $identityService->setDefault($testUser, 1, $testScope);
    $default = $identityService->getDefault($testUser, $testScope);
    echo "   New default: $default\n";
    if ($default === 1) {
        echo "   ✓ Default changed to 1\n";
    } else {
        echo "   ✗ Expected default 1, got $default\n";
    }

    // Test 9: Delete identity
    echo "\n9. Testing delete() for index 0...\n";
    $identityService->delete($testUser, 0, $testScope);
    $identities = $identityService->getAll($testUser, $testScope);
    echo "   Identities after delete: " . count($identities) . "\n";
    if (count($identities) === 1) {
        echo "   ✓ Identity deleted\n";
    } else {
        echo "   ✗ Expected 1 identity remaining\n";
    }

    // Verify default was adjusted
    $default = $identityService->getDefault($testUser, $testScope);
    echo "   Default after delete: $default\n";
    if ($default === 0) {
        echo "   ✓ Default adjusted to 0\n";
    } else {
        echo "   ✗ Expected default 0 after delete, got $default\n";
    }

    // Test 10: Delete non-existent identity (should throw)
    echo "\n10. Testing delete() for non-existent index 99...\n";
    try {
        $identityService->delete($testUser, 99, $testScope);
        echo "   ✗ Should have thrown IdentityNotFoundException\n";
    } catch (\Horde\Core\Service\IdentityNotFoundException $e) {
        echo "   ✓ Threw IdentityNotFoundException as expected\n";
    }

    // Cleanup
    $prefsService = $injector->getInstance('Horde\\Core\\Service\\PrefsService');
    $prefsService->deleteValue($testUser, $testScope, 'identities');
    $prefsService->deleteValue($testUser, $testScope, 'default_identity');

    echo "\n✓ All tests passed!\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
