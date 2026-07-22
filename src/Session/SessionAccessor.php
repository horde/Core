<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Core\Session;

use DateTimeImmutable;
use LogicException;

/**
 * Concrete {@see SessionAccess} implementation. One instance per request,
 * bound as a shared singleton on the injector.
 *
 * {@see SessionLifecycle} owns the pointer and updates it via
 * {@see replaceWith()} on {@see SessionLifecycle::start()},
 * {@see SessionLifecycle::clean()}, {@see SessionLifecycle::destroy()},
 * and {@see SessionLifecycle::regenerate()}. Consumers read via the
 * inherited interface methods and never touch {@see replaceWith()}.
 *
 * The passthrough methods resolve {@see current()} on every call so
 * that after a rotation, the next facade call reaches the fresh
 * value. This is the whole point of the type: consumers get consistent
 * per-call resolution without knowing about lifecycle at all.
 */
final class SessionAccessor implements SessionAccess
{
    private ?HordeSession $currentSession = null;

    /**
     * @internal Only {@see SessionLifecycle} should call this. Publishes
     *           `$next` as the current session for the remainder of the
     *           request. Called on every lifecycle transition that
     *           produces a fresh session value.
     */
    public function replaceWith(HordeSession $next): void
    {
        $this->currentSession = $next;
    }

    /**
     * @internal Only {@see SessionLifecycle} should call this. Clears
     *           the pointer. Used on {@see SessionLifecycle::destroy()}
     *           when there is no successor session value.
     */
    public function clearCurrent(): void
    {
        $this->currentSession = null;
    }

    public function current(): HordeSession
    {
        if ($this->currentSession === null) {
            throw new LogicException(
                'SessionAccessor::current() called before a session has '
                . 'been established. Guard with hasCurrent() at sites '
                . 'that legitimately run before SessionLifecycle::start().',
            );
        }
        return $this->currentSession;
    }

    public function hasCurrent(): bool
    {
        return $this->currentSession !== null;
    }

    // -----------------------------------------------------------------
    // Passthrough facade.
    // -----------------------------------------------------------------

    public function getScoped(string $app, string $name): mixed
    {
        return $this->current()->getScoped($app, $name);
    }

    public function setScoped(string $app, string $name, mixed $value): void
    {
        $this->current()->setScoped($app, $name, $value);
    }

    public function hasScoped(string $app, string $name): bool
    {
        return $this->current()->hasScoped($app, $name);
    }

    public function removeScoped(string $app, string $name): void
    {
        $this->current()->removeScoped($app, $name);
    }

    /** @return list<string> */
    public function keysForApp(string $app): array
    {
        return $this->current()->keysForApp($app);
    }

    public function clearScope(string $app): void
    {
        $this->current()->clearScope($app);
    }

    /** @param list<string> $prefixes */
    public function clearScopeWithPrefixes(string $app, array $prefixes): void
    {
        $this->current()->clearScopeWithPrefixes($app, $prefixes);
    }

    public function getEncrypted(string $app, string $name): mixed
    {
        return $this->current()->getEncrypted($app, $name);
    }

    public function setEncrypted(string $app, string $name, mixed $value): void
    {
        $this->current()->setEncrypted($app, $name, $value);
    }

    public function isEncrypted(string $app, string $name): bool
    {
        return $this->current()->isEncrypted($app, $name);
    }

    /** @return array<string, array<string, true>> */
    public function getEncryptionMap(): array
    {
        return $this->current()->getEncryptionMap();
    }

    public function getAuthenticatedUser(): ?string
    {
        return $this->current()->getAuthenticatedUser();
    }

    public function getAuthId(): ?string
    {
        return $this->current()->getAuthId();
    }

    public function getBrowserFingerprint(): ?string
    {
        return $this->current()->getBrowserFingerprint();
    }

    public function getRemoteAddress(): ?string
    {
        return $this->current()->getRemoteAddress();
    }

    public function getAuthTimestamp(): ?DateTimeImmutable
    {
        return $this->current()->getAuthTimestamp();
    }

    public function getSessionBegin(): ?DateTimeImmutable
    {
        return $this->current()->getSessionBegin();
    }

    public function getRegenerationDeadline(): ?int
    {
        return $this->current()->getRegenerationDeadline();
    }

    /** @return list<string> */
    public function getAuthenticatedApps(): array
    {
        return $this->current()->getAuthenticatedApps();
    }
}
