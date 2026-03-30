<?php

/**
 * @category Horde
 * @package Core
 */
class Horde_Core_Factory_ActiveSyncBackend extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector $injector)
    {
        global $conf, $registry;

        // Backend driver and dependencies
        $params = ['registry' => $registry];
        $adapter_params = ['factory' => new Horde_Core_ActiveSync_Imap_Factory()];

        // Determine emailsync setting - force to off if we don't have a mail API.
        // Use local variable instead of mutating global $conf.
        $emailsyncEnabled = !empty($conf['activesync']['emailsync'])
            && $registry->hasInterface('mail');

        $driver_params = [
            'connector' => new Horde_Core_ActiveSync_Connector($params),
            'imap' => $emailsyncEnabled
                ? new Horde_ActiveSync_Imap_Adapter($adapter_params)
                : null,
            'ping' => $conf['activesync']['ping'],
            'state' => $injector->getInstance('Horde_ActiveSyncState'),
            'auth' => $this->_getAuth(),
            'cache' => $injector->getInstance('Horde_Cache')];

        return new Horde_Core_ActiveSync_Driver($driver_params);
    }

    /**
     * Factory for ActiveSync Auth object.
     *
     * @return Horde_Core_ActiveSync_Auth
     */
    protected function _getAuth()
    {
        global $conf, $injector;

        $params = [
            'base_driver' => $injector->getInstance('Horde_Core_Factory_Auth')->create(),
        ];

        if ($conf['activesync']['auth']['type'] != 'basic') {
            $x_params = $conf['activesync']['auth']['params'];
            $x_params['default_user'] = $GLOBALS['registry']->getAuth();
            $x_params['logger'] = $this->_injector->getInstance('Horde_Log_Logger');
            $params['transparent_driver'] = Horde_Auth::factory('Horde_Core_Auth_X509', $x_params);
        }

        $obj = new Horde_Core_ActiveSync_Auth($params);
        return $obj;
    }

}
