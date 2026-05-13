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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Horde;
use Horde\Db\Adapter;
use Horde\Db\Adapter\Mysqli as ModernMysqli;
use Horde\Db\Adapter\Pdo\Mysql as PdoMysql;
use Horde\Db\Adapter\Pdo\Pgsql as PdoPgsql;
use Horde\Db\Adapter\Pdo\Sqlite as PdoSqlite;
use Horde\Db\Adapter\Oci8 as ModernOci8;
use Horde\Injector\Injector;
use Horde_String;
use Horde_Exception;
use Throwable;

class DbAdapterFactory
{
    private const PHPTYPE_MAP = [
        'mysqli' => ModernMysqli::class,
        'mysql' => PdoMysql::class,
        'pgsql' => PdoPgsql::class,
        'sqlite' => PdoSqlite::class,
        'oci8' => ModernOci8::class,
    ];

    public function create(Injector $injector): Adapter
    {
        $config = Horde::getDriverConfig('', 'sql');

        if (empty($config['phptype'])) {
            throw new Horde_Exception('The database configuration is missing.');
        }

        $phptype = $config['phptype'];
        $class = self::PHPTYPE_MAP[$phptype]
            ?? 'Horde\\Db\\Adapter\\Pdo\\' . Horde_String::ucfirst($phptype);

        if (!empty($config['hostspec'])) {
            $config['host'] = $config['hostspec'];
            unset($config['hostspec']);
        }

        unset($config['driverconfig']);

        if (!isset($config['charset'])) {
            $config['charset'] = 'UTF-8';
        }

        $ob = new $class($config);

        try {
            $ob->setCache($injector->getInstance('Horde_Cache'));
        } catch (Throwable) {
        }

        try {
            $ob->setLogger($injector->getInstance('Horde_Log_Logger'));
        } catch (Throwable) {
        }

        return $ob;
    }
}
