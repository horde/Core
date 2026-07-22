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

/**
 * Access to the request's current {@see HordeSession} value.
 *
 * `HordeSession` is a value type: it has a fixed {@see SessionId}, a
 * snapshot data map, and snapshot encryptor/decryptor closures. That
 * shape is right for admin iteration, backend load-N-inspect, tests,
 * and historical reads. It is wrong as a directly-injected dependency
 * of request-scoped services: once a service captures a `HordeSession`
 * in a constructor field, any rotation that produces a fresh value
 * cannot reach that captured reference. Post-rotation reads and writes
 * silently target a retired session — the "no credentials in session"
 * class of bug reported in horde/Core#190 and horde/base#99.
 *
 * `SessionAccess` is the answer. It is a request-scoped slot pointer:
 * one instance per request, held by consumers instead of `HordeSession`.
 * {@see current()} always returns whichever `HordeSession` value the
 * lifecycle has most recently published. Consumers must NOT cache the
 * result across call boundaries that could span a regeneration — ask
 * again. The passthrough facade methods on this interface do exactly
 * that: they resolve `current()` fresh on every call, then delegate.
 *
 * ## Facade vs. machinery
 *
 * The passthrough surface is deliberately narrower than
 * {@see HordeSession}'s. It covers the read/write and metadata-read
 * methods that request-scoped consumers legitimately call. It does
 * NOT cover the lifecycle-mutation surface (drain/refill,
 * updateEncryptionCallbacks, scheduleRegeneration, markDestroyed,
 * clearLifecycleFlags, setSessionBegin, setRegenerationDeadline). Those
 * remain on `HordeSession` only. Code that needs them is by definition
 * lifecycle-aware and can reach them via
 * `$access->current()->methodName(...)`; making that access explicit
 * is intentional.
 *
 * ## Ownership
 *
 * {@see SessionLifecycle} is the only legitimate caller of
 * {@see SessionAccessor::replaceWith()}. The interface exposes only the
 * read side; the write side lives on the concrete `SessionAccessor` and
 * is marked `@internal` there.
 */
interface SessionAccess
{
    /**
     * The `HordeSession` value that represents the current request's
     * session. Never cache the return value across any code boundary
     * that could reach `SessionLifecycle::regenerate()`,
     * `SessionLifecycle::clean()`, or `SessionLifecycle::destroy()` —
     * ask again by calling this method afresh.
     *
     * @throws \LogicException When no session has been established
     *                         yet. Guard with {@see hasCurrent()} at
     *                         call sites that legitimately run before
     *                         session setup.
     */
    public function current(): HordeSession;

    /**
     * Whether a current session value has been established for this
     * request. False before {@see SessionLifecycle::start()}, after
     * {@see SessionLifecycle::destroy()}, and during the narrow window
     * inside {@see SessionLifecycle::clean()} between drop and rebuild.
     * Most callers can assume true and rely on {@see current()}'s
     * exception.
     */
    public function hasCurrent(): bool;

    // -----------------------------------------------------------------
    // Passthrough facade — resolves current() fresh on every call and
    // delegates. Matches the consumer-facing read/write surface of
    // HordeSession.
    // -----------------------------------------------------------------

    public function getScoped(string $app, string $name): mixed;

    public function setScoped(string $app, string $name, mixed $value): void;

    public function hasScoped(string $app, string $name): bool;

    public function removeScoped(string $app, string $name): void;

    /** @return list<string> */
    public function keysForApp(string $app): array;

    public function clearScope(string $app): void;

    /** @param list<string> $prefixes */
    public function clearScopeWithPrefixes(string $app, array $prefixes): void;

    public function getEncrypted(string $app, string $name): mixed;

    public function setEncrypted(string $app, string $name, mixed $value): void;

    public function isEncrypted(string $app, string $name): bool;

    /** @return array<string, array<string, true>> */
    public function getEncryptionMap(): array;

    public function getAuthenticatedUser(): ?string;

    public function getAuthId(): ?string;

    public function getBrowserFingerprint(): ?string;

    public function getRemoteAddress(): ?string;

    public function getAuthTimestamp(): ?DateTimeImmutable;

    public function getSessionBegin(): ?DateTimeImmutable;

    public function getRegenerationDeadline(): ?int;

    /** @return list<string> */
    public function getAuthenticatedApps(): array;
}
