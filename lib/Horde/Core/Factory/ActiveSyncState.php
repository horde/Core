<?php

use Horde\Injector\Injector;

/**
 * @category Horde
 * @package Core
 */
class Horde_Core_Factory_ActiveSyncState extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        global $conf;

        if (!empty($conf['activesync']['enabled'])) {
            $driver = !empty($conf['activesync']['storage'])
                ? $conf['activesync']['storage']
                : 'sql';
            switch (Horde_String::lower($driver)) {
                case 'nosql':
                    $nosql = $injector->get(Horde_Core_Factory_Nosql::class)->create('horde', 'activesync');
                    return new Horde_ActiveSync_State_Mongo([
                        'connection' => $nosql,
                    ]);

                case 'sql':
                    return new Horde_ActiveSync_State_Sql([
                        'db' => $injector->get(Horde_Core_Factory_Db::class)->create('horde', 'activesync'),
                    ]);
            }
        }

        throw new Horde_Exception('ActiveSync is disabled.');
    }

}
