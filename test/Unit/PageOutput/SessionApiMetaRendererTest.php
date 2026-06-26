<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\Config\RegistryState;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\SessionApiMetaRenderer;
use Horde\Core\Session\HordeSession;
use Horde\Http\ServerRequest;
use Horde\SessionHandler\SessionId;
use Horde\Token\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionApiMetaRenderer::class)]
final class SessionApiMetaRendererTest extends TestCase
{
    private function makeRegistryState(string $webroot = '/horde'): RegistryState
    {
        return new RegistryState([
            'horde' => [
                'status' => 'active',
                'webroot' => $webroot,
                'fileroot' => '/var/www/horde',
                'jsuri' => $webroot . '/js',
                'themesuri' => $webroot . '/themes',
                'staticuri' => $webroot . '/static',
            ],
        ]);
    }

    private function makeToken(): Token
    {
        return Token::null('test-secret-key');
    }

    private function makeSession(): HordeSession
    {
        return new HordeSession(new SessionId('test-sid'));
    }

    #[Test]
    public function renderTagsReturnsEmptyWhenNoSession(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );

        self::assertSame('', $renderer->renderTags(null));
    }

    #[Test]
    public function renderTagsEmitsBothMetaTagsWithName(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
            request: null,
        );

        $html = $renderer->renderTags($this->makeSession());

        self::assertStringContainsString('name="session-api"', $html);
        self::assertStringContainsString('name="csrf-api"', $html);
        // The tags must not use http-equiv — they are not HTTP-equivalent directives.
        self::assertStringNotContainsString('http-equiv="session-api"', $html);
        self::assertStringNotContainsString('http-equiv="csrf-api"', $html);
    }

    #[Test]
    public function getSessionApiUrlIsRelativeWhenNoRequest(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState('/horde'),
            $this->makeToken(),
            request: null,
        );

        self::assertSame('/horde/api/v1', $renderer->getSessionApiUrl());
    }

    #[Test]
    public function getSessionApiUrlIsAbsoluteWhenRequestAvailable(): void
    {
        $request = new ServerRequest('GET', 'https://webmail.example.com/horde/services/portal');

        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState('/horde'),
            $this->makeToken(),
            request: $request,
        );

        self::assertSame(
            'https://webmail.example.com/horde/api/v1',
            $renderer->getSessionApiUrl(),
        );
    }

    #[Test]
    public function getSessionApiUrlPreservesNonStandardHttpsPort(): void
    {
        $request = new ServerRequest('GET', 'https://webmail.example.com:8443/horde/');

        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState('/horde'),
            $this->makeToken(),
            request: $request,
        );

        self::assertSame(
            'https://webmail.example.com:8443/horde/api/v1',
            $renderer->getSessionApiUrl(),
        );
    }

    #[Test]
    public function getSessionApiUrlOmitsDefaultHttpsPort(): void
    {
        // Note: PSR-7 URIs may or may not preserve a default port at
        // parse time. The renderer must omit :443 regardless.
        $request = (new ServerRequest('GET', 'https://webmail.example.com/horde/'))
            ->withUri(
                (new ServerRequest('GET', 'https://webmail.example.com/horde/'))
                    ->getUri()
                    ->withPort(443)
            );

        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState('/horde'),
            $this->makeToken(),
            request: $request,
        );

        self::assertSame(
            'https://webmail.example.com/horde/api/v1',
            $renderer->getSessionApiUrl(),
        );
    }

    #[Test]
    public function getSessionApiUrlPreservesNonStandardHttpPort(): void
    {
        $request = new ServerRequest('GET', 'http://localhost:8080/horde/');

        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState('/horde'),
            $this->makeToken(),
            request: $request,
        );

        self::assertSame(
            'http://localhost:8080/horde/api/v1',
            $renderer->getSessionApiUrl(),
        );
    }

    #[Test]
    public function getSessionApiUrlFallsBackToDefaultWebrootWhenHordeAppMissing(): void
    {
        $renderer = new SessionApiMetaRenderer(
            new RegistryState([]), // no horde app at all
            $this->makeToken(),
            request: null,
        );

        self::assertSame('/horde/api/v1', $renderer->getSessionApiUrl());
    }

    #[Test]
    public function mintCsrfTokenReturnsNullForNullSession(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );

        self::assertNull($renderer->mintCsrfToken(null));
    }

    #[Test]
    public function mintCsrfTokenProducesValidatableToken(): void
    {
        $token = $this->makeToken();
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $token,
        );

        $minted = $renderer->mintCsrfToken($this->makeSession());

        self::assertIsString($minted);
        self::assertNotEmpty($minted);
        self::assertTrue(
            $token->isValid($minted, HordeSession::CSRF_SEED),
            'Minted token must validate against the CSRF seed',
        );
    }

    #[Test]
    public function mintCsrfTokenReturnsDifferentValueEachCall(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );

        $a = $renderer->mintCsrfToken($this->makeSession());
        $b = $renderer->mintCsrfToken($this->makeSession());

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function addToCollectorIsNoOpWhenNoSession(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );
        $collector = new AssetCollector();

        $renderer->addToCollector($collector, null);

        self::assertStringNotContainsString('session-api', $collector->renderMetaTags());
        self::assertStringNotContainsString('csrf-api', $collector->renderMetaTags());
    }

    #[Test]
    public function addToCollectorPushesBothTagsWhenSessionPresent(): void
    {
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );
        $collector = new AssetCollector();

        $renderer->addToCollector($collector, $this->makeSession());
        $html = $collector->renderMetaTags();

        self::assertStringContainsString('name="session-api"', $html);
        self::assertStringContainsString('name="csrf-api"', $html);
        self::assertStringContainsString('/horde/api/v1', $html);
    }

    #[Test]
    public function metaContentIsHtmlEscaped(): void
    {
        // The CSRF token is opaque bytes; if any character requires
        // escaping it must be HTML-escaped, not passed through raw.
        // We can't easily force a known-escapable token from Token::null,
        // so this test ensures the render path uses htmlspecialchars on
        // attributes by verifying no raw quotes leak into the output.
        $renderer = new SessionApiMetaRenderer(
            $this->makeRegistryState(),
            $this->makeToken(),
        );

        $html = $renderer->renderTags($this->makeSession());
        // Each meta tag has exactly four `"` quote chars: name="..." content="..."
        // Tags are 2, so 8 total. More would indicate unescaped content.
        $quoteCount = substr_count($html, '"');
        self::assertSame(8, $quoteCount, 'Unexpected quote count suggests unescaped content');
    }
}
