<?php

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Logger extends Horde_Core_Factory_Injector
{
    /**
     * Stores the exception if the logger could not be started.
     *
     * @var Horde_Log_Exception
     */
    public $error;

    /**
     * Log queue.
     *
     * @var array
     */
    protected static $_queue;

    /**
     * Logger configuration service
     *
     * @var Horde\Core\Config\LoggerConfig
     */
    private $config;

    /**
     */
    public function create(Horde_Injector $injector)
    {
        // Get LoggerConfig service (autowired by injector)
        if ($this->config === null) {
            $this->config = $injector->getInstance('Horde\\Core\\Config\\LoggerConfig');
        }

        $this->error = null;

        /* Default handler if logging is disabled. */
        if (!$this->config->isEnabled()) {
            return new Horde_Core_Log_Logger(new Horde_Log_Handler_Null());
        }

        switch ($this->config->getType()) {
            case 'file':
            case 'stream':
                $append = ($this->config->getType() === 'file')
                    ? $this->config->getAppendMode()
                    : null;

                switch ($this->config->getFormat()) {
                    case 'custom':
                        $formatter = ($this->config->getTemplate() !== null)
                            ? new Horde_Log_Formatter_Simple(['format' => $this->config->getTemplate()])
                            : null;
                        break;

                    case 'default':
                    default:
                        // Use Horde_Log defaults.
                        $formatter = null;
                        break;

                    case 'xml':
                        $formatter = new Horde_Log_Formatter_Xml();
                        break;
                }

                try {
                    $handler = new Horde_Log_Handler_Stream($this->config->getName(), $append, $formatter);
                } catch (Horde_Log_Exception $e) {
                    $this->error = $e;
                    return new Horde_Core_Log_Logger(new Horde_Log_Handler_Null());
                }
                try {
                    $handler->setOption('ident', $this->config->getIdent());
                } catch (Horde_Log_Exception $e) {
                }
                break;

            case 'syslog':
                try {
                    $handler = new Horde_Log_Handler_Syslog();
                    $facility = $this->config->getFacility();
                    if ($facility !== null) {
                        $handler->setOption('facility', $facility);
                    }
                    $ident = $this->config->getIdent();
                    if (!empty($ident)) {
                        $handler->setOption('ident', $ident);
                    }
                } catch (Horde_Log_Exception $e) {
                    $this->error = $e;
                    return new Horde_Core_Log_Logger(new Horde_Log_Handler_Null());
                }
                break;

            case 'null':
            default:
                // Use default null handler.
                return new Horde_Core_Log_Logger(new Horde_Log_Handler_Null());
        }

        $handler->addFilter($this->config->getPriorityValue());

        try {
            /* Horde_Core_Log_Logger contains code to format the log
             * message. */
            $ob = new Horde_Core_Log_Logger($handler);
            self::processQueue($ob);
            return $ob;
        } catch (Horde_Log_Exception $e) {
            $this->error = $e;
            return new Horde_Core_Log_Logger(new Horde_Log_Handler_Null());
        }
    }

    /**
     * Is the logger available?
     *
     * @return boolean  True if logging is available.
     */
    public static function available()
    {
        return (isset($GLOBALS['registry']) && $GLOBALS['registry']->hordeInit);
    }

    /**
     * Queue log entries to output once the framework is initialized.
     */
    public static function queue(Horde_Core_Log_Object $ob)
    {
        if (!isset(self::$_queue)) {
            self::$_queue = [];
            register_shutdown_function([__CLASS__, 'processQueue']);
        }

        self::$_queue[] = $ob;
    }

    /**
     * Process the log queue.
     */
    public static function processQueue($logger = null)
    {
        try {
            if (empty(self::$_queue) || !self::available()) {
                return;
            }

            if (is_null($logger)) {
                $logger = $GLOBALS['injector']->getInstance('Horde_Log_Logger');
            }

            foreach (self::$_queue as $val) {
                $logger->logObject($val);
            }
        } catch (Exception $e) {
        }

        self::$_queue = [];
    }

}
