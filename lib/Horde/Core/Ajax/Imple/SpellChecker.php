<?php

/**
 * Imple to attach the spellchecker to an HTML element.
 *
 * Copyright 2005-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
use Horde\Core\Horde;

class Horde_Core_Ajax_Imple_SpellChecker extends Horde_Core_Ajax_Imple
{
    /**
     * @param array $params  OPTIONAL configuration parameters:
     *   - locales: (array) List of supported locales.
     *   - states: (array) TODO
     *   - targetId: (string) TODO
     */
    public function __construct(array $params = [])
    {
        global $language, $registry;

        if (!isset($params['targetId'])) {
            $params['targetId'] = strval(new Horde_Support_Randomid());
        }

        if (!isset($params['locales'])) {
            $key_list = array_keys($registry->nlsconfig->spelling);
            asort($key_list, SORT_LOCALE_STRING);

            $params['locales'] = [];
            foreach ($key_list as $lcode) {
                $params['locales'][] = [
                    'l' => $registry->nlsconfig->languages[$lcode],
                    's' => $lcode == $language,
                    'v' => $lcode,
                ];
            }
        }

        parent::__construct($params);
    }

    /**
     */
    protected function _attach($init)
    {
        global $page_output;

        if ($init) {
            $page_output->addScriptFile('spellchecker.js', 'horde');
            $page_output->addScriptPackage('Horde_Core_Script_Package_Keynavlist');

            $page_output->addInlineJsVars([
                'HordeImple.SpellChecker' => new stdClass(),
            ]);
        }

        $dom_id = $this->getDomId();

        $opts = [
            'locales' => $this->_params['locales'],
            'statusButton' => $dom_id,
            'target' => $this->_params['targetId'],
            'url' => strval($this->getImpleUrl()->setRaw(true)->add(['input' => $this->_params['targetId']])),
        ];
        if (isset($this->_params['states'])) {
            $opts['bs'] = $this->_params['states'];
        }

        $page_output->addInlineScript([
            'HordeImple.SpellChecker.' . $dom_id . '=new SpellChecker(' . Horde_Serialize::serialize($opts, Horde_Serialize::JSON) . ')',
        ], true);

        return false;
    }

    /**
     * Form variables used:
     *   - input
     */
    protected function _handle(Horde_Variables $vars)
    {
        global $injector;

        $args = ['html' => !empty($vars->html)];
        if (isset($vars->locale)) {
            $args['locale'] = $vars->locale;
        }
        $input = $vars->get($vars->input);

        try {
            return new Horde_Core_Ajax_Response_Prototypejs(
                $injector->getInstance('Horde_Core_Factory_SpellChecker')->create($args, $input)->spellCheck($input)
            );
        } catch (Horde_Exception $e) {
            Horde::log($e, Horde_Log::ERR);
            return [
                'bad' => [],
                'suggestions' => [],
            ];
        }
    }

}
