<?php

declare(strict_types=1);

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

namespace Horde\Core\Ajax;

use Horde\Core\Api\ApiInterfaceListProvider;
use Horde\Core\Api\ApiRegistry;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Util\Variables;
use Horde_Core_Ajax_Application_Handler;
use Horde_Core_Ajax_Response;
use Horde_Core_Ajax_Response_HordeCore;
use Horde_Exception;
use Horde_Exception_HookNotSet;
use Horde_Injector;
use Horde_Registry;
use Horde_Variables;
use InvalidArgumentException;
use stdClass;
use Throwable;

abstract class Application
{
    /**
     * The data returned from the doAction() call.
     *
     * @var mixed
     */
    public $data = null;

    /**
     * The list of (possibly) unsolicited tasks/data to do for this request.
     *
     * @var object
     */
    public $tasks = null;

    /**
     * The action to perform.
     *
     * @var string
     */
    protected $_action;

    /**
     * The Horde application.
     *
     * @var string
     */
    protected $_app;

    /**
     * AJAX method handlers.
     *
     * @var array
     */
    protected $_handlers = [];

    /**
     * The request variables.
     *
     * @var Horde_Variables|Variables
     */
    protected $_vars;

    /**
     * Constructor.
     *
     * @param string $app            The application name.
     * @param Horde_Variables|Variables $vars  Form/request data.
     * @param string $action         The AJAX action to perform.
     * @param string $token          Session token.
     *
     * @throws Horde_Exception
     */
    public function __construct(
        $app,
        Horde_Variables|Variables $vars,
        $action = null,
        $token = null,
    ) {
        global $registry, $session;

        $this->_app = $app;
        $this->_vars = $vars;
        $this->_action = $action;

        $this->_init();

        $auth = $registry->currentProcessAuth();
        $ob = $this->_getHandler();

        /* Non-authenticated actions MUST occur in a handler. */
        if (!$ob && !$auth) {
            throw new Horde_Exception('Accessing AJAX action without being authenticated.');
        }

        /* Check authentication/token. */
        if ($ob && !$ob->external($action)) {
            if (!$auth) {
                throw new Horde_Exception('Accessing AJAX action without being authenticated.');
            }
            $session->checkToken($token);
        }

        /* Check for session regeneration request. */
        if ($vars->regenerate_sid) {
            $session->regenerate();
            if (SID) {
                $this->addTask('sid', SID, 'horde');
            }
        }

        /* Close session if action is labeled as read-only. */
        if ($ob && $ob->readonly($action)) {
            $session->close();
        }
    }

    /**
     * Application initialization code.
     */
    protected function _init()
    {
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
            case 'app':
                return $this->_app;

            case 'vars':
                return $this->_vars;
        }
    }

    /**
     * Add an AJAX method handler.
     *
     * @param string $class  Classname of a Handler to add.
     *
     * @return Horde_Core_Ajax_Application_Handler  Handler object.
     */
    final public function addHandler($class)
    {
        if (!isset($this->_handlers[$class])) {
            if (!class_exists($class)
                || !($ob = new $class($this))
                || !($ob instanceof Horde_Core_Ajax_Application_Handler)) {
                throw new InvalidArgumentException('Bad AJAX handler: ' . $class);
            }

            $this->_handlers[$class] = $ob;
        }

        return $this->_handlers[$class];
    }

    /**
     * Performs the AJAX action. The AJAX action should return either raw data
     * (which will be output to the browser to be parsed by the HordeCore JS
     * framework), or a Horde_Ajax_Core_Response object, which will be sent
     * unaltered.
     *
     * @throws Horde_Exception
     */
    public function doAction()
    {
        global $injector;

        if (!strlen((string) $this->_action)) {
            return;
        }

        $hooks = $injector->getInstance('Horde_Core_Hooks');

        /* Look for action in helpers. */
        if ($ob = $this->_getHandler()) {
            $this->data = call_user_func([$ob, $this->_action]);
        } elseif (($result = $this->_tryApiRegistry($injector)) !== null) {
            $this->data = $result;
        } else {
            /* Look for action in application hook. */
            try {
                $this->data = $hooks->callHook(
                    'ajaxaction_handle',
                    $this->_app,
                    [$this, $this->_action],
                );
            } catch (Horde_Exception $e) {
                /* DEPRECATED hook. @deprecated */
                try {
                    $this->data = $hooks->callHook(
                        'ajaxaction',
                        $this->_app,
                        [$this->_action, $this->_vars],
                    );
                } catch (Horde_Exception $e) {
                    throw new Horde_Exception('Handler for action "' . $this->_action . '" does not exist.');
                }
            }
        }

        try {
            $this->data = $hooks->callHook(
                'ajaxaction_data',
                $this->_app,
                [$this->_action, $this->data],
            );
        } catch (Horde_Exception_HookNotSet $e) {
        }
    }

    /**
     * Add task to response data.
     *
     * @param string $name  Task name.
     * @param mixed $data   Task data.
     * @param string $app   Overwrite default application (since 2.5.0).
     */
    public function addTask($name, $data, $app = null)
    {
        if (empty($this->tasks)) {
            $this->tasks = new stdClass();
        }

        $name = (is_null($app) ? $this->_app : $app) . ':' . $name;
        $this->tasks->$name = $data;
    }

    /**
     * Send AJAX response to the browser.
     */
    public function send()
    {
        if ($GLOBALS['session']->regenerate_due) {
            $this->addTask('regenerate_sid', true, 'horde');
        }

        if ($this->data instanceof Horde_Core_Ajax_Response) {
            $response = clone $this->data;
            if ($response instanceof Horde_Core_Ajax_Response_HordeCore) {
                $response->tasks = $this->tasks;
            }
        } else {
            $response = new Horde_Core_Ajax_Response_HordeCore($this->data, $this->tasks);
        }
        $response->sendAndExit();
    }

    /**
     * Explicitly call an action.
     *
     * @since 2.5.0
     *
     * @param string $action  The action to call.
     *
     * @return mixed  The response from the called action.
     */
    public function callAction($action)
    {
        foreach ($this->_handlers as $ob) {
            if ($ob->has($action)) {
                return call_user_func([$ob, $action]);
            }
        }
    }

    /**
     * Return the Handler for the current action.
     *
     * @return mixed  A Horde_Core_Ajax_Application_Handler object, or null if
     *                handler is not found.
     */
    protected function _getHandler()
    {
        foreach ($this->_handlers as $ob) {
            if ($ob->has($this->_action)) {
                return $ob;
            }
        }

        return null;
    }

    /**
     * Try to dispatch the current action via the ApiRegistry.
     *
     * Looks up the app's Api class; if it implements
     * ApiInterfaceListProvider, iterates its interfaces searching for
     * one that exposes the current action. Returns the result value
     * on match, or null when no provider handles the action.
     *
     * Silently returns null when the ApiRegistry is not yet wired or
     * the app has no modern Api class, so legacy hook dispatch can
     * proceed.
     *
     * @param Horde_Injector $injector
     *
     * @return mixed|null  The provider result value, or null if not handled.
     */
    protected function _tryApiRegistry($injector)
    {
        try {
            $apiRegistry = $injector->getInstance(ApiRegistry::class);
        } catch (Throwable $e) {
            return null;
        }

        $apiClass = 'Horde\\' . ucfirst($this->_app) . '\\Api';
        if (!class_exists($apiClass)) {
            return null;
        }

        try {
            $api = $injector->getInstance($apiClass);
        } catch (Throwable $e) {
            return null;
        }

        if (!($api instanceof ApiInterfaceListProvider)) {
            return null;
        }

        foreach (array_keys($api->getApiInterfaceList()) as $interface) {
            $qualifiedMethod = $interface . '.' . $this->_action;
            if ($apiRegistry->hasMethod($qualifiedMethod)) {
                $context = new ApiCallContext([
                    'userId' => $GLOBALS['registry']->getAuth(),
                    'permissions' => [],
                    'transport' => 'ajax',
                    'app' => $this->_app,
                ]);
                $result = $apiRegistry->invoke(
                    $qualifiedMethod,
                    iterator_to_array($this->_vars),
                    $context,
                );
                return $result->value;
            }
        }

        return null;
    }
}
