<?php
use Horde\Injector\Injector;

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Group extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        $driver = Horde_String::ucfirst($GLOBALS['conf']['group']['driver']);
        $params = Horde::getDriverConfig('group', $driver);
        // Safeguard against empty driver config.
        if (empty($driver)) {
            $driver = 'Mock';
            if (empty($params)) {
                $params = [];
            }
        }
        if (!empty($GLOBALS['conf']['group']['cache'])) {
            $params['cache'] = $injector->getInstance('Horde_Cache');
        }

        switch ($driver) {
            case 'Contactlists':
                $class = 'Horde_Group_Contactlists';
                $params['api'] = $GLOBALS['registry']->contacts;
                break;

            case 'Ldap':
                $class = 'Horde_Core_Group_Ldap';
                $params['ldap'] = $injector
                    ->getInstance('Horde_Core_Factory_Ldap')
                    ->create('horde', 'group');
                break;

            case 'Sql':
                $class = 'Horde_Group_Sql';
                $params['db'] = $injector
                    ->getInstance('Horde_Core_Factory_Db')
                    ->create('horde', 'group');
                break;

            default:
                $class = $this->_getDriverName($driver, 'Horde_Group');
                break;
        }

        return new $class($params);
    }

}
