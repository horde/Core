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

namespace Horde\Core\Test\Config\Metadata;

use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConditionalFields class.
 *
 * ConditionalFields manages field definitions that appear conditionally
 * based on SWITCH field values. Used in 9 drivers for features like:
 * - PostgreSQL SSL configuration
 * - LDAP password expiration
 * - MySQL connection type
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(ConditionalFields::class)]
class ConditionalFieldsTest extends TestCase
{
    // ===================================================================
    // A. Construction & Basic Access
    // ===================================================================

    public function testEmptyConstructorCreatesValidInstance(): void
    {
        $conditional = new ConditionalFields();

        $this->assertSame([], $conditional->getCases());
        $this->assertFalse($conditional->hasCase('any'));
    }

    public function testConstructorWithCasesPopulatesCorrectly(): void
    {
        $field1 = new PropertyMetadata(name: 'field1', type: FieldType::TEXT);
        $field2 = new PropertyMetadata(name: 'field2', type: FieldType::INTEGER);

        $conditional = new ConditionalFields([
            'case1' => [$field1],
            'case2' => [$field2],
        ]);

        $this->assertCount(2, $conditional->getCases());
        $this->assertTrue($conditional->hasCase('case1'));
        $this->assertTrue($conditional->hasCase('case2'));
    }

    // ===================================================================
    // B. getFieldsForCase() Method - CRITICAL
    // ===================================================================

    public function testGetFieldsForCaseReturnsCorrectFields(): void
    {
        $field1 = new PropertyMetadata(name: 'sslmode', type: FieldType::ENUM, options: ['require' => 'Require']);
        $field2 = new PropertyMetadata(name: 'sslcert', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$field1, $field2],
        ]);

        $fields = $conditional->getFieldsForCase('true');

        $this->assertCount(2, $fields);
        $this->assertSame($field1, $fields[0]);
        $this->assertSame($field2, $fields[1]);
    }

    public function testGetFieldsForCaseReturnsEmptyArrayForNonExistentCase(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'yes' => [$field],
        ]);

        $fields = $conditional->getFieldsForCase('nonexistent');

        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    public function testGetFieldsForCaseIsCaseSensitive(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$field],
        ]);

        // Should not find 'True' when defined as 'true'
        $fields = $conditional->getFieldsForCase('True');
        $this->assertEmpty($fields);

        // Should find exact match
        $fields = $conditional->getFieldsForCase('true');
        $this->assertCount(1, $fields);
    }

    public function testGetFieldsForCaseWhitespaceSensitivity(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'yes' => [$field],
        ]);

        // Should not find 'yes ' with trailing space
        $fields = $conditional->getFieldsForCase('yes ');
        $this->assertEmpty($fields);

        // Should find exact match
        $fields = $conditional->getFieldsForCase('yes');
        $this->assertCount(1, $fields);
    }

    public function testGetFieldsForCaseNumericCaseValues(): void
    {
        $field0 = new PropertyMetadata(name: 'field0', type: FieldType::TEXT);
        $field1 = new PropertyMetadata(name: 'field1', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            '0' => [$field0],
            '1' => [$field1],
        ]);

        $fields0 = $conditional->getFieldsForCase('0');
        $this->assertCount(1, $fields0);
        $this->assertSame($field0, $fields0[0]);

        $fields1 = $conditional->getFieldsForCase('1');
        $this->assertCount(1, $fields1);
        $this->assertSame($field1, $fields1[0]);
    }

    public function testGetFieldsForCaseEmptyFieldArray(): void
    {
        $conditional = new ConditionalFields([
            'empty' => [],
        ]);

        $fields = $conditional->getFieldsForCase('empty');

        $this->assertIsArray($fields);
        $this->assertEmpty($fields);
    }

    public function testGetFieldsForCaseMultipleFields(): void
    {
        $fields = [];
        for ($i = 0; $i < 5; $i++) {
            $fields[] = new PropertyMetadata(name: "field{$i}", type: FieldType::TEXT);
        }

        $conditional = new ConditionalFields([
            'many' => $fields,
        ]);

        $result = $conditional->getFieldsForCase('many');

        $this->assertCount(5, $result);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($fields[$i], $result[$i]);
        }
    }

    // ===================================================================
    // C. getCases() Method
    // ===================================================================

    public function testGetCasesReturnsAllCaseKeys(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'case1' => [$field],
            'case2' => [$field],
            'case3' => [$field],
        ]);

        $cases = $conditional->getCases();

        $this->assertCount(3, $cases);
        $this->assertContains('case1', $cases);
        $this->assertContains('case2', $cases);
        $this->assertContains('case3', $cases);
    }

    public function testGetCasesReturnsEmptyArrayWhenNoCases(): void
    {
        $conditional = new ConditionalFields([]);

        $cases = $conditional->getCases();

        $this->assertIsArray($cases);
        $this->assertEmpty($cases);
    }

    public function testGetCasesKeysAreStrings(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$field],
            'false' => [$field],
        ]);

        $cases = $conditional->getCases();

        foreach ($cases as $case) {
            $this->assertIsString($case);
        }
    }

    public function testGetCasesPreservesOrder(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'alpha' => [$field],
            'beta' => [$field],
            'gamma' => [$field],
        ]);

        $cases = $conditional->getCases();

        $this->assertSame(['alpha', 'beta', 'gamma'], $cases);
    }

    // ===================================================================
    // D. hasCase() Method
    // ===================================================================

    public function testHasCaseReturnsTrueForExistingCase(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'yes' => [$field],
        ]);

        $this->assertTrue($conditional->hasCase('yes'));
    }

    public function testHasCaseReturnsFalseForNonExistentCase(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'yes' => [$field],
        ]);

        $this->assertFalse($conditional->hasCase('maybe'));
    }

    public function testHasCaseIsCaseSensitive(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'yes' => [$field],
        ]);

        $this->assertFalse($conditional->hasCase('YES'));
        $this->assertTrue($conditional->hasCase('yes'));
    }

    public function testHasCaseEmptyString(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            '' => [$field],
        ]);

        $this->assertTrue($conditional->hasCase(''));
    }

    public function testHasCaseZeroValue(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            '0' => [$field],
        ]);

        $this->assertTrue($conditional->hasCase('0'));
    }

    // ===================================================================
    // E. jsonSerialize() Method - CRITICAL
    // ===================================================================

    public function testJsonSerializeEmptyConditionalFields(): void
    {
        $conditional = new ConditionalFields([]);

        $json = $conditional->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    public function testJsonSerializeWithPropertyMetadata(): void
    {
        $field = new PropertyMetadata(
            name: 'sslmode',
            type: FieldType::ENUM,
            description: 'SSL mode',
            options: ['require' => 'Require']
        );

        $conditional = new ConditionalFields([
            'true' => [$field],
        ]);

        $json = $conditional->jsonSerialize();

        $this->assertArrayHasKey('true', $json);
        $this->assertIsArray($json['true']);
        $this->assertCount(1, $json['true']);

        // Check that PropertyMetadata was serialized
        $serializedField = $json['true'][0];
        $this->assertIsArray($serializedField);
        $this->assertSame('sslmode', $serializedField['name']);
        $this->assertSame('enum', $serializedField['type']);
    }

    public function testJsonSerializeMultipleCases(): void
    {
        $field1 = new PropertyMetadata(name: 'field1', type: FieldType::TEXT);
        $field2 = new PropertyMetadata(name: 'field2', type: FieldType::INTEGER);

        $conditional = new ConditionalFields([
            'case1' => [$field1],
            'case2' => [$field2],
        ]);

        $json = $conditional->jsonSerialize();

        $this->assertCount(2, $json);
        $this->assertArrayHasKey('case1', $json);
        $this->assertArrayHasKey('case2', $json);
    }

    public function testJsonSerializePreservesOrder(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'alpha' => [$field],
            'beta' => [$field],
            'gamma' => [$field],
        ]);

        $json = $conditional->jsonSerialize();

        $keys = array_keys($json);
        $this->assertSame(['alpha', 'beta', 'gamma'], $keys);
    }

    public function testJsonSerializeEmptyFieldArrays(): void
    {
        $conditional = new ConditionalFields([
            'empty' => [],
        ]);

        $json = $conditional->jsonSerialize();

        $this->assertArrayHasKey('empty', $json);
        $this->assertIsArray($json['empty']);
        $this->assertEmpty($json['empty']);
    }

    // ===================================================================
    // F. PropertyMetadata Integration
    // ===================================================================

    public function testConditionalFieldsPassedToPropertyMetadata(): void
    {
        $nestedField = new PropertyMetadata(name: 'nested', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$nestedField],
        ]);

        $property = new PropertyMetadata(
            name: 'ssl',
            type: FieldType::SWITCH,
            conditionalFields: $conditional
        );

        $this->assertSame($conditional, $property->conditionalFields);
    }

    public function testPropertyMetadataJsonSerializeIncludesConditionalFields(): void
    {
        $nestedField = new PropertyMetadata(name: 'nested', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$nestedField],
        ]);

        $property = new PropertyMetadata(
            name: 'ssl',
            type: FieldType::SWITCH,
            conditionalFields: $conditional
        );

        $json = $property->jsonSerialize();

        $this->assertArrayHasKey('conditionalFields', $json);
    }

    public function testPropertyMetadataJsonSerializeNullWhenNoConditionalFields(): void
    {
        $property = new PropertyMetadata(
            name: 'field',
            type: FieldType::TEXT
        );

        $json = $property->jsonSerialize();

        // ConditionalFields key should not be present when null
        $this->assertArrayNotHasKey('conditionalFields', $json);
    }

    // ===================================================================
    // G. Real-World Driver Scenarios - CRITICAL
    // ===================================================================

    public function testPostgreSQLSSLSwitchScenario(): void
    {
        // This is the exact scenario from the user's original bug report
        $sslmode = new PropertyMetadata(
            name: 'sslmode',
            type: FieldType::ENUM,
            description: 'SSL mode',
            default: 'require',
            options: [
                'disable' => 'Disable',
                'allow' => 'Allow',
                'prefer' => 'Prefer',
                'require' => 'Require',
                'verify-ca' => 'Verify CA',
                'verify-full' => 'Verify Full',
            ]
        );

        $sslcert = new PropertyMetadata(name: 'sslcert', type: FieldType::TEXT);
        $sslkey = new PropertyMetadata(name: 'sslkey', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$sslmode, $sslcert, $sslkey],
        ]);

        // When SSL is enabled
        $fields = $conditional->getFieldsForCase('true');
        $this->assertCount(3, $fields);
        $this->assertSame($sslmode, $fields[0]);

        // When SSL is disabled
        $fields = $conditional->getFieldsForCase('false');
        $this->assertEmpty($fields);
    }

    public function testLDAPPasswordExpirationScenario(): void
    {
        $minage = new PropertyMetadata(name: 'password_minage', type: FieldType::INTEGER);
        $maxage = new PropertyMetadata(name: 'password_maxage', type: FieldType::INTEGER);

        $conditional = new ConditionalFields([
            'yes' => [$minage, $maxage],
        ]);

        $fields = $conditional->getFieldsForCase('yes');
        $this->assertCount(2, $fields);

        $fields = $conditional->getFieldsForCase('no');
        $this->assertEmpty($fields);
    }

    public function testMySQLConnectionTypeScenario(): void
    {
        $hostspec = new PropertyMetadata(name: 'hostspec', type: FieldType::TEXT);
        $port = new PropertyMetadata(name: 'port', type: FieldType::INTEGER);
        $socket = new PropertyMetadata(name: 'socket', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'tcp' => [$hostspec, $port],
            'unix' => [$socket],
        ]);

        // TCP connection needs host and port
        $tcpFields = $conditional->getFieldsForCase('tcp');
        $this->assertCount(2, $tcpFields);

        // Unix socket needs socket path
        $unixFields = $conditional->getFieldsForCase('unix');
        $this->assertCount(1, $unixFields);
    }

    public function testBooleanDefaultCastingToString(): void
    {
        // Drivers cast boolean defaults to strings for case lookups
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'true' => [$field],
            'false' => [],
        ]);

        // Simulate driver behavior: (string) true
        $boolValue = true;
        $stringValue = (string) $boolValue; // This becomes '1' in PHP!

        // This is a potential pitfall - PHP casts true to '1', not 'true'
        // Drivers should explicitly use 'true'/'false' strings, not cast booleans
        $this->assertSame('1', $stringValue);
    }

    // ===================================================================
    // H. Edge Cases
    // ===================================================================

    public function testCaseKeysWithSpecialCharacters(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            'verify-ca' => [$field],
            'verify-full' => [$field],
        ]);

        $this->assertTrue($conditional->hasCase('verify-ca'));
        $this->assertTrue($conditional->hasCase('verify-full'));

        $fields = $conditional->getFieldsForCase('verify-ca');
        $this->assertCount(1, $fields);
    }

    public function testUnicodeInCaseValues(): void
    {
        $field = new PropertyMetadata(name: 'field', type: FieldType::TEXT);

        $conditional = new ConditionalFields([
            '日本語' => [$field],
            'مرحبا' => [$field],
        ]);

        $this->assertTrue($conditional->hasCase('日本語'));
        $this->assertTrue($conditional->hasCase('مرحبا'));
    }

    public function testLargeFieldArraysPerCase(): void
    {
        $fields = [];
        for ($i = 0; $i < 50; $i++) {
            $fields[] = new PropertyMetadata(name: "field{$i}", type: FieldType::TEXT);
        }

        $conditional = new ConditionalFields([
            'many' => $fields,
        ]);

        $result = $conditional->getFieldsForCase('many');

        $this->assertCount(50, $result);
    }
}
