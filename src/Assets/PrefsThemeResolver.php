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

namespace Horde\Core\Assets;

use Horde\Core\Service\PrefsService;
use Horde\Identity\Identity;

class PrefsThemeResolver implements ThemeResolver
{
    public function __construct(
        private readonly PrefsService $prefsService,
        private readonly string $defaultTheme = 'default',
    ) {}

    public function resolve(Identity|string $identity, ?string $authUid = null, ?string $app = null): string
    {
        $scope = $app ?? 'horde';

        if ($identity instanceof Identity) {
            $theme = $this->prefsService->getValue($identity->id, $scope, 'theme');
            if ($theme !== null) {
                return (string) $theme;
            }

            if ($authUid !== null) {
                $theme = $this->prefsService->getValue($authUid, $scope, 'theme');
                if ($theme !== null) {
                    return (string) $theme;
                }
            }

            return $this->defaultTheme;
        }

        $theme = $this->prefsService->getValue($identity, $scope, 'theme');
        if ($theme !== null) {
            return (string) $theme;
        }

        return $this->defaultTheme;
    }
}
