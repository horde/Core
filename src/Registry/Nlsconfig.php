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

namespace Horde\Core\Registry;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\LanguageContext;
use Horde\Core\Session\SessionAccess;
use Horde_Prefs;
use Horde_String;

/**
 * DI-friendly, PSR-4 replacement for the legacy `Horde_Registry_Nlsconfig`.
 *
 * Owns the language cascade (session -> preference -> explicit $lang ->
 * Accept-Language -> site default) and the resolved $language value
 * itself. Unlike its legacy predecessor, it does not read/write
 * $GLOBALS and does not require a bootstrapped `Horde_Registry` — it
 * only needs a session accessor, the merged `nls.php` config, and
 * (optionally) preferences, all constructor-injected.
 *
 * `Horde_Registry` is a *consumer* of this service, not the other way
 * around: it asks this class to resolve/persist the language, then
 * layers its own legacy side effects (setlocale()/putenv(), gettext
 * domain reload, `$GLOBALS['language']` mirroring, per-app
 * `changeLanguage()` callbacks) on top. See {@see LanguageContext} for
 * the accepted gap when this service is used without going through
 * `Horde_Registry`.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class Nlsconfig implements LanguageContext
{
    /** Merged `nls.php` config (`horde_nls_config` variable). */
    private array $config;

    private ?string $language = null;

    public function __construct(
        private SessionAccess $session,
        BackendConfigLoader $configLoader,
        private ?Horde_Prefs $prefs = null,
    ) {
        $this->config = $configLoader
            ->load('horde', 'nls.php', 'horde_nls_config')
            ->toArray();
    }

    public function validLang(string $lang): bool
    {
        return isset($this->config['languages'][$lang]);
    }

    public function preferredLang(?string $lang = null): string
    {
        if ($this->session->hasCurrent()
            && $this->session->hasScoped('horde', 'language')) {
            return basename((string) $this->session->getScoped('horde', 'language'));
        }

        if ($this->prefs !== null
            && ($prefLang = $this->prefs->getValue('language'))) {
            return basename($prefLang);
        }

        if (!empty($lang) && $this->validLang($lang)) {
            return basename($lang);
        }

        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $partialLang = null;

            foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $browserLang) {
                if (($pos = strpos($browserLang, ';')) !== false) {
                    $browserLang = substr($browserLang, 0, $pos);
                }

                $mapped = $this->mapLang(trim($browserLang));
                if ($this->validLang($mapped)) {
                    return basename($mapped);
                }

                /* In case there's no full match, save our best guess. Try
                 * ll_LL, followed by just ll. */
                if ($partialLang === null) {
                    $llLL = Horde_String::lower(substr($mapped, 0, 2))
                        . '_' . Horde_String::upper(substr($mapped, 0, 2));
                    if ($this->validLang($llLL)) {
                        $partialLang = $llLL;
                    } else {
                        $ll = $this->mapLang(substr($mapped, 0, 2));
                        if ($this->validLang($ll)) {
                            $partialLang = $ll;
                        }
                    }
                }
            }

            if ($partialLang !== null) {
                return basename($partialLang);
            }
        }

        $default = $this->config['defaults']['language'] ?? '';

        return $default !== '' ? basename($default) : 'en_US';
    }

    public function setLanguage(?string $lang = null): string
    {
        $resolved = (!empty($lang) && $this->validLang($lang))
            ? basename($lang)
            : $this->preferredLang($lang);

        $this->language = $resolved;

        if ($this->session->hasCurrent()) {
            $this->session->setScoped('horde', 'language', $resolved);
        }

        return $resolved;
    }

    public function getLanguage(): string
    {
        return $this->language ??= $this->preferredLang();
    }

    public function getLocale(): string
    {
        return $this->getLanguage();
    }

    /**
     * Maps languages with common two-letter codes (such as nl) to the
     * full locale code (in this case, nl_NL). Returns the language
     * unmodified if it isn't an alias.
     */
    private function mapLang(string $language): string
    {
        // Translate the $language to get broader matches.
        // (e.g. de-DE should match de_DE)
        $transLang = str_replace('-', '_', $language);
        $langParts = explode('_', $transLang);
        $transLang = Horde_String::lower($langParts[0]);
        if (isset($langParts[1])) {
            $transLang .= '_' . Horde_String::upper($langParts[1]);
        }

        return $this->config['aliases'][$transLang] ?? $transLang;
    }
}
