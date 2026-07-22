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

namespace Horde\Core\PageOutput;

use Horde\Browser\Browser;
use Horde\Core\Session\SessionAccess;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: RenderingModeResolverFactory::class, method: 'create')]
class RenderingModeResolver
{
    public function __construct(
        private readonly SessionAccess $session,
        private readonly Browser $browser,
    ) {}

    public function resolve(): RenderingMode
    {
        $authId = $this->session->getAuthId();

        if ($authId !== null) {
            return $this->resolveForAuthenticated();
        }

        return $this->resolveForAnonymous();
    }

    /**
     * Store a rendering mode in the session.
     *
     * Called by the authentication flow after login to persist the user's
     * mode selection. Pass null to clear (revert to auto-detection).
     */
    public function storeMode(?RenderingMode $mode): void
    {
        if ($mode === null) {
            if ($this->session->hasScoped('horde', 'rendering_mode')) {
                $this->session->removeScoped('horde', 'rendering_mode');
            }
            return;
        }

        $this->session->setScoped('horde', 'rendering_mode', $mode->value);
    }

    /**
     * Map a legacy view name from the login form to a RenderingMode.
     *
     * Returns null for 'auto' (meaning: don't store, let detection handle it).
     */
    public static function fromLegacyViewName(string $viewName): ?RenderingMode
    {
        return match ($viewName) {
            'dynamic' => RenderingMode::DYNAMIC,
            'basic' => RenderingMode::BASIC,
            'smartmobile', 'mobile' => RenderingMode::RESPONSIVE,
            default => null,
        };
    }

    private function resolveForAuthenticated(): RenderingMode
    {
        if ($this->session->hasScoped('horde', 'rendering_mode')) {
            $stored = $this->session->getScoped('horde', 'rendering_mode');
            if (is_string($stored)) {
                $mode = RenderingMode::tryFrom($stored);
                if ($mode !== null) {
                    return $mode;
                }
            }
        }

        return $this->detectFromBrowser();
    }

    private function resolveForAnonymous(): RenderingMode
    {
        return $this->detectFromBrowser();
    }

    private function detectFromBrowser(): RenderingMode
    {
        if ($this->browser->mobile() || $this->browser->tablet()) {
            return RenderingMode::RESPONSIVE;
        }

        if (!$this->browser->hasFeature('ajax')) {
            return RenderingMode::BASIC;
        }

        return RenderingMode::DYNAMIC;
    }
}
