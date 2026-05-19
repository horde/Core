<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\View;

use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde_Injector;
use Horde\Injector\Injector;
use Horde_Registry;
use Throwable;

class AccessKeyTrackerFactory
{
    public function create(Horde_Injector|Injector $injector): AccessKeyTracker
    {
        $accessKeysEnabled = true;
        try {
            $prefs = $injector->getInstance(PrefsService::class);
            $session = $injector->getInstance(HordeSession::class);
            $uid = $session->getAuthId() ?? '';
            if ($uid !== '') {
                $accessKeysEnabled = (bool) $prefs->getValue($uid, 'horde', 'widget_accesskey');
            }
        } catch (Throwable) {
        }

        $multibyte = false;
        try {
            $registry = $injector->getInstance(Horde_Registry::class);
            $multibyte = !empty($registry->nlsconfig->curr_multibyte);
        } catch (Throwable) {
        }

        return new AccessKeyTracker($accessKeysEnabled, $multibyte);
    }
}
