<?php
declare(strict_types=1);

namespace Horde\Core\Service;

/**
 * Represents a specific purpose for which a service is used
 * This is Horde Apps vocabulary decoupled from specific scopes an IdP provider may use to model the same concept.
 * I.e. if the Horde identifier is "mail" and the scope in one IdP is "email" and the scope in another IdP is "imap", the ServicePurpose identifier is "mail"
 * Two service purposes are equal if they have the same identifier even if they have different GrantStrategies.
 * The default strategy is isolated grants.
 */
final class ServicePurpose
{
    private function __construct(
        private readonly string        $identifier,
        private readonly GrantStrategy $grantStrategy = GrantStrategy::Isolated,
    ) {}

    public static function of(string $identifier, GrantStrategy $grantStrategy = GrantStrategy::Isolated): self
    {
        return new self($identifier, $grantStrategy);
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function grantStrategy(): GrantStrategy
    {
        return $this->grantStrategy;
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier;
    }

    /** Round-trip through OAuthFlowData persistence. */
    public function serialize(): array
    {
        return ['id' => $this->identifier, 'strategy' => $this->grantStrategy->value];
    }

    public static function deserialize(array $data): self
    {
        return new self($data['id'], GrantStrategy::from($data['strategy']));
    }
}
