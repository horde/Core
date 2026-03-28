<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Config\Metadata;

use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyMetadata::class)]
class PropertyMetadataTest extends TestCase
{
    public function testBasicPropertyCreation(): void
    {
        $property = new PropertyMetadata(
            name: 'username',
            type: FieldType::TEXT,
            description: 'Database username',
            required: true,
        );

        $this->assertEquals('username', $property->name);
        $this->assertEquals(FieldType::TEXT, $property->type);
        $this->assertEquals('Database username', $property->description);
        $this->assertTrue($property->required);
    }

    public function testRequiredFieldValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'username',
            type: FieldType::TEXT,
            required: true,
        );

        $result = $property->validate(null);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('required', $result->getFirstError());
    }

    public function testOptionalFieldValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'port',
            type: FieldType::INTEGER,
            required: false,
        );

        $result = $property->validate(null);
        $this->assertTrue($result->isValid());
    }

    public function testIntegerTypeValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'port',
            type: FieldType::INTEGER,
        );

        // Valid integer
        $result = $property->validate(3306);
        $this->assertTrue($result->isValid());

        // Valid numeric string
        $result = $property->validate('3306');
        $this->assertTrue($result->isValid());

        // Invalid non-numeric
        $result = $property->validate('not-a-number');
        $this->assertFalse($result->isValid());
    }

    public function testBooleanTypeValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'ssl',
            type: FieldType::BOOLEAN,
        );

        // Valid boolean
        $result = $property->validate(true);
        $this->assertTrue($result->isValid());

        // Valid truthy values
        $result = $property->validate(1);
        $this->assertTrue($result->isValid());

        $result = $property->validate('true');
        $this->assertTrue($result->isValid());

        // Invalid value
        $result = $property->validate('maybe');
        $this->assertFalse($result->isValid());
    }

    public function testEnumTypeValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'protocol',
            type: FieldType::ENUM,
            options: [
                'tcp' => 'TCP/IP',
                'unix' => 'Unix Socket',
            ],
        );

        // Valid option
        $result = $property->validate('tcp');
        $this->assertTrue($result->isValid());

        // Invalid option
        $result = $property->validate('http');
        $this->assertFalse($result->isValid());
    }

    public function testMultiEnumTypeValidation(): void
    {
        $property = new PropertyMetadata(
            name: 'features',
            type: FieldType::MULTI_ENUM,
            options: [
                'ssl' => 'SSL Support',
                'compression' => 'Compression',
                'replication' => 'Replication',
            ],
        );

        // Valid array
        $result = $property->validate(['ssl', 'compression']);
        $this->assertTrue($result->isValid());

        // Invalid - not an array
        $result = $property->validate('ssl');
        $this->assertFalse($result->isValid());

        // Invalid - contains invalid option
        $result = $property->validate(['ssl', 'invalid']);
        $this->assertFalse($result->isValid());
    }

    public function testJsonSerialization(): void
    {
        $property = new PropertyMetadata(
            name: 'username',
            type: FieldType::TEXT,
            description: 'Database username',
            required: true,
            default: 'root',
        );

        $json = $property->jsonSerialize();

        $this->assertEquals('username', $json['name']);
        $this->assertEquals('text', $json['type']);
        $this->assertEquals('Database username', $json['description']);
        $this->assertTrue($json['required']);
        $this->assertEquals('root', $json['default']);
    }
}
