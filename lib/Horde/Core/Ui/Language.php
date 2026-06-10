<?php

/**
 * The Horde_Core_Ui_Language:: class provides a widget for changing the
 * currently selected language.
 *
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

use Horde\Core\Session\HordeSession;

class Horde_Core_Ui_Language
{
    /**
     * Render the language selection.
     *
     * @return string  The HTML selection box.
     */
    public static function render()
    {
        global $prefs, $registry;

        $html = '';

        if (!$prefs->isLocked('language')) {
            $session = $GLOBALS['injector']->getInstance(HordeSession::class);
            $session->setScoped('horde', 'language', $registry->preferredLang());
            $html = sprintf(
                '<form name="language" action="%s">',
                Horde::url($registry->get('webroot', 'horde') . '/services/language.php', false, -1)
            );
            $html .= '<input type="hidden" name="url" value="' . @htmlspecialchars(Horde::signUrl(Horde::selfUrl(false, false, true))) . '" />';
            $html .= '<select name="new_lang" onchange="document.language.submit()">';
            foreach ($registry->nlsconfig->languages as $key => $val) {
                $sel = ($key == $session->getScoped('horde', 'language')) ? ' selected="selected"' : '';
                $html .= "<option value=\"$key\"$sel>$val</option>";
            }
            $html .= '</select></form>';
        }

        return $html;
    }

}
