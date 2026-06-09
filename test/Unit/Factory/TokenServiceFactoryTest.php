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

namespace Horde\Core\Test\Unit\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Factory\TokenServiceFactory;
use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use Horde\Token\Token;
use Horde_Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TokenServiceFactory::class)]
class TokenServiceFactoryTest extends TestCase
{
    /** @param array<string, mixed> $conf */
    private function makeFactory(array $conf): TokenServiceFactory
    {
        $loader = $this->createStub(ConfigLoader::class);
        $loader->method('load')->willReturn(new State($conf));

        $injector = $this->createStub(Horde_Injector::class);
        $injector->method('getInstance')->willReturnMap([
            [ConfigLoader::class, $loader],
        ]);

        return new TokenServiceFactory($injector);
    }

    public function testCreateFromSecretReturnsToken(): void
    {
        $factory = $this->makeFactory(['token' => ['driver' => 'Null']]);
        $token = $factory->createFromSecret('explicit-secret');

        $this->assertInstanceOf(Token::class, $token);

        // Round-trip: tokens minted with explicit secret validate against same.
        $generated = $token->generate('seed');
        $this->assertTrue($token->isValid($generated->token, 'seed'));
    }

    public function testCreateForSessionPersistsSecretAcrossCalls(): void
    {
        $factory = $this->makeFactory(['token' => ['driver' => 'Null']]);
        $session = new HordeSession(new SessionId('s1'));

        $tokenA = $factory->createForSession($session);
        $tokenB = $factory->createForSession($session);

        // Same per-session secret reused; tokens minted by either Token
        // instance must verify against either.
        $generated = $tokenA->generate('seed');
        $this->assertTrue($tokenB->isValid($generated->token, 'seed'));
    }

    public function testCreateForSessionUsesSessionScopedSecretSlot(): void
    {
        $factory = $this->makeFactory(['token' => ['driver' => 'Null']]);
        $session = new HordeSession(new SessionId('s1'));

        $factory->createForSession($session);

        $this->assertTrue($session->hasScoped('horde', 'token_secret_key'));
        $secret = $session->getScoped('horde', 'token_secret_key');
        $this->assertIsString($secret);
        $this->assertNotEmpty($secret);
    }

    public function testCreateForSessionDifferentSessionsProduceIncompatibleTokens(): void
    {
        $factory = $this->makeFactory(['token' => ['driver' => 'Null']]);
        $sessionA = new HordeSession(new SessionId('a1'));
        $sessionB = new HordeSession(new SessionId('b1'));

        $tokenA = $factory->createForSession($sessionA);
        $tokenB = $factory->createForSession($sessionB);

        $generatedA = $tokenA->generate('seed');

        // Different per-session secrets means session B cannot verify A's token.
        $this->assertFalse($tokenB->isValid($generatedA->token, 'seed'));
    }

    public function testCreateFromDeploymentSecretUsesConfSecretKey(): void
    {
        $factory = $this->makeFactory([
            'token' => ['driver' => 'Null'],
            'secret_key' => 'deployment-wide-secret-12345',
        ]);
        $token = $factory->createFromDeploymentSecret();

        $this->assertInstanceOf(Token::class, $token);

        // A second call must produce a Token signing with the SAME deployment
        // secret. Cross-validation succeeds.
        $token2 = $factory->createFromDeploymentSecret();
        $generated = $token->generate('seed');
        $this->assertTrue($token2->isValid($generated->token, 'seed'));
    }

    public function testCreateFromDeploymentSecretThrowsWhenSecretKeyMissing(): void
    {
        $factory = $this->makeFactory(['token' => ['driver' => 'Null']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secret_key');
        $factory->createFromDeploymentSecret();
    }

    public function testCreateFromDeploymentSecretThrowsWhenSecretKeyEmpty(): void
    {
        $factory = $this->makeFactory([
            'token' => ['driver' => 'Null'],
            'secret_key' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $factory->createFromDeploymentSecret();
    }

    public function testStorageBackendIsCachedAcrossCalls(): void
    {
        $factory = $this->makeFactory([
            'token' => ['driver' => 'Null'],
            'secret_key' => 'deployment-secret',
        ]);

        $tokenA = $factory->createFromSecret('a');
        $tokenB = $factory->createFromSecret('b');
        $tokenC = $factory->createFromDeploymentSecret();

        // Each Token round-trips against its own secret.
        $genA = $tokenA->generate('s');
        $genB = $tokenB->generate('s');
        $genC = $tokenC->generate('s');

        $this->assertTrue($tokenA->isValid($genA->token, 's'));
        $this->assertTrue($tokenB->isValid($genB->token, 's'));
        $this->assertTrue($tokenC->isValid($genC->token, 's'));

        // Cross-secret rejection
        $this->assertFalse($tokenA->isValid($genB->token, 's'));
        $this->assertFalse($tokenC->isValid($genA->token, 's'));
    }
}
