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

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\ConfigStateWithMetadata;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for ConfigStateWithMetadata.
 *
 * ConfigStateWithMetadata extends State to add metadata queries and
 * validation support. It integrates with ConfigMetadataProvider to
 * access driver schemas and validate configuration values.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(ConfigStateWithMetadata::class)]
class ConfigStateWithMetadataTest extends TestCase
{
    // ===================================================================
    // A. Inheritance from State (3 tests)
    // ===================================================================

    public function testInheritsGetMethod(): void
    {
        $config = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ];

        $state = new ConfigStateWithMetadata($config);

        // Can use get() from parent State
        $this->assertSame('localhost', $state->get('database.host'));
        $this->assertSame(3306, $state->get('database.port'));
    }

    public function testInheritsHasMethod(): void
    {
        $config = [
            'database' => [
                'host' => 'localhost',
            ],
        ];

        $state = new ConfigStateWithMetadata($config);

        // Can use has() from parent State
        $this->assertTrue($state->has('database.host'));
        $this->assertFalse($state->has('database.nonexistent'));
    }

    public function testInheritsToArrayMethod(): void
    {
        $config = [
            'database' => [
                'host' => 'localhost',
            ],
        ];

        $state = new ConfigStateWithMetadata($config);

        // Can use toArray() from parent State
        $array = $state->toArray();
        $this->assertSame($config, $array);
    }

    // ===================================================================
    // B. No Provider Scenario (2 tests)
    // ===================================================================

    public function testGetMetadataWithNoProviderReturnsNull(): void
    {
        $config = [
            'sql' => [
                'username' => 'root',
            ],
        ];

        $state = new ConfigStateWithMetadata($config, null);

        $metadata = $state->getMetadata('sql.username', 'sql', 'mysql');

        $this->assertNull($metadata);
    }

    public function testValidateWithNoProviderReturnsError(): void
    {
        $config = [
            'sql' => [
                'username' => 'root',
            ],
        ];

        $state = new ConfigStateWithMetadata($config, null);

        $result = $state->validate('sql', 'mysql');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Metadata provider not available', $result->getFirstError());
    }

    // ===================================================================
    // C. With Provider (5 tests)
    // ===================================================================

    public function testGetMetadataCallsProvider(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        // Provider returns validation result (for existence check)
        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', [])
            ->willReturn(new ValidationResult([]));

        // Provider returns schema with fields
        $provider->expects($this->once())
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'fields' => [
                    [
                        'name' => 'username',
                        'type' => 'text',
                        'description' => 'Database username',
                        'required' => true,
                    ],
                ],
            ]);

        $config = ['sql' => ['username' => 'root']];
        $state = new ConfigStateWithMetadata($config, $provider);

        $metadata = $state->getMetadata('sql.username', 'sql', 'mysql');

        $this->assertInstanceOf(PropertyMetadata::class, $metadata);
        $this->assertSame('username', $metadata->name);
    }

    public function testGetMetadataReturnsNullForNonExistentField(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $provider->expects($this->once())
            ->method('validateConfig')
            ->willReturn(new ValidationResult([]));

        $provider->expects($this->once())
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'fields' => [
                    [
                        'name' => 'username',
                        'type' => 'text',
                    ],
                ],
            ]);

        $config = ['sql' => []];
        $state = new ConfigStateWithMetadata($config, $provider);

        // Request nonexistent field
        $metadata = $state->getMetadata('sql.nonexistent', 'sql', 'mysql');

        $this->assertNull($metadata);
    }

    public function testGetMetadataHandlesProviderException(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        // Provider throws exception
        $provider->expects($this->once())
            ->method('validateConfig')
            ->willThrowException(new RuntimeException('Driver not found'));

        $config = ['sql' => []];
        $state = new ConfigStateWithMetadata($config, $provider);

        // Should handle exception and return null
        $metadata = $state->getMetadata('sql.username', 'sql', 'invalid');

        $this->assertNull($metadata);
    }

    public function testValidateCallsProvider(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $config = [
            'sql' => [
                'username' => 'root',
                'password' => 'secret',
            ],
        ];

        $expectedResult = new ValidationResult([]);

        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', $config['sql'])
            ->willReturn($expectedResult);

        $state = new ConfigStateWithMetadata($config, $provider);
        $result = $state->validate('sql', 'mysql');

        $this->assertSame($expectedResult, $result);
    }

    public function testValidateReturnsErrorForNonArrayConfig(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        // Provider should never be called for validation error
        $provider->expects($this->never())
            ->method($this->anything());

        // Config value is not an array
        $config = [
            'sql' => 'invalid',
        ];

        $state = new ConfigStateWithMetadata($config, $provider);
        $result = $state->validate('sql', 'mysql');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not an array', $result->getFirstError());
    }

    // ===================================================================
    // D. Field Name Extraction (3 tests)
    // ===================================================================

    public function testFieldNameExtractionFromDotNotation(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', [])
            ->willReturn(new ValidationResult([]));

        $provider->expects($this->once())
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'fields' => [
                    [
                        'name' => 'port',
                        'type' => 'int',
                    ],
                ],
            ]);

        $config = ['sql' => ['port' => 3306]];
        $state = new ConfigStateWithMetadata($config, $provider);

        // Key is 'sql.mysql.port', should extract 'port'
        $metadata = $state->getMetadata('sql.mysql.port', 'sql', 'mysql');

        $this->assertInstanceOf(PropertyMetadata::class, $metadata);
        $this->assertSame('port', $metadata->name);
    }

    public function testFieldNameExtractionNoDots(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', [])
            ->willReturn(new ValidationResult([]));

        $provider->expects($this->once())
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'fields' => [
                    [
                        'name' => 'ield',  // After strrpos('.') + 1 on 'field', it returns 'ield'
                        'type' => 'text',
                    ],
                ],
            ]);

        $config = ['sql' => []];
        $state = new ConfigStateWithMetadata($config, $provider);

        // Key is just 'field', no dots
        // strrpos('field', '.') returns false, false + 1 = 1, so substr('field', 1) = 'ield'
        $metadata = $state->getMetadata('field', 'sql', 'mysql');

        $this->assertInstanceOf(PropertyMetadata::class, $metadata);
        $this->assertSame('ield', $metadata->name);
    }

    public function testFieldNameExtractionDeepNesting(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', [])
            ->willReturn(new ValidationResult([]));

        $provider->expects($this->once())
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'fields' => [
                    [
                        'name' => 'username',
                        'type' => 'text',
                    ],
                ],
            ]);

        $config = ['sql' => []];
        $state = new ConfigStateWithMetadata($config, $provider);

        // Deep nesting: 'a.b.c.d.username'
        $metadata = $state->getMetadata('a.b.c.d.username', 'sql', 'mysql');

        $this->assertInstanceOf(PropertyMetadata::class, $metadata);
        $this->assertSame('username', $metadata->name);
    }

    // ===================================================================
    // E. Integration Tests (2 tests)
    // ===================================================================

    public function testIntegrationWithMockProviderReturnsMetadata(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $provider->expects($this->exactly(2))
            ->method('validateConfig')
            ->with('sql', 'mysql', [])
            ->willReturn(new ValidationResult([]));

        $provider->expects($this->exactly(2))
            ->method('getDriverSchema')
            ->with('sql', 'mysql')
            ->willReturn([
                'name' => 'MySQL',
                'fields' => [
                    [
                        'name' => 'username',
                        'type' => 'text',
                        'description' => 'Database username',
                        'required' => true,
                    ],
                    [
                        'name' => 'password',
                        'type' => 'password',
                        'description' => 'Database password',
                        'required' => true,
                    ],
                ],
            ]);

        $config = [
            'sql' => [
                'username' => 'root',
                'password' => 'secret',
            ],
        ];

        $state = new ConfigStateWithMetadata($config, $provider);

        // Get metadata for username field
        $usernameMetadata = $state->getMetadata('sql.username', 'sql', 'mysql');
        $this->assertInstanceOf(PropertyMetadata::class, $usernameMetadata);
        $this->assertSame('username', $usernameMetadata->name);
        $this->assertTrue($usernameMetadata->required);

        // Get metadata for password field
        $passwordMetadata = $state->getMetadata('sql.password', 'sql', 'mysql');
        $this->assertInstanceOf(PropertyMetadata::class, $passwordMetadata);
        $this->assertSame('password', $passwordMetadata->name);
    }

    public function testValidationIntegration(): void
    {
        $provider = $this->createMock(ConfigMetadataProvider::class);

        $validConfig = [
            'sql' => [
                'username' => 'root',
                'password' => 'secret',
            ],
        ];

        // Mock validateConfig to return success
        $provider->expects($this->once())
            ->method('validateConfig')
            ->with('sql', 'mysql', $validConfig['sql'])
            ->willReturn(new ValidationResult([]));

        $state = new ConfigStateWithMetadata($validConfig, $provider);
        $result = $state->validate('sql', 'mysql');

        $this->assertTrue($result->isValid());
    }
}
