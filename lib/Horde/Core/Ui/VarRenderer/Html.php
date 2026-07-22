<?php

use Horde\Util\Util;
use Horde\Util\Variables;

/**
 * The Horde_Core_Ui_VarRenderer_html:: class renders variables to HTML.
 *
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class Horde_Core_Ui_VarRenderer_Html extends Horde_Core_Ui_VarRenderer
{
    protected $_onLoadJS = [];

    protected function _renderVarInputDefault($form, &$var, &$vars)
    {
        return '<strong>Warning:</strong> Unknown variable type '
            . htmlspecialchars((string) $var->getTypeName());
    }

    protected function _renderVarInput_basic($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" value="%s" %s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->isDisabled() ? ' disabled="disabled" ' : '',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_number($form, &$var, &$vars)
    {
        $value = (string) $var->getValue($vars);
        if ($var->getProperty('fraction')) {
            $value = sprintf('%01.' . $var->getProperty('fraction') . 'f', $value);
        }
        $linfo = (new Horde\Nls\Nls())->getLocaleInfo();
        /* Only if there is a mon_decimal_point do the
         * substitution. */
        if (!empty($linfo['mon_decimal_point'])) {
            $value = str_replace('.', $linfo['mon_decimal_point'], $value);
        }
        return sprintf(
            '<input type="text" size="5" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $value),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_int($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="number" size="5" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_octal($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" size="5" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            sprintf('0%o', octdec($var->getValue($vars))),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_intlist($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_text($form, &$var, &$vars)
    {
        $maxlength = $var->getMaxLength();
        return sprintf(
            '<input type="text" name="%s" id="%s" size="%s" value="%s" %s%s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $var->getSize(),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->isDisabled() ? ' disabled="disabled" ' : '',
            empty($maxlength) ? '' : ' maxlength="' . $maxlength . '"',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_stringlist($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" size="60" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_stringarray($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" size="60" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) implode(', ', $var->getValue($vars))),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_phone($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" size="%s" value="%s" %s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $var->getSize(),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->isDisabled() ? ' disabled="disabled" ' : '',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_cellphone($form, &$var, &$vars)
    {
        return $this->_renderVarInput_phone($form, $var, $vars);
    }

    protected function _renderVarInput_ipaddress($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" size="16" value="%s" %s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->isDisabled() ? ' disabled="disabled" ' : '',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_ip6address($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" size="40" value="%s" %s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->isDisabled() ? ' disabled="disabled" ' : '',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_file($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="file" size="30" name="%s" id="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $this->_getActionScripts($form, $var)
        );
    }

    /**
     * @todo Show image dimensions in the width/height boxes.
     */
    protected function _renderVarInput_image($form, &$var, &$vars)
    {
        $html = '';
        $image = $var->getImage($vars);
        $varname = $this->_genID($var->getVarName(), false);

        $GLOBALS['injector']->getInstance('Horde_PageOutput')->addScriptFile('image.js', 'horde');

        /* Check if there is existing img information stored. */
        if (isset($image['img'])) {
            /* Hidden tag to store the preview image id. */
            $html = sprintf(
                '<input type="hidden" name="%s" id="%s" value="%s" />',
                htmlspecialchars((string) $var->getVarName()) . '[hash]',
                $this->_genID($var->getVarName() . '[hash]', false),
                $var->getRandomId()
            );
        }

        /* Output MAX_FILE_SIZE parameter to limit large files. */
        if ($var->getProperty('max_filesize')) {
            $html .= sprintf(
                '<input type="hidden" name="MAX_FILE_SIZE" value="%d" />',
                $var->getProperty('max_filesize')
            );
        }

        /* Output the input tag. */
        $html .= sprintf(
            '<input type="file" size="30" name="%s" id="%s" />',
            htmlspecialchars((string) $var->getVarName()) . '[new]',
            $this->_genID($var->getVarName() . '[new]', false)
        );

        /* Output the button to upload/reset the image. */
        if ($var->getProperty('show_upload')) {
            $html .= '&nbsp;';
            $html .= sprintf(
                '<input class="button" name="%s" id="%s" type="submit" value="%s" /> ',
                'do_' . htmlspecialchars((string) $var->getVarName()),
                'do_' . $this->_genID($var->getVarName(), false),
                Horde_Core_Translation::t('Upload')
            );
        }

        if (!empty($image['img'])) {
            $html .= '&nbsp;';
            $html .= sprintf(
                '<input class="button" name="%s" id="%s" type="submit" value="%s" /> ',
                'remove_' . htmlspecialchars((string) $var->getVarName()),
                'remove_' . $this->_genID($var->getVarName(), false),
                Horde_Core_Translation::t('Remove')
            );
            /* Image information stored, show preview, add buttons for image
             * manipulation. */
            $html .= '<br />';
            $img = Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/images/view.php');
            $img->setRaw(true);
            if (isset($image['img']['vfs_id'])) {
                /* Calling an image from VFS. */
                $img->add([
                    'f' => $image['img']['vfs_id'],
                    'p' => $image['img']['vfs_path'],
                    's' => 'vfs',
                ]);
            } else {
                /* Calling an image from a tmp directory (uploads). */
                $img->add('f', $image['img']['file']);
            }

            /* Reset. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Reset'), '', '', 'showImage(\'' . $img . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/refresh.png', ['alt' => Horde_Core_Translation::t('Reset')]) . '</a>';

            /* Rotate 270. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Rotate Left'), '', '', 'showImage(\'' . $img->copy()->add(['a' => 'rotate', 'v' => '270']) . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/rotate-270.png', ['alt' => Horde_Core_Translation::t('Rotate Left')]) . '</a>';

            /* Rotate 180. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Rotate 180'), '', '', 'showImage(\'' . $img->copy()->add(['a' => 'rotate', 'v' => '180']) . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/rotate-180.png', ['alt' => Horde_Core_Translation::t('Rotate 180')]) . '</a>';

            /* Rotate 90. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Rotate Right'), '', '', 'showImage(\'' . $img->copy()->add(['a' => 'rotate', 'v' => '90']) . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/rotate-90.png', ['alt' => Horde_Core_Translation::t('Rotate Right')]) . '</a>';

            /* Flip image. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Flip'), '', '', 'showImage(\'' . $img->copy()->add('a', 'flip') . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/flip.png', ['alt' => Horde_Core_Translation::t('Flip')]) . '</a>';

            /* Mirror image. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Mirror'), '', '', 'showImage(\'' . $img->copy()->add('a', 'mirror') . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/mirror.png', ['alt' => Horde_Core_Translation::t('Mirror')]) . '</a>';

            /* Apply grayscale. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Grayscale'), '', '', 'showImage(\'' . $img->copy()->add('a', 'grayscale') . '\', \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('image/grayscale.png', ['alt' => Horde_Core_Translation::t('Grayscale')]) . '</a>';

            /* Resize width. */
            $html .= sprintf(
                '%s<input type="text" size="4" onchange="src=getResizeSrc(\'%s\', \'%s\');showImage(src, \'_p_%s\', true);" %s />',
                Horde_Core_Translation::t('w:'),
                $img->copy()->add('a', 'resize'),
                $varname,
                $varname,
                $this->_genID('_w_' . $varname)
            );

            /* Resize height. */
            $html .= sprintf(
                '%s<input type="text" size="4" onchange="src=getResizeSrc(\'%s\', \'%s\');showImage(src, \'_p_%s\', true);" %s />',
                Horde_Core_Translation::t('h:'),
                $img->copy()->add('a', 'resize'),
                $varname,
                $varname,
                $this->_genID('_h_' . $varname)
            );

            /* Apply fixed ratio resize. */
            $html .= Horde::link('#', Horde_Core_Translation::t('Fix ratio'), '', '', 'src=getResizeSrc(\'' . $img->copy()->add('a', 'resize') . '\', \'' . $varname . '\', \'1\');showImage(src, \'_p_' . $varname . '\', true);') . Horde_Themes_Image::tag('ratio.png', ['alt' => Horde_Core_Translation::t('Fix ratio')]) . '</a>';

            /* Keep also original if it has been requested. */
            if ($var->getProperty('show_keeporig')) {
                $html .= sprintf(
                    '<input type="checkbox" class="checkbox" name="%s" id="%s"%s />%s' . "\n",
                    htmlspecialchars((string) $var->getVarName()) . '[keep_orig]',
                    $varname . '[keep_orig]',
                    !empty($image['keep_orig']) ? ' checked="checked"' : '',
                    Horde_Core_Translation::t('Keep original?')
                );
            }

            /* The preview image element. */
            $img->setRaw(false);
            $html .= '<br /><img src="' . $img . '" ' . $this->_genID('_p_' . $varname) . ">\n";
        }

        return $html;
    }

    protected function _renderVarInput_longtext($form, &$var, &$vars)
    {
        global $browser;

        $html = sprintf(
            '<textarea name="%s" id="%s" cols="%s" rows="%s"%s%s>%s</textarea>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            (int) $var->getCols(),
            (int) $var->getRows(),
            $this->_getActionScripts($form, $var),
            $var->isDisabled() ? ' disabled="disabled"' : '',
            htmlspecialchars((string) $var->getValue($vars))
        );

        if ($var->hasHelper('rte')) {
            $GLOBALS['injector']->getInstance('Horde_Editor')->initialize(
                ['id' => $this->_genID($var->getVarName(), false),
                    'relativelinks' => $var->hasHelper('relativelinks'),
                    'config' => ['extraPlugins' => 'syntaxhighlight']]
            );
        }

        if ($var->hasHelper() && $browser->hasFeature('javascript')) {
            $html .= '<br /><table cellspacing="0"><tr><td>';
            $imgId = $this->_genID($var->getVarName(), false) . 'ehelper';

            $page_output = $GLOBALS['injector']->getInstance('Horde_PageOutput');
            $page_output->addScriptFile('open_html_helper.js', 'horde');

            if ($var->hasHelper('emoticons')) {
                $filter = $GLOBALS['injector']->getInstance('Horde_Core_Factory_TextFilter')->create('emoticons');
                $icon_list = [];

                foreach (array_flip($filter->getIcons() ?? []) as $icon => $string) {
                    $icon_list[] = [
                        $filter->getIcon($icon),
                        $string,
                    ];
                }

                $page_output->addInlineJsVars([
                    'Horde_Html_Helper.iconlist' => $icon_list,
                ]);

                $html .= Horde::link('#', Horde_Core_Translation::t('Emoticons'), '', '', 'Horde_Html_Helper.open(\'emoticons\', \'' . $var->getVarName() . '\'); return false;') . Horde_Themes_Image::tag('emoticons/smile.png', ['alt' => Horde_Core_Translation::t('Emoticons'), 'attr' => ['id' => $imgId]]) . '</a>';
            }
            $html .= '</td></tr><tr><td><div ' . $this->_genID('htmlhelper_' . $var->getVarName()) . ' class="control"></div></td></tr></table>' . "\n";
        }

        return $html;
    }

    protected function _renderVarInput_countedtext($form, &$var, &$vars)
    {
        return sprintf(
            '<textarea name="%s" id="%s" cols="%s" rows="%s"%s%s>%s</textarea>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            (int) $var->getCols(),
            (int) $var->getRows(),
            $this->_getActionScripts($form, $var),
            $var->isDisabled() ? ' disabled="disabled"' : '',
            htmlspecialchars((string) $var->getValue($vars))
        );
    }

    protected function _renderVarInput_address($form, &$var, &$vars)
    {
        return sprintf(
            '<textarea name="%s" id="%s" cols="%s" rows="%s"%s%s>%s</textarea>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            (int) $var->getCols(),
            (int) $var->getRows(),
            $this->_getActionScripts($form, $var),
            $var->isDisabled() ? ' disabled="disabled"' : '',
            htmlspecialchars((string) $var->getValue($vars))
        );
    }

    protected function _renderVarInput_addresslink($form, &$var, &$vars)
    {
        return '';
    }

    protected function _renderVarInput_pgp($form, &$var, &$vars)
    {
        return $this->_renderVarInput_longtext($form, $var, $vars);
    }

    protected function _renderVarInput_smime($form, &$var, &$vars)
    {
        return $this->_renderVarInput_longtext($form, $var, $vars);
    }

    protected function _renderVarInput_country($form, &$var, &$vars)
    {
        return $this->_renderVarInput_enum($form, $var, $vars);
    }

    protected function _renderVarInput_date($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_time($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" size="5" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_hourminutesecond($form, &$var, &$vars)
    {
        $time = $var->getTimeParts($var->getValue($vars));

        /* Output hours. */
        $hours = ['' => Horde_Core_Translation::t('hh')];
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = $i;
        }
        $html = sprintf(
            '<select name="%s[hour]" id="%s_hour_"%s>%s</select>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $this->_getActionScripts($form, $var),
            $this->selectOptions($hours, ($time['hour'] === '') ? '' : $time['hour'])
        );

        /* Output minutes. */
        $minutes = ['' => Horde_Core_Translation::t('mm')];
        for ($i = 0; $i <= 59; $i++) {
            $m = sprintf('%02d', $i);
            $minutes[$m] = $m;
        }
        $html .= sprintf(
            '<select name="%s[minute]" id="%s_minute_"%s>%s</select>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $this->_getActionScripts($form, $var),
            $this->selectOptions($minutes, ($time['minute'] === '') ? '' : sprintf('%02d', $time['minute']))
        );

        /* Return if seconds are not required. */
        if (!$var->getProperty('show_seconds')) {
            return $html;
        }

        /* Output seconds. */
        $seconds = ['' => Horde_Core_Translation::t('ss')];
        for ($i = 0; $i <= 59; $i++) {
            $s = sprintf('%02d', $i);
            $seconds[$s] = $s;
        }
        return $html . sprintf(
            '<select name="%s[second]" id="%s_second_"%s>%s</select>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $this->_getActionScripts($form, $var),
            $this->selectOptions($seconds, ($time['second'] === '') ? '' : sprintf('%02d', $time['second']))
        );
    }

    protected function _renderVarInput_monthyear($form, &$var, &$vars)
    {
        $dates = [];
        $dates['month'] = ['' => Horde_Core_Translation::t('MM'),
            1 => Horde_Core_Translation::t('January'),
            2 => Horde_Core_Translation::t('February'),
            3 => Horde_Core_Translation::t('March'),
            4 => Horde_Core_Translation::t('April'),
            5 => Horde_Core_Translation::t('May'),
            6 => Horde_Core_Translation::t('June'),
            7 => Horde_Core_Translation::t('July'),
            8 => Horde_Core_Translation::t('August'),
            9 => Horde_Core_Translation::t('September'),
            10 => Horde_Core_Translation::t('October'),
            11 => Horde_Core_Translation::t('November'),
            12 => Horde_Core_Translation::t('December')];
        $dates['year'] = ['' => Horde_Core_Translation::t('YYYY')];
        if ($var->getProperty('start_year') > $var->getProperty('end_year')) {
            for ($i = $var->getProperty('start_year'); $i >= $var->getProperty('end_year'); $i--) {
                $dates['year'][$i] = $i;
            }
        } else {
            for ($i = $var->getProperty('start_year'); $i <= $var->getProperty('end_year'); $i++) {
                $dates['year'][$i] = $i;
            }
        }
        return sprintf(
            '<select name="%s" id="%s"%s>%s</select>',
            $var->getMonthVar($var),
            $var->getMonthVar($var),
            $this->_getActionScripts($form, $var),
            $this->selectOptions($dates['month'], $vars->get($var->getMonthVar($var)))
        )
            . sprintf(
                '<select name="%s" id="%s"%s>%s</select>',
                $var->getYearVar($var),
                $var->getYearVar($var),
                $this->_getActionScripts($form, $var),
                $this->selectOptions($dates['year'], $vars->get($var->getYearVar($var)))
            );
    }

    protected function _renderVarInput_monthdayyear($form, &$var, &$vars)
    {
        try {
            $date = $var->getDateParts($var->getValue($vars));
        } catch (Horde_Date_Exception $e) {
            return $this->_renderVarInput_basic($form, $var, $vars);
        }

        $GLOBALS['injector']->getInstance('Horde_PageOutput')->addInlineScript(
            "document.observe('Horde_Calendar:select', "
              . 'function(e) {'
                . 'var elt = e.element();'
                . "elt.up().previous('SELECT[name$=\"[month]\"]').setValue(e.memo.getMonth() + 1);"
                . "elt.up().previous('SELECT[name$=\"[day]\"]').setValue(e.memo.getDate());"
                . "elt.up().previous('SELECT[name$=\"[year]\"]').setValue(e.memo.getFullYear());"
                . '});',
            true
        );

        $dates = [
            'month' => [
                ''   => Horde_Core_Translation::t('MM'),
                '1'  => Horde_Core_Translation::t('January'),
                '2'  => Horde_Core_Translation::t('February'),
                '3'  => Horde_Core_Translation::t('March'),
                '4'  => Horde_Core_Translation::t('April'),
                '5'  => Horde_Core_Translation::t('May'),
                '6'  => Horde_Core_Translation::t('June'),
                '7'  => Horde_Core_Translation::t('July'),
                '8'  => Horde_Core_Translation::t('August'),
                '9'  => Horde_Core_Translation::t('September'),
                '10' => Horde_Core_Translation::t('October'),
                '11' => Horde_Core_Translation::t('November'),
                '12' => Horde_Core_Translation::t('December'),
            ],
        ];
        $dates['day'] = ['' => Horde_Core_Translation::t('DD')];
        for ($i = 1; $i <= 31; $i++) {
            $dates['day'][$i] = $i;
        }
        $dates['year'] = ['' => Horde_Core_Translation::t('YYYY')];
        if ($var->getProperty('start_year') > $var->getProperty('end_year')) {
            for ($i = $var->getProperty('start_year'); $i >= $var->getProperty('end_year'); $i--) {
                $dates['year'][$i] = $i;
            }
        } else {
            for ($i = $var->getProperty('start_year'); $i <= $var->getProperty('end_year'); $i++) {
                $dates['year'][$i] = $i;
            }
        }
        // TODO: use NLS to get the order right for the Rest Of The
        // World.
        $html = '';
        $date_parts = ['month', 'day', 'year'];
        foreach ($date_parts as $part) {
            $html .= sprintf(
                '<select name="%s" id="%s"%s>%s</select>',
                htmlspecialchars((string) $var->getVarName() . '[' . $part . ']'),
                $this->_genID($var->getVarName(), false) . $part,
                $this->_getActionScripts($form, $var),
                $this->selectOptions($dates[$part], $date[$part])
            );
        }

        if ($var->getProperty('picker')
            && $GLOBALS['browser']->hasFeature('javascript')) {
            Horde_Core_Ui_JsCalendar::init();
            $imgId = $this->_genID($var->getVarName(), false) . 'goto';
            $html .= Horde::link('#', Horde_Core_Translation::t('Select a date'), '', '', 'Horde_Calendar.open(\'' . $imgId . '\', null)') . Horde_Themes_Image::tag('calendar.png', ['alt' => Horde_Core_Translation::t('Calendar'), 'attr' => ['id' => $imgId]]) . "</a>\n";
        }

        return $html;
    }

    protected function _renderVarInput_datetime(&$form, &$var, &$vars)
    {
        return $this->_renderVarInput_monthdayyear($form, $var, $vars)
            . $this->_renderVarInput_hourminutesecond($form, $var, $vars);
    }

    protected function _renderVarInput_sound(&$form, &$var, &$vars)
    {
        $value = htmlspecialchars((string) $var->getValue($vars));
        $html = '<ul class="sound-list">';
        if (!$var->isRequired()) {
            $html .= '<li><label><input type="radio" id="' . $this->_genID($var->getVarName(), false) . '" name="' . htmlspecialchars((string) $var->getVarName()) . '" value=""' . (!$value ? ' checked="checked"' : '') . ' /> ' . Horde_Core_Translation::t('No Sound') . '</label></li>';
        }
        foreach ($var->getSounds() as $sound) {
            $sound = htmlspecialchars((string) $sound);
            $html .= '<li><label><input type="radio" id="' . $this->_genID($var->getVarName(), false) . '" name="' . htmlspecialchars((string) $var->getVarName()) . '" value="' . $sound . '"' . ($value == $sound ? ' checked="checked"' : '') . ' />' . $sound . '</label>'
                . ' <embed autostart="false" src="' . Horde_Themes::sound($sound) . '" /></li>';
        }
        return $html . '</ul>';
    }

    protected function _renderVarInput_colorpicker($form, &$var, &$vars)
    {
        global $browser;

        $varname = $this->_genID($var->getVarName(), false);
        $color = $var->getValue($vars);
        if ($color) {
            $style = ' style="background-color:' . htmlspecialchars((string) $color) . ';color:'
                . (Horde_Image::brightness($color) < 128 ? '#fff' : '#000') . '"';
        } else {
            $style = '';
        }
        $html = '<input type="text" size="10" maxlength="7" name="'
            . htmlspecialchars((string) $var->getVarName()) . '" id="' . $varname . '"' . $style
            . ' value="' . htmlspecialchars((string) $color)
            . '" />';
        if ($browser->hasFeature('javascript')) {
            $GLOBALS['injector']->getInstance('Horde_PageOutput')->addScriptFile('colorpicker.js', 'horde');
            $html .= ' '
                . Horde::link(
                    '#',
                    Horde_Core_Translation::t('Color Picker'),
                    '',
                    '',
                    'new ColorPicker({ color: \'' . htmlspecialchars((string) $color) . '\', offsetParent: Event.element(event), update: [[\'' . $varname . '\', \'value\'], [\'' . $varname . '\', \'background\']] }); return false;'
                )
                . Horde_Themes_Image::tag('colorpicker.png', ['alt' => Horde_Core_Translation::t('Color Picker'), 'attr' => ['height' => 16]]) . '</a>';
        }
        return $html;
    }

    protected function _renderVarInput_sorter($form, &$var, &$vars)
    {
        $instance = $var->getProperty('instance');

        $page = $GLOBALS['injector']->getInstance('Horde_PageOutput');
        $page->addScriptFile('sorter.js', 'horde');
        $page->addInlineScript(
            sprintf(
                '%1$s = new Horde_Form_Sorter(\'%1$s\', \'%2$s\', \'%3$s\');%1$s.setHidden();',
                $instance,
                $this->_genID($var->getVarName(), false),
                $var->getHeader()
            )
        );

        return '<input type="hidden" name="' . htmlspecialchars((string) $var->getVarName())
            . '[array]" value="" ' . $this->_genID($var->getVarName() . '_array') . '/>'
            . '<select class="leftFloat" multiple="multiple" size="'
            . (int) $var->getSize() . '" name="' . htmlspecialchars((string) $var->getVarName())
            . '[list]" onchange="' . $instance . '.deselectHeader();" '
            . $this->_genID($var->getVarName() . '_list') . '>'
            . $var->getOptions($var->getValue($vars)) . '</select><div class="leftFloat">'
            . Horde::link('#', Horde_Core_Translation::t('Move up'), '', '', $instance . '.moveColumnUp(); return false;') . Horde_Themes_Image::tag('nav/up.png', ['alt' => Horde_Core_Translation::t('Move up')]) . '</a><br />'
            . Horde::link('#', Horde_Core_Translation::t('Move up'), '', '', $instance . '.moveColumnDown(); return false;') . Horde_Themes_Image::tag('nav/down.png', ['alt' => Horde_Core_Translation::t('Move down')]) . '</a></div>';
    }

    protected function _renderVarInput_assign($form, &$var, &$vars)
    {
        $GLOBALS['injector']->getInstance('Horde_PageOutput')->addScriptFile('form_assign.js', 'horde');

        $name = htmlspecialchars((string) $var->getVarName());
        $size = $var->getSize();
        $width = $var->getWidth();
        $lhdr = (bool) $var->getHeader(0);
        $rhdr = (bool) $var->getHeader(1);
        $this->_addOnLoadJavascript('Horde_Form_Assign.setField(\'' . $form->getName() . '\', \'' . $var->getVarName() . '\');');

        return '<input type="hidden" name="' . $name . '__values" />'
            . '<table style="width:auto"><tr><td>'
            . sprintf(
                '<select name="%s__left" multiple="multiple" size="%d" style="width:%s"%s>',
                $name,
                $size,
                $width,
                $lhdr ? ' onchange="Horde_Form_Assign.deselectHeaders(\'' . $form->getName() . '\', \'' . $var->getVarName() . '\', 0);"' : ''
            )
            . $var->getOptions(0, $form->getName(), $var->getVarName())
            . '</select></td><td>'
            . '<a href="#" onclick="Horde_Form_Assign.move(\'' . $form->getName() . '\', \'' . $var->getVarName() . '\', 0); return false;">'
            . Horde_Themes_Image::tag('rhand.png', ['alt' => Horde_Core_Translation::t('Add')])
            . '</a><br /><a href="#" onclick="Horde_Form_Assign.move(\''
            . $form->getName() . '\', \'' . $var->getVarName() . '\', 1); return false;">'
            . Horde_Themes_Image::tag('lhand.png', ['alt' => Horde_Core_Translation::t('Remove')])
            . '</a></td><td>'
            . sprintf(
                '<select name="%s__right" multiple="multiple" size="%d" style="width:%s"%s>',
                $name,
                $size,
                $width,
                $rhdr ? ' onchange="Horde_Form_Assign.deselectHeaders(\'' . $form->getName() . '\', \'' . $var->getVarName() . '\', 1);"' : ''
            )
            . $var->getOptions(1, $form->getName(), $var->getVarName())
            . '</select></td></tr></table>';
    }

    protected function _renderVarInput_invalid($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_invalid($form, $var, $vars);
    }

    protected function _renderVarInput_enum($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $prompt = $var->getPrompt();
        $htmlchars = $var->getOption('htmlchars');
        if (!empty($prompt)) {
            $prompt = '<option value="">' . ($htmlchars ? $prompt : htmlspecialchars((string) $prompt)) . '</option>';
        }
        return sprintf(
            '<select name="%s" id="%s" %s>%s%s</select>',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            $this->_getActionScripts($form, $var),
            $prompt,
            $this->selectOptions($values, $var->getValue($vars), $htmlchars)
        );
    }

    protected function _renderVarInput_mlenum($form, &$var, &$vars)
    {
        $varname = $var->getVarName();
        $hvarname = htmlspecialchars((string) $varname);
        $values = $var->getValues();
        $prompts = $var->getPrompts();
        $selected = $var->getValue($vars);
        /* If passing a non-array value need to get the keys. */
        if (!is_array($selected)) {
            foreach ($values as $key_1 => $values_2) {
                if (isset($values_2[$selected])) {
                    $selected = ['1' => $key_1, '2' => $selected];
                    break;
                }
            }
        }

        /* Hidden tag to store the current first level. */
        $html = sprintf(
            '<input type="hidden" name="%s[old]" value="%s" %s />',
            $hvarname,
            htmlspecialchars((string) $selected['1']),
            $this->_genID($varname . '_old')
        );

        /* First level. */
        $values_1 = Horde_Array::valuesToKeys(array_keys($values));
        $html .= sprintf(
            '<select %s name="%s[1]" onchange="%s"%s>',
            $this->_genID($varname . '_1'),
            $hvarname,
            'if (this.value) { document.' . $form->getName() . '.formname.value=\'\';' . 'document.' . $form->getName() . '.submit() }',
            ($var->hasAction() ? ' ' . $this->_genActionScript($form, $var->_action, $varname) : '')
        );
        if (!empty($prompts)) {
            $html .= '<option value="">' . htmlspecialchars((string) $prompts[0]) . '</option>';
        }
        $html .= $this->selectOptions($values_1, $selected['1']);
        $html .= '</select>';

        /* Second level. */
        $html .= sprintf(
            '<select %s name="%s[2]"%s>',
            $this->_genID($varname . '_2'),
            $hvarname,
            ($var->hasAction() ? ' ' . $this->_genActionScript($form, $var->_action, $varname) : '')
        );
        if (!empty($prompts)) {
            $html .= '<option value="">' . htmlspecialchars((string) $prompts[1]) . '</option>';
        }
        $values_2 = [];
        if (!empty($selected['1'])) {
            $values_2 = &$values[$selected['1']];
        }
        return $html . $this->selectOptions($values_2, $selected['2']) . '</select>';
    }

    protected function _renderVarInput_multienum($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $selected = $vars->getExists($var->getVarName(), $wasset);
        if (!$wasset) {
            $selected = $var->getDefault();
        }
        return sprintf(
            '<select multiple="multiple" size="%s" name="%s[]" %s>%s</select>',
            (int) $var->size,
            htmlspecialchars((string) $var->getVarName()),
            $this->_getActionScripts($form, $var),
            $this->_multiSelectOptions($values, $selected)
        )
            . "<br />\n" . Horde_Core_Translation::t('To select multiple items, hold down the Control (PC) or Command (Mac) key while clicking.') . "\n";
    }

    protected function _renderVarInput_keyval_multienum($form, &$var, &$vars)
    {
        return $this->_renderVarInput_multienum($form, $var, $vars);
    }

    protected function _renderVarInput_radio($form, &$var, &$vars)
    {
        return $this->_radioButtons(
            $var->getVarName(),
            $var->getValues(),
            $var->getValue($vars),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_set($form, &$var, &$vars)
    {
        $html = $this->_checkBoxes(
            $var->getVarName(),
            $var->getValues(),
            $var->getValue($vars),
            $this->_getActionScripts($form, $var)
        );

        if ($var->getProperty('checkAll')) {
            $form_name = $form->getName();
            $var_name = $var->getVarName() . '[]';
            $function_name = 'select' . $form_name . $var->getVarName();
            $enable = Horde_Core_Translation::t('Select all');
            $disable = Horde_Core_Translation::t('Select none');
            $invert = Horde_Core_Translation::t('Invert selection');
            $GLOBALS['injector']
                ->getInstance('Horde_PageOutput')
                ->addInlineScript(sprintf(
                    '
function %s()
{
    for (var i = 0; i < document.%s.elements.length; i++) {
        f = document.%s$2.elements[i];
        if (f.name != \'%s$3\') {
            continue;
        }
        if (arguments.length) {
            f.checked = arguments[0];
        } else {
            f.checked = !f.checked;
        }
    }
}',
                    $function_name,
                    $form_name,
                    $var_name
                ));

            $html .= <<<EOT
                <a href="#" onclick="$function_name(true); return false;">$enable</a>,
                <a href="#" onclick="$function_name(false); return false;">$disable</a>,
                <a href="#" onclick="$function_name(); return false;">$invert</a>
                EOT;
        }

        return $html;
    }

    protected function _renderVarInput_link($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_link($form, $var, $vars);
    }

    protected function _renderVarInput_html($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_html($form, $var, $vars);
    }

    protected function _renderVarInput_email($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="email" name="%s" id="%s" value="%s"%s%s%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $var->getSize() ? ' size="' . $var->getSize() . '"' : '',
            $var->allowMulti() ? ' multiple="multiple"' : '',
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_matrix($form, &$var, &$vars)
    {
        $varname   = $var->getVarName();
        $var_array = $var->getValue($vars);
        $cols      = $var->getCols();
        $rows      = $var->getRows();
        $matrix    = $var->getMatrix();
        $new_input = $var->getNewInput();

        $html = '<table cellspacing="0"><tr>';

        $html .= '<td class="rightAlign" width="20%"></td>';
        foreach ($cols as $col_title) {
            $html .= '<td align="center" width="1%">' . htmlspecialchars((string) $col_title) . '</td>';
        }
        $html .= '<td class="rightAlign" width="60%"></td></tr>';

        /* Offer a new row of data to be added to the matrix? */
        if ($new_input) {
            $html .= '<tr><td>';
            if (is_array($new_input)) {
                $html .= sprintf(
                    '<select %s name="%s[n][r]"><option value="">%s</option>%s</select><br />',
                    $this->_genID($varname . '_n_r'),
                    htmlspecialchars((string) $var->getVarName()),
                    Horde_Core_Translation::t('-- select --'),
                    $this->selectOptions($new_input, $var_array['n']['r'] ?? null)
                );
            } elseif ($new_input == true) {
                $html .= sprintf(
                    '<input %s type="text" name="%s[n][r]" value="%s" />',
                    $this->_genID($varname . '_n_r'),
                    htmlspecialchars((string) $var->getVarName()),
                    is_array($var_array) ? $var_array['n']['r'] : ''
                );
            }
            $html .= ' </td>';
            foreach ($cols as $col_id => $col_title) {
                $html .= sprintf('<td align="center"><input type="checkbox" class="checkbox" name="%s[n][v][%s]" /></td>', htmlspecialchars((string) $var->getVarName()), $col_id);
            }
            $html .= '<td> </td></tr>';
        }

        /* Loop through the rows and create checkboxes for each column. */
        foreach ($rows as $row_id => $row_title) {
            $html .= sprintf('<tr><td>%s</td>', $row_title);
            foreach ($cols as $col_id => $col_title) {
                $html .= sprintf('<td align="center"><input type="checkbox" class="checkbox" name="%s[r][%s][%s]"%s /></td>', htmlspecialchars((string) $var->getVarName()), $row_id, $col_id, (!empty($matrix[$row_id][$col_id]) ? ' checked="checked"' : ''));
            }
            $html .= '<td> </td></tr>';
        }

        return $html . '</table>';
    }

    protected function _renderVarInput_password($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="password" name="%s" id="%s" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $var->getValue($vars)),
            $this->_getActionScripts($form, $var)
        );
    }

    protected function _renderVarInput_emailconfirm($form, &$var, &$vars)
    {
        $email = $var->getValue($vars);
        return sprintf(
            '<input type="email" name="%s[original]" id="%s_original" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $email['original']),
            $this->_getActionScripts($form, $var)
        )
            . ' ' . sprintf(
                '<input type="email" name="%s[confirm]" id="%s_confirm" value="%s"%s />',
                htmlspecialchars((string) $var->getVarName()),
                $this->_genID($var->getVarName(), false),
                htmlspecialchars((string) $email['confirm']),
                $this->_getActionScripts($form, $var)
            );
    }

    /**
     * Render two input fields to confirm a password
     *
     * Used in admin/user and possibly more places
     *
     * @param Horde_Form $form
     * @param Horde_Variables|Variables $var
     * @param Horde_Variables|Variables $vars
     * @return void
     */
    protected function _renderVarInput_passwordconfirm($form, &$var, &$vars)
    {
        $password = $var->getValue($vars) ?? ['confirm' => '', 'original' => ''];
        return sprintf(
            '<input type="password" name="%s[original]" id="%s_original" value="%s"%s />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            htmlspecialchars((string) $password['original']),
            $this->_getActionScripts($form, $var)
        )
            . ' ' . sprintf(
                '<input type="password" name="%s[confirm]" id="%s_confirm" value="%s"%s />',
                htmlspecialchars((string) $var->getVarName()),
                $this->_genID($var->getVarName(), false),
                htmlspecialchars((string) $password['confirm']),
                $this->_getActionScripts($form, $var)
            );
    }

    protected function _renderVarInput_boolean($form, &$var, &$vars)
    {
        $html = '<input type="checkbox" class="checkbox" name="' . htmlspecialchars((string) $var->getVarName()) . '"'
            . ' id="' . $this->_genID($var->getVarName(), false) . '"' . ($var->getValue($vars) ? ' checked="checked"' : '')
            . ($var->isDisabled() ? ' disabled="disabled" ' : '');
        if ($var->hasAction()) {
            $html .= $this->_genActionScript(
                $form,
                $var->_action,
                $var->getVarName()
            );
        }
        return $html . ' />';
    }

    protected function _renderVarInput_creditcard($form, &$var, &$vars)
    {
        $html = '<input type="text" name="' . htmlspecialchars((string) $var->getVarName()) . '" id="' . $this->_genID($var->getVarName(), false) . '" value="'
            . htmlspecialchars((string) $var->getValue($vars)) . '"';
        if ($var->hasAction()) {
            $html .= $this->_genActionScript(
                $form,
                $var->_action,
                $var->getVarName()
            );
        }
        return $html . ' />';
    }

    protected function _renderVarInput_obrowser($form, &$var, &$vars)
    {
        $varname = $var->getVarName();
        $varvalue = $vars->get($varname);
        $fieldId = $this->_genID(uniqid(mt_rand()), false) . 'id';
        $GLOBALS['injector']
            ->getInstance('Horde_PageOutput')
            ->addInlineScript(sprintf(
                '
var obrowserWindowName;
function obrowserCallback(name, oid)
{
    if (name == obrowserWindowName) {
        document.getElementById(\'%s\').value = oid;
        return false;
    } else {
        return "Invalid window name supplied";
    }
}',
                $fieldId
            ));

        $html .= sprintf(
            '<input type="hidden" name="%s" id="%s"%s value="%s">',
            htmlspecialchars((string) $varname),
            $fieldId,
            $this->_getActionScripts($form, $var),
            htmlspecialchars((string) $varvalue)
        );
        if (!empty($varvalue)) {
            $html .= $varvalue;
        }

        if ($GLOBALS['browser']->hasFeature('javascript')) {
            $html .= Horde::link($GLOBALS['registry']->get('webroot', 'horde') . '/services/obrowser/', Horde_Core_Translation::t('Select an object'), '', '_blank', 'obrowserWindow = ' . Horde::popupJs($GLOBALS['registry']->get('webroot', 'horde') . '/services/obrowser/', ['urlencode' => true]) . 'obrowserWindowName = obrowserWindow.name; return false;') . Horde_Themes_Image::tag('tree/leaf.png', ['alt' => Horde_Core_Translation::t('Object')]) . "</a>\n";
        }

        return $html;
    }

    protected function _renderVarInput_dblookup($form, &$var, &$vars)
    {
        return $this->_renderVarInput_enum($form, $var, $vars);
    }

    protected function _renderVarInput_figlet($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" size="%s" value="%s" />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            strlen($var->getText()),
            htmlspecialchars((string) $var->getValue($vars))
        )
            . '<br />' . Horde_Core_Translation::t('Enter the letters below:') . '<br />'
            . $this->_renderVarDisplay_figlet($form, $var, $vars);
    }

    protected function _renderVarInput_captcha($form, &$var, &$vars)
    {
        return sprintf(
            '<input type="text" name="%s" id="%s" size="%s" value="%s" />',
            htmlspecialchars((string) $var->getVarName()),
            $this->_genID($var->getVarName(), false),
            strlen($var->getText()),
            htmlspecialchars((string) $var->getValue($vars))
        )
            . '<br />' . Horde_Core_Translation::t('Enter the letters below:') . '<br />'
            . $this->_renderVarDisplay_captcha($form, $var, $vars);
    }

    protected function _renderVarDisplayDefault($form, &$var, &$vars)
    {
        return nl2br(htmlspecialchars((string) $var->getValue($vars)));
    }

    protected function _renderVarDisplay_html($form, &$var, &$vars)
    {
        // Since this is an HTML type we explicitly don't escape
        // it. User beware.
        return $var->getValue($vars);
    }

    protected function _renderVarDisplay_email($form, &$var, &$vars)
    {
        $email_val = $var->getValue($vars);

        if ($var->getProperty('link_compose')) {
            $addrs = $GLOBALS['injector']->getInstance('Horde_Mail_Rfc822')->parseAddressList($email_val, [
                'limit' => $var->getProperty('allow_multi') ? 0 : 1,
            ]);

            $link = '';
            foreach ($addrs as $addr) {
                $display_email = $var->getProperty('strip_domain')
                    ? $addr->mailbox . ' (at) ' . str_replace('.', ' (dot) ', $addr->host)
                    : $addr->bare_address;

                $addr->personal = $var->getProperty('link_name');
                $address = $addr->writeAddress(true);

                try {
                    $mail_link = $GLOBALS['registry']->call('mail/compose', [['to' => addslashes($address)]]);
                } catch (Horde_Exception $e) {
                    $mail_link = 'mailto:' . urlencode($address);
                }

                if (!empty($link)) {
                    $link .= ', ';
                }

                $link .= Horde::link($mail_link, strval($addr)) . htmlspecialchars((string) $display_email) . '</a>';
            }

            return $link;
        } else {
            $addrs = $GLOBALS['injector']->getInstance('Horde_Mail_Rfc822')->parseAddressList($email_val, [
                'limit' => $var->getProperty('allow_multi') ? 0 : 1,
            ]);

            $display = [];
            foreach ($addrs as $addr) {
                $display_email = $var->getProperty('strip_domain')
                    ? $addr->mailbox . ' (at) ' . str_replace('.', ' (dot) ', $addr->host)
                    : $addr->bare_address;
                $display[] = htmlspecialchars((string) $display_email);
            }

            return implode(', ', $display);
        }
    }

    protected function _renderVarDisplay_password($form, &$var, &$vars)
    {
        return '********';
    }

    protected function _renderVarDisplay_passwordconfirm($form, &$var, &$vars)
    {
        return '********';
    }

    protected function _renderVarDisplay_octal($form, &$var, &$vars)
    {
        return sprintf('0%o', octdec($var->getValue($vars)));
    }

    protected function _renderVarDisplay_boolean($form, &$var, &$vars)
    {
        return $var->getValue($vars) ? Horde_Core_Translation::t('Yes') : Horde_Core_Translation::t('No');
    }

    protected function _renderVarDisplay_enum($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $value = $var->getValue($vars);
        if (count($values) == 0) {
            return Horde_Core_Translation::t('No values');
        } elseif (isset($values[$value]) && $value != '') {
            return htmlspecialchars((string) $values[$value]);
        }
    }

    protected function _renderVarDisplay_radio($form, &$var, &$vars)
    {
        $values = $var->getValues();
        if (count($values) == 0) {
            return Horde_Core_Translation::t('No values');
        } elseif (isset($values[$var->getValue($vars)])) {
            return htmlspecialchars((string) $values[$var->getValue($vars)]);
        }
    }

    protected function _renderVarDisplay_multienum($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $on = $var->getValue($vars);
        if (!count($values) || !count($on)) {
            return Horde_Core_Translation::t('No values');
        } else {
            $display = [];
            foreach ($values as $value => $name) {
                if (in_array($value, $on)) {
                    $display[] = $name;
                }
            }
            return htmlspecialchars(implode(', ', $display));
        }
    }

    protected function _renderVarDisplay_keyval_multienum($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $on = $var->getValue($vars);
        if (!count($values) || !count($on)) {
            return Horde_Core_Translation::t('No values');
        } else {
            $display = [];
            foreach ($values as $name) {
                if (in_array($name, $on)) {
                    $display[] = $name;
                }
            }
            return htmlspecialchars(implode(', ', $display));
        }
    }

    protected function _renderVarDisplay_set($form, &$var, &$vars)
    {
        $values = $var->getValues();
        $on = $var->getValue($vars);
        if (!count($values) || !count($on)) {
            return Horde_Core_Translation::t('No values');
        } else {
            $display = [];
            foreach ($values as $value => $name) {
                if (in_array($value, $on)) {
                    $display[] = $name;
                }
            }
            return htmlspecialchars(implode(', ', $display));
        }
    }

    protected function _renderVarDisplay_image($form, &$var, &$vars)
    {
        $image = $var->getValue($vars);

        /* Check if existing image data is being loaded. */
        $image = $var->loadImageData($image);

        if (empty($image['img'])) {
            return '';
        }

        $img = Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/images/view.php');

        if (isset($image['img']['vfs_id'])) {
            /* Calling an image from VFS. */
            $img->add([
                'f' => $image['img']['vfs_id'],
                'p' => $image['img']['vfs_path'],
                's' => 'vfs',
            ]);
        } else {
            /* Calling an image from a tmp directory (uploads). */
            $img->add('f', $image['img']['file']);
        }

        return '<img src="' . $img . '" />';
    }

    protected function _renderVarDisplay_phone($form, &$var, &$vars)
    {
        global $registry;

        $number = $var->getValue($vars);
        $html = htmlspecialchars((string) $number);

        if ($number && $registry->hasMethod('telephony/dial')) {
            $url = $registry->call('telephony/dial', [$number]);
            $label = sprintf(Horde_Core_Translation::t('Dial %s'), $number);
            $html .= ' ' . Horde::link($url, $label) . Horde_Themes_Image::tag('phone.png', ['alt' => $label]) . '</a>';
        }

        return $html;
    }

    protected function _renderVarDisplay_cellphone($form, &$var, &$vars)
    {
        global $registry;

        $html = $this->_renderVarDisplay_phone($form, $var, $vars);

        $number = $var->getValue($vars);
        if ($number && $registry->hasMethod('sms/compose')) {
            $url = $registry->link('sms/compose', ['to' => $number]);
            $html .= ' ' . Horde::link($url, Horde_Core_Translation::t('Send SMS')) . Horde_Themes_Image::tag('mobile.png', ['alt' => Horde_Core_Translation::t('Send SMS')]) . '</a>';
        }

        return $html;
    }

    protected function _renderVarDisplay_address($form, &$var, &$vars, $text = true)
    {
        $address = $var->getValue($vars);
        if (empty($address)) {
            return '';
        }

        $info = $var->parse($address);

        $google_icon = 'map.png';
        if (!empty($info['country'])) {
            switch ($info['country']) {
                case 'uk':
                    /* Multimap.co.uk generated map */
                    $mapurl = 'http://www.multimap.com/map/browse.cgi?pc='
                        . urlencode($info['zip']);
                    $desc = Horde_Core_Translation::t('Multimap UK map');
                    $icon = 'map.png';
                    break;

                case 'au':
                    /* Whereis.com.au generated map */
                    $mapurl = 'http://www.whereis.com.au/whereis/mapping/geocodeAddress.do?';
                    $desc = Horde_Core_Translation::t('Whereis Australia map');
                    $icon = 'map.png';
                    /* See if it's the street number & name. */
                    if (isset($info['streetNumber'])
                        && isset($info['streetName'])) {
                        $mapurl .= '&streetNumber='
                            . urlencode($info['streetNumber']) . '&streetName='
                            . urlencode($info['streetName']);
                    }
                    /* Look for "Suburb, State". */
                    if (isset($info['city'])) {
                        $mapurl .= '&suburb=' . urlencode($info['city']);
                    }
                    /* Look for "State <4 digit postcode>". */
                    if (isset($info['state'])) {
                        $mapurl .= '&state=' . urlencode($info['state']);
                    }
                    break;

                case 'us':
                case 'ca':
                    /* American/Canadian address style. */
                    /* Mapquest generated map */
                    $mapurl = 'http://www.mapquest.com/maps/map.adp?size=big&zoom=7';
                    $desc = Horde_Core_Translation::t('MapQuest map');
                    $icon = 'map.png';
                    if (!empty($info['street'])) {
                        $mapurl .= '&address=' . urlencode($info['street']);
                    }
                    if (!empty($info['city'])) {
                        $mapurl .= '&city=' . urlencode($info['city']);
                    }
                    if (!empty($info['state'])) {
                        $mapurl .= '&state=' . urlencode($info['state']);
                    }
                    if (!empty($info['zip'])) {
                        if ($info['country'] == 'ca') {
                            $mapurl .= '&country=CA';
                        }
                        $mapurl .= '&zipcode=' . urlencode($info['zip']);
                    }
                    break;

                default:
                    if (!count($info)) {
                        break;
                    }
                    /* European address style. */
                    $google_icon = 'map_eu.png';
                    /* Mapquest generated map. */
                    $mapurl2 = 'http://www.mapquest.com/maps/map.adp?country=' . Horde_String::upper($info['country']);
                    $desc2 = Horde_Core_Translation::t('MapQuest map');
                    $icon2 = 'map_eu.png';
                    if (!empty($info['street'])) {
                        $mapurl2 .= '&address=' . urlencode($info['street']);
                    }
                    if (!empty($info['zip'])) {
                        $mapurl2 .= '&zipcode=' . urlencode($info['zip']);
                    }
                    if (!empty($info['city'])) {
                        $mapurl2 .= '&city=' . urlencode($info['city']);
                    }
                    break;
            }
        }

        $html = $text ? nl2br(htmlspecialchars((string) $address)) : '';
        /* The three map-icon <img> tags below all carry a shared
         * "horde-map-icon" class so apps can constrain their size
         * via app-level CSS. The default theme's map.png ships at
         * 48x48, which reads as oversized in list-view rows where
         * surrounding sprites are 16x16; theme-material ships a
         * 20x20 variant. Rather than pick a canonical size here or
         * touch theme assets, we tag the elements so each consumer
         * (turba browse list, kronolith attendee panel, imp contact
         * details, ...) sizes them to fit its own layout. See
         * turba#64. */
        if (!empty($mapurl)) {
            $html .= '&nbsp;&nbsp;' . Horde::link(Horde::externalUrl($mapurl), $desc, null, '_blank') . Horde_Themes_Image::tag($icon, ['alt' => $desc, 'attr' => ['class' => 'horde-map-icon']]) . '</a>';
        }
        if (!empty($mapurl2)) {
            $html .= '&nbsp;' . Horde::link(Horde::externalUrl($mapurl2), $desc2, null, '_blank') . Horde_Themes_Image::tag($icon2, ['alt' => $desc2, 'attr' => ['class' => 'horde-map-icon']]) . '</a>';
        }

        /* Google generated map. */
        if ($address) {
            $html .= '&nbsp;' . Horde::link(Horde::externalUrl('http://maps.google.com/maps?q=' . urlencode(preg_replace('/\r?\n/', ',', $address)) . '&hl=en'), Horde_Core_Translation::t('Google Maps'), null, '_blank') . Horde_Themes_Image::tag($google_icon, ['alt' => Horde_Core_Translation::t('Google Maps'), 'attr' => ['class' => 'horde-map-icon']]) . '</a>';
        }

        return $html;
    }

    protected function _renderVarDisplay_addresslink($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_address($form, $var, $vars, false);
    }

    protected function _renderVarDisplay_pgp($form, &$var, &$vars)
    {
        $key = $var->getValue($vars);
        if (empty($key)) {
            return '';
        }
        return '<pre>'
            . $GLOBALS['injector']->getInstance('Horde_Core_Factory_Crypt')->create('Pgp', $var->getPGPParams())->pgpPrettyKey($key)
            . '</pre>';
    }

    protected function _renderVarDisplay_smime($form, &$var, &$vars)
    {
        $cert = $var->getValue($vars);
        if (empty($cert)) {
            return '';
        }
        try {
            return $GLOBALS['injector']
                ->getInstance('Horde_Core_Factory_Crypt')
                ->create('Smime', $var->getSMIMEParams())
                ->certToHTML($cert);
        } catch (Horde_Crypt_Exception $e) {
            return $e->getMessage();
        }
    }

    protected function _renderVarDisplay_country($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_enum($form, $var, $vars);
    }

    protected function _renderVarDisplay_date($form, &$var, &$vars)
    {
        return htmlspecialchars((string) $var->getFormattedTime($var->getValue($vars)));
    }

    protected function _renderVarDisplay_hourminutesecond($form, &$var, &$vars)
    {
        $time = $var->getTimeParts($var->getValue($vars));
        if (!$var->getProperty('show_seconds')) {
            return (int) $time['hour'] . ':' . sprintf('%02d', (int) $time['minute']);
        } else {
            return (int) $time['hour'] . ':' . sprintf('%02d', (int) $time['minute']) . ':' . sprintf('%02d', (int) $time['second']);
        }
    }

    protected function _renderVarDisplay_monthyear($form, &$var, &$vars)
    {
        return (int) $vars->get($var->getVarName() . '[month]') . ', ' . (int) $vars->get($var->getVarName() . '[year]');
    }

    protected function _renderVarDisplay_monthdayyear($form, &$var, &$vars)
    {
        $date = $var->getValue($vars);
        if ((is_array($date) && !empty($date['year'])
             && !empty($date['month']) && !empty($date['day']))
            || (!is_array($date) && !empty($date) && $date != '0000-00-00')) {
            try {
                return $var->formatDate($date);
            } catch (Horde_Date_Exception $e) {
                return $date;
            }
        }
        return '';
    }

    protected function _renderVarDisplay_datetime($form, &$var, &$vars)
    {
        $value = $var->getValue($vars);
        try {
            $html = htmlspecialchars((string) $var->formatDate($value));
        } catch (Horde_Date_Exception $e) {
            return $value;
        }

        if (!$var->emptyDateArray($value)) {
            $html .= (new Horde_Form_Type_date())->getAgo($value);
        }
        return $html;
    }

    protected function _renderVarDisplay_colorpicker($form, &$var, &$vars)
    {
        $color = $var->getValue($vars);
        return '<span style="background-color:' . htmlspecialchars((string) $color) . ';color:'
            . (Horde_Image::brightness($color) < 128 ? '#fff' : '#000')
            . '">' . $color . '</span>';
    }

    protected function _renderVarDisplay_invalid($form, &$var, &$vars)
    {
        return '<span class="form-error">' . htmlspecialchars((string) $var->getMessage()) . '</span>';
    }

    protected function _renderVarDisplay_link($form, &$var, &$vars)
    {
        $values = $var->values;
        if (!isset($values[0])) {
            $values = [$values];
        }

        $count = count($values);
        $html = '';
        for ($i = 0; $i < $count; $i++) {
            if (empty($values[$i]['url']) || empty($values[$i]['text'])) {
                continue;
            }
            if (!isset($values[$i]['target'])) {
                $values[$i]['target'] = '';
            }
            if (!isset($values[$i]['onclick'])) {
                $values[$i]['onclick'] = '';
            }
            if (!isset($values[$i]['title'])) {
                $values[$i]['title'] = '';
            }
            if (!isset($values[$i]['accesskey'])) {
                $values[$i]['accesskey'] = '';
            }
            $class = $values[$i]['class']
                ?? 'widget';
            if ($i > 0) {
                $html .= ' | ';
            }
            $html .= Horde::link($values[$i]['url'], $values[$i]['text'], $class, $values[$i]['target'], $values[$i]['onclick'], $values[$i]['title'], $values[$i]['accesskey']) . htmlspecialchars((string) $values[$i]['text']) . '</a>';
        }

        return $html;
    }

    protected function _renderVarDisplay_dblookup($form, &$var, &$vars)
    {
        return $this->_renderVarDisplay_enum($form, $var, $vars);
    }

    protected function _renderVarDisplay_figlet($form, &$var, &$vars)
    {
        static $figlet;

        if (!isset($figlet)) {
            $figlet = new Text_Figlet();
        }

        $result = $figlet->loadFont($var->getFont());
        if (is_a($result, 'PEAR_Error')) {
            return $result->getMessage();
        }

        return '<pre>' . $figlet->lineEcho($var->getText()) . '</pre>';
    }

    protected function _renderVarDisplay_captcha($form, &$var, &$vars)
    {
        static $captcha;

        if (!isset($captcha)) {
            $captcha = Text_CAPTCHA::factory('Image');
        }

        $image = $captcha->init(
            150,
            60,
            $var->getText(),
            ['font_path' => dirname($var->getFont()) . '/',
                'font_file' => basename($var->getFont())]
        );
        if (is_a($image, 'PEAR_Error')) {
            return $image->getMessage();
        }

        $cid = hash('md5', $var->getText());
        $cache = $GLOBALS['injector']->getInstance('Horde_Cache');

        $cache->set($cid, serialize(['data' => $captcha->getCAPTCHAAsJPEG(),
            'ctype' => 'image/jpeg']));

        $url = Horde::url($GLOBALS['registry']->get('webroot', 'horde') . '/services/cacheview.php')->add('cid', $cid);

        return '<img src="' . $url . '" />';

    }

    protected function _renderVarInput_selectFiles($form, &$var, &$vars)
    {
        /* Needed for gollem js calls */
        $html = sprintf(
            '<input type="hidden" name="%s" id="%s" value="%s" />',
            'selectlist_selectid',
            'selectlist_selectid',
            $var->getProperty('selectid')
        )
            . sprintf('<input type="hidden" name="%s" id="%s" />', 'actionID', 'actionID')

            /* Form field. */
            . sprintf(
                '<input type="hidden" name="%s" id="%s" value="%s" />',
                htmlspecialchars((string) $var->getVarName()),
                $this->_genID($var->getVarName(), false),
                $var->getProperty('selectid')
            );

        /* Open window link. */
        $param = [$var->getProperty('link_text'),
            $var->getProperty('link_style'),
            $form->getName(),
            $var->getProperty('icon'),
            $var->getProperty('selectid')];
        $html .= $GLOBALS['registry']->call('files/selectlistLink', $param) . "<br />\n";

        if ($var->getProperty('selectid')) {
            $param = [$var->getProperty('selectid')];
            $files = $GLOBALS['registry']->call('files/selectlistResults', $param);
            if ($files) {
                $html .= '<ol>';
                foreach ($files as $file) {
                    $dir = key($file);
                    $filename = current($file);
                    if ($GLOBALS['registry']->hasMethod('files/getViewLink')) {
                        $filename = basename($filename);
                        $url = $GLOBALS['registry']->call('files/getViewLink', [$dir, $filename]);
                        $filename = Horde::link($url, Horde_Core_Translation::t('Preview'), null, 'form_file_view') . htmlspecialchars(Util::realPath($dir . '/' . $filename)) . '</a>';
                    } else {
                        if (!empty($dir) && ($dir != '.')) {
                            $filename = $dir . '/' . $filename;
                        }
                        $filename = htmlspecialchars((string) $filename);
                    }
                    $html .= '<li>' . $filename . "</li>\n";
                }
                $html .= '</ol>';
            }
        }

        return $html;
    }

    protected function _renderVarInput_category($form, &$var, &$vars)
    {
        $GLOBALS['injector']->getInstance('Horde_PageOutput')->addScriptFile('form_helpers.js', 'horde');
        $this->_addOnLoadJavascript('addEvent(document.getElementById(\'' . $form->getName() . '\'), \'submit\', checkCategory);');
        return '<input type="hidden" name="new_category" />'
            . Horde_Prefs_CategoryManager::getJavaScript($form->getName(), $var->getVarName())
            . Horde_Prefs_CategoryManager::getSelect($var->getVarName(), $var->getValue($vars));
    }

    public function selectOptions(
        &$values,
        $selectedValue = false,
        $htmlchars = false
    ) {
        $result = '';
        $sel = false;
        foreach ($values as $value => $display) {
            if (!is_null($selectedValue) && !$sel
                && $value == $selectedValue
                && strlen($value) == strlen($selectedValue)) {
                $selected = ' selected="selected"';
                $sel = true;
            } else {
                $selected = '';
            }
            $result .= ' <option value="';
            $result .= $htmlchars
                ? $value
                : htmlspecialchars((string) $value);
            $result .= '"' . $selected . '>';
            $result .= $htmlchars
                ? $display
                : htmlspecialchars((string) $display);
            $result .= "</option>\n";
        }

        return $result;
    }

    protected function _multiSelectOptions(&$values, $selectedValues)
    {
        if (!is_array($selectedValues)) {
            $selectedValues = [];
        } else {
            $selectedValues = array_flip($selectedValues);
        }

        $result = '';
        foreach ($values as $value => $display) {
            $selected = isset($selectedValues[$value])
                ? ' selected="selected"'
                : '';
            $result .= ' <option value="' . htmlspecialchars((string) $value) . "\"$selected>" . htmlspecialchars((string) $display) . "</option>\n";
        }

        return $result;
    }

    protected function _checkBoxes($name, $values, $checkedValues, $actions = '')
    {
        $result = '';
        if (!is_array($checkedValues)) {
            $checkedValues = [];
        }
        $i = 0;
        foreach ($values as $value => $display) {
            $checked = (in_array($value, $checkedValues)) ? ' checked="checked"' : '';
            $result .= sprintf(
                '<input id="%s%s" type="checkbox" class="checkbox" name="%s[]" value="%s"%s%s /><label for="%s%s">&nbsp;%s</label><br />',
                htmlspecialchars((string) $name),
                $i,
                htmlspecialchars((string) $name),
                htmlspecialchars((string) $value),
                $checked,
                $actions,
                htmlspecialchars((string) $name),
                $i,
                htmlspecialchars((string) $display)
            );
            $i++;
        }

        return $result;
    }

    protected function _radioButtons($name, $values, $checkedValue = null, $actions = '')
    {
        $result = '';
        $i = 0;
        foreach ((array) $values as $value => $display) {
            $checked = (!is_null($checkedValue) && $value == $checkedValue) ? ' checked="checked"' : '';
            $result .= sprintf(
                '<input id="%s%s" type="radio" class="checkbox" name="%s" value="%s"%s%s /><label for="%s%s">&nbsp;%s</label><br />',
                htmlspecialchars((string) $name),
                $i,
                htmlspecialchars((string) $name),
                htmlspecialchars((string) $value),
                $checked,
                $actions,
                htmlspecialchars((string) $name),
                $i,
                htmlspecialchars((string) $display)
            );
            $i++;
        }

        return $result;
    }

    protected function _genID($name, $fulltag = true)
    {
        $name = htmlspecialchars(preg_replace('/[^A-Za-z0-9-_:.]+/', '_', $name));
        return $fulltag ? 'id="' . $name . '"' : $name;
    }

    protected function _genActionScript($form, $action, $varname)
    {
        $html = '';
        $triggers = $action->getTrigger();
        if (!is_array($triggers)) {
            $triggers = [$triggers];
        }
        $js = $action->getActionScript($form, $this, $varname);
        foreach ($triggers as $trigger) {
            if ($trigger == 'onload') {
                $this->_addOnLoadJavascript($js);
            } else {
                $html .= ' ' . $trigger . '="' . $js . '"';
            }
        }
        return $html;
    }

    protected function _getActionScripts($form, &$var)
    {
        $actions = '';
        if ($var->hasAction()) {
            $varname = $var->getVarName();
            $action = &$var->_action;
            $triggers = $action->getTrigger();
            if (!is_array($triggers)) {
                $triggers = [$triggers];
            }
            $js = $action->getActionScript($form, $this, $varname);
            foreach ($triggers as $trigger) {
                if ($trigger == 'onload') {
                    $this->_addOnLoadJavascript($js);
                } else {
                    $actions .= ' ' . $trigger . '="' . $js . '"';
                }
            }
        }
        return $actions;
    }

    protected function _addOnLoadJavascript($script)
    {
        $this->_onLoadJS[] = $script;
    }

    public function renderEnd()
    {
        if (count($this->_onLoadJS)) {
            $GLOBALS['page_output']->addInlineScript(implode("\n", $this->_onLoadJS), 'dom');
        } else {
            return '';
        }
    }

}
