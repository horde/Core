<?php
/**
 * Horde/Log PSR-3 Logger Factory
 *
 * @author Ralf Lang <lang@b1-systems.de>
 *
 */

namespace Horde\Core\Factory;

use Horde_Core_Factory_Injector;
use Horde\Log\Logger;
use Horde\Log\LogException;
use Horde\Core\Config\State;
use Horde\Injector\Injector;
use Horde\Log\LogHandler;
use Horde\Log\LogLevels;

/**
 * LoggerFactory builds modular loggers with multiple handlers
 *
 */
class LoggerFactory extends Horde_Core_Factory_Injector
{
    private State $conf;
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
     *
     * @param State $config The conf.php values for the global log handler
     *
     */
    public function __construct(State $config, LogHandlerFactory $handlerFactory)
    {
        $this->conf = $config;
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * Create default logger
     *
     * This creates a logger with
     * - one handler based on the horde config or no handler,
     * - canonic log levels,
     * - the PSR-3 formatter and depending on options, another formatter,
     * - a handler-level filter by loglevel
     *
     * TODO: Mechanism to add specialised handlers without a configuration mess.
     *
     * @param Injector $injector
     * @return Logger
     * @throws LogException
     */
    public function create(Injector $injector): Logger
    {
        $conf = $this->conf->toArray();
        $this->levels = LogLevels::initWithCanonicalLevels();
        //
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
