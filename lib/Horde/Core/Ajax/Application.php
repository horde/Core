<?php

/**
 * Defines the AJAX interface for an application.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 *
 * @property string $app  The current application
 * @property Horde_Variables|Variables $vars  The Variables object.
 */
use Horde\Core\Ajax\Application;

abstract class Horde_Core_Ajax_Application extends Application
{
}
