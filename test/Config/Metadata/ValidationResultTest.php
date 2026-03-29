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

use Horde\Core\Config\Metadata\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ValidationResult class.
 *
 * ValidationResult is an immutable value object that encapsulates
 * validation errors from PropertyMetadata validation. Used throughout
 * the Config system for validation flows.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(ValidationResult::class)]
class ValidationResultTest extends TestCase
{
    // ===================================================================
    // A. Valid State (No Errors)
    // ===================================================================

    public function testEmptyConstructorCreatesValidState(): void
    {
        $result = new ValidationResult();

        $this->assertTrue($result->isValid());
        $this->assertIsArray($result->getErrors());
        $this->assertEmpty($result->getErrors());
        $this->assertNull($result->getFirstError());
    }

    public function testExplicitEmptyArrayCreatesValidState(): void
    {
        $result = new ValidationResult([]);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
        $this->assertNull($result->getFirstError());
    }

    // ===================================================================
    // B. Invalid State (With Errors)
    // ===================================================================

    public function testSingleErrorCreatesInvalidState(): void
    {
        $result = new ValidationResult(['Field is required']);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('Field is required', $result->getFirstError());
    }

    public function testMultipleErrorsAllReturned(): void
    {
        $errors = [
            'Field is required',
            'Field must be an integer',
            'Field must be positive',
        ];

        $result = new ValidationResult($errors);

        $this->assertFalse($result->isValid());
        $this->assertCount(3, $result->getErrors());
        $this->assertSame($errors, $result->getErrors());
    }

    public function testGetFirstErrorReturnsFirstOnly(): void
    {
        $errors = [
            'First error',
            'Second error',
            'Third error',
        ];

        $result = new ValidationResult($errors);

        $this->assertSame('First error', $result->getFirstError());
    }

    // ===================================================================
    // C. Edge Cases
    // ===================================================================

    public function testEmptyStringAsError(): void
    {
        $result = new ValidationResult(['']);

        // Empty string is still an error (array not empty)
        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('', $result->getFirstError());
    }

    public function testVeryLongErrorMessage(): void
    {
        $longMessage = str_repeat('Error message with detailed information. ', 50);

        $result = new ValidationResult([$longMessage]);

        $this->assertFalse($result->isValid());
        $this->assertSame($longMessage, $result->getFirstError());
    }

    public function testErrorsAreReadonly(): void
    {
        $errors = ['Original error'];
        $result = new ValidationResult($errors);

        // Modify original array - should not affect result
        $errors[] = 'New error';

        $this->assertCount(1, $result->getErrors());
    }

    // ===================================================================
    // D. Real-World Usage Patterns
    // ===================================================================

    public function testTypicalRequiredFieldError(): void
    {
        $result = new ValidationResult([
            "Property 'username' is required but not provided",
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('username', $result->getFirstError());
        $this->assertStringContainsString('required', $result->getFirstError());
    }

    public function testTypicalTypeValidationError(): void
    {
        $result = new ValidationResult([
            "Property 'port' must be an integer, got: string",
        ]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('port', $result->getFirstError());
        $this->assertStringContainsString('integer', $result->getFirstError());
    }

    public function testMultipleFieldErrors(): void
    {
        $result = new ValidationResult([
            "Property 'username' is required but not provided",
            "Property 'port' must be an integer, got: string",
            "Property 'protocol' must be one of: tcp, unix",
        ]);

        $this->assertFalse($result->isValid());
        $this->assertCount(3, $result->getErrors());

        // First error is about username
        $this->assertStringContainsString('username', $result->getFirstError());
    }
}
