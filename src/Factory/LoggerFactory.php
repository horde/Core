<?php

/**
 * Horde/Log PSR-3 Logger Factory
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 *
 */

namespace Horde\Core\Factory;

use Horde_Core_Factory_Injector;
use Horde\Log\Logger;
use Horde\Log\LogException;
use Horde\Core\Config\LoggerConfig;
use Horde\Injector\Injector;
use Horde\Log\LogHandler;
use Horde\Log\LogLevels;

/**
 * LoggerFactory builds modular loggers with multiple handlers
 *
 */
class LoggerFactory extends Horde_Core_Factory_Injector
{
    private LoggerConfig $config;
    private LogLevels $levels;
    private Logger $logger;

    /**
     * Keyed list of handlers, to prevent doubles
     *
     * @var LogHandler[]
     */
    private array $handlers = [];
    private LogHandlerFactory $handlerFactory;

    /**
     * Constructor
     *
     * @param LoggerConfig $config Logger configuration service
     * @param LogHandlerFactory $handlerFactory Handler factory
     */
    public function __construct(LoggerConfig $config, LogHandlerFactory $handlerFactory)
    {
        $this->config = $config;
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * Create default logger
     *
     * This creates a logger with:
     * - one handler based on the horde config or no handler,
     * - canonical log levels,
     * - the PSR-3 formatter and depending on options, another formatter,
     * - a handler-level filter by loglevel
     *
     * @param Injector $injector
     * @return Logger
     * @throws LogException
     */
    public function create(Injector $injector): Logger
    {
        $this->levels = LogLevels::initWithCanonicalLevels();
        $handlers = $this->predefinedHandlers();
        $handlers[] = $this->handlerFactory->create($injector);
        return new Logger($handlers, $this->levels);
    }

    /**
     * Integration point
     *
     * @return LogHandler[]
     */
    public function predefinedHandlers(): array
    {
        // TODO
        return [];
    }
}
