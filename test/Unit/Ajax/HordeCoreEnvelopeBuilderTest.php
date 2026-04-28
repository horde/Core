<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @package   Core
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Ajax;

use Horde\Core\Ajax\HordeCoreEnvelopeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(HordeCoreEnvelopeBuilder::class)]
class HordeCoreEnvelopeBuilderTest extends TestCase
{
    private HordeCoreEnvelopeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HordeCoreEnvelopeBuilder();
    }

    // -----------------------------------------------------------------
    // Envelope structure
    // -----------------------------------------------------------------

    #[Test]
    public function envelopeContainsResponseField(): void
    {
        $envelope = $this->builder->buildEnvelope('hello');

        self::assertSame('hello', $envelope->response);
    }

    #[Test]
    public function envelopeWithNullDataHasNullResponse(): void
    {
        $envelope = $this->builder->buildEnvelope(null);

        self::assertNull($envelope->response);
    }

    #[Test]
    public function envelopeOmitsMsgsWhenEmpty(): void
    {
        $envelope = $this->builder->buildEnvelope('data');

        self::assertObjectNotHasProperty('msgs', $envelope);
    }

    #[Test]
    public function envelopeIncludesMsgsWhenPresent(): void
    {
        $msgs = [
            ['type' => 'horde.success', 'message' => 'Saved', 'flags' => []],
        ];
        $envelope = $this->builder->buildEnvelope('data', msgs: $msgs);

        self::assertCount(1, $envelope->msgs);
        self::assertSame('horde.success', $envelope->msgs[0]['type']);
        self::assertSame('Saved', $envelope->msgs[0]['message']);
    }

    #[Test]
    public function envelopeOmitsTasksWhenNull(): void
    {
        $envelope = $this->builder->buildEnvelope('data');

        self::assertObjectNotHasProperty('tasks', $envelope);
    }

    #[Test]
    public function envelopeOmitsTasksWhenEmptyObject(): void
    {
        $envelope = $this->builder->buildEnvelope('data', tasks: new stdClass());

        self::assertObjectNotHasProperty('tasks', $envelope);
    }

    #[Test]
    public function envelopeIncludesTasksWhenPopulated(): void
    {
        $tasks = new stdClass();
        $tasks->{'horde:sid'} = 'abc123';
        $envelope = $this->builder->buildEnvelope('data', tasks: $tasks);

        self::assertSame('abc123', $envelope->tasks->{'horde:sid'});
    }

    #[Test]
    public function msgsWebnotifyNullIsFilteredOut(): void
    {
        $msgs = [
            ['type' => 'horde.success', 'message' => 'OK', 'flags' => [], 'webnotify' => null],
        ];
        $envelope = $this->builder->buildEnvelope('data', msgs: $msgs);

        self::assertArrayNotHasKey('webnotify', $envelope->msgs[0]);
    }

    #[Test]
    public function msgsWebnotifyValueIsPreserved(): void
    {
        $msgs = [
            ['type' => 'horde.success', 'message' => 'OK', 'flags' => [], 'webnotify' => 'notify-body'],
        ];
        $envelope = $this->builder->buildEnvelope('data', msgs: $msgs);

        self::assertSame('notify-body', $envelope->msgs[0]['webnotify']);
    }

    // -----------------------------------------------------------------
    // XSSI wrapping
    // -----------------------------------------------------------------

    #[Test]
    public function encodeSecureWrapsJsonWithXssiDelimiters(): void
    {
        $envelope = $this->builder->buildEnvelope(true);
        $json = $this->builder->encodeSecure($envelope);

        self::assertStringStartsWith('/*-secure-', $json);
        self::assertStringEndsWith('*/', $json);
    }

    #[Test]
    public function encodeSecureContainsNoLiteralNullBytes(): void
    {
        $envelope = $this->builder->buildEnvelope("before\x00after");
        $json = $this->builder->encodeSecure($envelope);

        self::assertStringNotContainsString("\x00", $json);
        // json_encode escapes the null byte — both parts of the string survive
        self::assertStringContainsString('before', $json);
        self::assertStringContainsString('after', $json);
    }

    // -----------------------------------------------------------------
    // PSR-7 response building
    // -----------------------------------------------------------------

    #[Test]
    public function buildReturnsJsonResponseWithXssiWrapper(): void
    {
        $response = $this->builder->build(data: ['key' => 'value']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringStartsWith('/*-secure-', $body);
        self::assertStringContainsString('"key":"value"', $body);
    }

    #[Test]
    public function buildWithJsonhtmlReturnsHtmlEscapedResponse(): void
    {
        $response = $this->builder->build(data: '<script>alert(1)</script>', jsonhtml: true);

        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringNotContainsString('<script>', $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    // -----------------------------------------------------------------
    // Convenience builders
    // -----------------------------------------------------------------

    #[Test]
    public function buildSessionTimeoutProducesCorrectEnvelope(): void
    {
        $response = $this->builder->buildSessionTimeout('https://example.com/login?reason=session');

        $body = (string) $response->getBody();
        $inner = substr($body, strlen('/*-secure-'), -strlen('*/'));
        $decoded = json_decode($inner, true);

        self::assertFalse($decoded['response']);
        self::assertSame('horde.ajaxtimeout', $decoded['msgs'][0]['type']);
        self::assertStringContainsString('reason=session', $decoded['msgs'][0]['message']);
    }

    #[Test]
    public function buildNoAuthProducesCorrectEnvelope(): void
    {
        $response = $this->builder->buildNoAuth('https://example.com/login?reason=failed');

        $body = (string) $response->getBody();
        $inner = substr($body, strlen('/*-secure-'), -strlen('*/'));
        $decoded = json_decode($inner, true);

        self::assertFalse($decoded['response']);
        self::assertSame('horde.noauth', $decoded['msgs'][0]['type']);
    }

    // -----------------------------------------------------------------
    // Complex envelope round-trip
    // -----------------------------------------------------------------

    #[Test]
    public function fullEnvelopeRoundTrip(): void
    {
        $tasks = new stdClass();
        $tasks->{'imp:viewport'} = ['mailbox' => 'INBOX', 'rownum' => 25];

        $msgs = [
            ['type' => 'horde.success', 'message' => '1 message moved', 'flags' => []],
            ['type' => 'horde.warning', 'message' => 'Quota at 90%', 'flags' => ['alarm']],
        ];

        $response = $this->builder->build(
            data: ['count' => 42],
            tasks: $tasks,
            msgs: $msgs,
        );

        $body = (string) $response->getBody();
        $inner = substr($body, strlen('/*-secure-'), -strlen('*/'));
        $decoded = json_decode($inner, true);

        self::assertSame(42, $decoded['response']['count']);
        self::assertCount(2, $decoded['msgs']);
        self::assertSame('INBOX', $decoded['tasks']['imp:viewport']['mailbox']);
    }
}
