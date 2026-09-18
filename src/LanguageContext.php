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

namespace Horde\Core;

/**
 * Owner of the request's display language / locale.
 *
 * Replaces reads of $GLOBALS['language'] with an injectable service.
 * This is the source of truth: {@see \Horde_Registry} delegates its
 * legacy preferredLang()/setLanguage() to an instance of this
 * interface and mirrors the result into $GLOBALS['language'] for
 * backward compatibility, rather than the other way around.
 *
 * Implementations resolve the language cascade:
 *   1. Session ('horde' / 'language' scope)
 *   2. Preference ('language')
 *   3. Explicit $lang argument (e.g. login-screen selection), if valid
 *   4. Browser Accept-Language header
 *   5. Site-wide default / 'en_US' fallback
 *
 * Known MVP gap: code that calls setLanguage() directly on this
 * service (bypassing Horde_Registry) resolves and persists the
 * session value, but does not trigger Horde_Registry's side effects
 * (setlocale()/putenv(), gettext domain reload, per-app
 * changeLanguage() callbacks) or refresh $GLOBALS['language']. This is
 * accepted for now; a future PSR-14 LanguageChanged event dispatched
 * from setLanguage() is the intended remediation, letting
 * Horde_Registry (and other interested listeners) react without this
 * service depending back on the legacy registry.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface LanguageContext
{
    /**
     * The currently resolved display language (e.g. 'de_DE').
     *
     * Resolves and caches via preferredLang() on first access if
     * setLanguage() has not been called yet.
     */
    public function getLanguage(): string;

    /**
     * The ICU-compatible locale identifier for the current language.
     *
     * Currently identical to getLanguage(); kept as a distinct method
     * so callers expressing an ICU/locale need (e.g. date formatting)
     * are not coupled to the language-cascade wording.
     */
    public function getLocale(): string;

    /**
     * Run the language cascade without persisting anything.
     *
     * @param string|null $lang  Explicit candidate language (e.g. from
     *                           a login-screen selector), used only if
     *                           no session/preference value exists.
     *
     * @return string  The selected language abbreviation.
     */
    public function preferredLang(?string $lang = null): string;

    /**
     * Resolve (via preferredLang()) and persist the language for this
     * request (writes the 'horde' / 'language' session scope).
     *
     * @return string  The resolved, now-current language.
     */
    public function setLanguage(?string $lang = null): string;

    /**
     * Whether $lang is a configured, selectable language.
     */
    public function validLang(string $lang): bool;
}
