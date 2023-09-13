<?php
/**
 * Provides methods used to handle error reporting.
 *
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package  Core
 */
class Horde_ErrorHandler
{
    /**
     * Aborts with a fatal error, displaying debug information to the user.
     *
     * @param mixed $error  Either a string or an object with a getMessage()
     *                      method (e.g. PEAR_Error, Exception).
     */
    public static function fatal($error)
    {
        global $registry;

        if (is_object($error)) {
            switch (get_class($error)) {
            case 'Horde_Exception_AuthenticationFailure':
                $auth_app = !$registry->clearAuthApp($error->application);

                if ($auth_app &&
                    $registry->isAuthenticated(array('app' => $error->application, 'notransparent' => true))) {
                    break;
                }

                try {
                    Horde::log($error, 'NOTICE');
                } catch (Exception $e) {}

                if (Horde_Cli::runningFromCLI()) {
                    $cli = new Horde_Cli();
                    $cli->fatal($error);
                }

                $params = array();

                if ($registry->getAuth()) {
                    $params['app'] = $error->application;
                }

                switch ($error->getCode()) {
                case Horde_Auth::REASON_MESSAGE:
                    $params['msg'] = $error->getMessage();
                    $params['reason'] = $error->getCode();
                    break;
                }

                $logout_url = $registry->getLogoutUrl($params);

                /* Clear authentication here. Otherwise, there might be
                 * issues on the login page since we would otherwise need
                 * to do session token checking (which might not be
                 * available, so logout won't happen, etc...) */
                if ($auth_app && array_key_exists('app', $params)) {
                    $registry->clearAuth();
                }

                $logout_url->redirect();
            }
        }

        try {
            Horde::log($error, 'EMERG');
        } catch (Exception $e) {}

        try {
            $cli = Horde_Cli::runningFromCLI();
        } catch (Exception $e) {
            die($e);
        }

        if ($cli) {
            $cli = new Horde_Cli();
            $cli->fatal($error);
        }

        if (!headers_sent()) {
            header('Content-type: text/html; charset=UTF-8');
            header('HTTP/1.1 500: Internal Server Error');
        }
        
        try {
            $admin = (isset($registry) && $registry->isAdmin());
            $errorHtml = self::getHtmlForError($error, $admin);
        } catch (Exception $e) {
            die($e);
        }

        ob_start();
        echo $errorHtml;
        ob_end_flush();
        exit(1);
    }

    /**
     * PHP legacy error handling (non-Exceptions).
     *
     * @param integer $errno     See set_error_handler().
     * @param string $errstr     See set_error_handler().
     * @param string $errfile    See set_error_handler().
     * @param integer $errline   See set_error_handler().
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $er = error_reporting();

        // Calls prefixed with '@'.
        if ($er == 0) {
            // Must return false to populate $php_errormsg (as of PHP 5.2).
            return false;
        }

        if (!($er & $errno) || !class_exists('Horde_Log')) {
            return;
        }

        $options = array();

        try {
            switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                $priority = Horde_Log::WARN;
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
                $priority = Horde_Log::NOTICE;
                break;

            case E_STRICT:
                $options['notracelog'] = true;
                $priority = Horde_Log::DEBUG;
                break;

            default:
                $priority = Horde_Log::DEBUG;
                break;
            }

            Horde::log(new ErrorException('PHP ERROR: ' . $errstr, 0, $errno, $errfile, $errline), $priority, $options);
        } catch (Exception $e) {}
    }

    /**
     * Catch fatal errors.
     */
    public static function catchFatalError()
    {
        $error = error_get_last();
        if ($error['type'] == E_ERROR) {
            self::fatal(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    /**
     * Returns html for an error
     * 
     * @param string|Throwable $error  The error as string message or Throwable
     * @param bool $isAdmin  If true will also output the complete trace
     *                       If $error is a Throwable its complete content will also be included in the output
     * @return string  The full html page for that error as a string
     */
    public static function getHtmlForError($error, bool $isAdmin = false): string
    {
        if ($error instanceof Throwable) {
            $message = htmlspecialchars($error->getMessage());

        } else {
            $message = $error;
        }
        $fatalErrorHasOccoured = Horde_Core_Translation::t('A fatal error has occurred');
        if ($isAdmin){
            $detailsHtml = self::getErrorDetailsAsHtml($error);
        } else {
            $detailsHtml = '<h3>' . Horde_Core_Translation::t("Details have been logged for the administrator.") . '</h3>';
        }
        return <<<HTMLDELIM
            <html>
            <head>
            <title>Horde :: Fatal Error</title>
            <meta charset="utf-8">
            </head>
            <body style="background:#fff; color:#000">
            <h1>{$fatalErrorHasOccoured}</h1>
                <h3>{$message}</h3>
                {$detailsHtml}
            </body>
            </html>
            HTMLDELIM;

    }

    /**
     * Get the details of an error as html. This should usually only be output to admin users
     * 
     * @param string|Throwable $error  The error as string message or Throwable
     * @return string  The details of that error as html
     */
    protected static function getErrorDetailsAsHtml($error): string
    {
        if ($error instanceof Throwable) {
            $trace = strval(new Horde_Support_Backtrace($error));
            $fileName = $error->getFile();
            $lineNo = $error->getLine();

            $translateDetails = Horde_Core_Translation::t('Details');
            $translateLogInfo = Horde_Core_Translation::t('The full error message is logged in Horde\'s log file, and is shown below only to administrators. Non-administrative users will not see error details.');
            $fullErrorAsHtml = htmlspecialchars(print_r($error, true));
            $fullDetailsHtml = <<<HTMLDELIM
                <h3>$translateDetails</h3>
                <h4>{$translateLogInfo}</h4>
                <div id="details">
                    <pre>$fullErrorAsHtml</pre>
                </div>
                HTMLDELIM;
        } else {
            $trace = debug_backtrace();
            $calling = array_shift($trace);
            $trace = strval(new Horde_Support_Backtrace($trace));
            $fileName = $calling['file'];
            $lineNo = $calling['line'];
            $fullDetailsHtml = '';
        }
        $translateIn = sprintf(Horde_Core_Translation::t('in %s:%d'), $fileName, $lineNo);

        return <<<HTMLDELIM
            {$translateIn}
            <div id="backtrace">
            <pre>
            {$trace}
            </pre>
            </div>
            {$fullDetailsHtml}
            HTMLDELIM;
    }
}
