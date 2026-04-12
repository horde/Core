<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Editor;

use Horde\Editor\Tinymce;
use Horde\Editor\TinymceConfig;
use Horde\Editor\EditorResult;
use Horde_Script_File_External;

/**
 * Bridge between the Tinymce driver and Horde_PageOutput.
 *
 * Translates EditorResult (files + scripts) into addScriptFile() and
 * addInlineScript() calls so the editor loads in the browser.
 *
 * Equivalent to Horde_Core_Editor_Ckeditor for the CKEditor path.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class TinymcePageBinder
{
    public function __construct(
        private Tinymce $tinymce,
    ) {}

    /**
     * Initialize TinyMCE and register scripts with PageOutput.
     *
     * @param array $params  'id' => textarea HTML ID, 'config' => TinymceConfig|null
     */
    public function initialize(array $params = []): void
    {
        global $page_output, $registry;

        $config = $params['config'] ?? null;
        $result = $this->tinymce->initialize(
            textareaId: $params['id'] ?? '',
            config: $config,
        );

        $jsUri = $registry->get('jsuri', 'horde');

        foreach ($result->files as $file) {
            $page_output->addScriptFile(
                new Horde_Script_File_External($jsUri . '/' . $file)
            );
        }

        if ($result->scripts !== []) {
            $page_output->addInlineScript($result->scripts, true);
        }
    }
}
