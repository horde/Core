<?php
/**
 * Copyright 2013-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */

use Sabre\DAV;
use Sabre\DAVACL;
use Sabre\CalDAV;
use Sabre\CardDAV;

/**
 * Factory for the DAV server.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class Horde_Core_Factory_DavServer extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector $injector)
    {
        global $conf, $registry;

        $principalBackend = new Horde_Dav_Principals(
            new Horde_Core_Auth_UsernameHook(
                array(
                    'base' => $injector
                        ->getInstance('Horde_Core_Factory_Auth')
                        ->create()
                )
            ),
            $injector->getInstance('Horde_Core_Factory_Identity_DavUsernameHook')
        );
        $principals = new DAVACL\PrincipalCollection($principalBackend);
        $principals->disableListing = $conf['auth']['list_users'] == 'input';

        $calendarBackend = new Horde_Dav_Calendar_Backend($registry, $injector->getInstance('Horde_Dav_Storage'));
        $caldav = new CalDAV\CalendarRoot($principalBackend, $calendarBackend);
        $contactsBackend = new Horde_Dav_Contacts_Backend($registry);
        $carddav = new CardDAV\AddressBookRoot($principalBackend, $contactsBackend);

        $server = new DAV\Server(
            new Horde_Dav_RootCollection(
                $registry,
                array($principals, $caldav, $carddav),
                isset($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null
            )
        );
        $server->debugExceptions = false;
        $davBaseUri = $registry->get('webroot', 'horde')
            . ($GLOBALS['conf']['urls']['pretty'] == 'rewrite' ? '/rpc/' : '/rpc.php/');

        // Check if we need to honor an override config
        // TODO: Refactor to str_starts_with when minimum PHP version becomes 8
        if (!empty($conf['dav_root'])) {
            $candidates = explode(';', $conf['dav_root']);
            // Ensure longer hits overrule shorter hits regardless of input order
            usort($candidates, function($a, $b) { return strlen($a) <=> strlen($b);});
            foreach ($candidates as $davBaseTest) {
                if (!empty($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], $davBaseTest) === 0)) {
                    $davBaseUri = $davBaseTest;
                }
            }
        }
        $server->setBaseUri($davBaseUri);
        $server->addPlugin(
            new DAV\Auth\Plugin(
                new Horde_Core_Dav_Auth(
                    $injector->getInstance('Horde_Core_Factory_Auth')->create()
                ),
                'Horde DAV Server'
            )
        );
        $server->addPlugin(
            new CalDAV\Plugin()
        );
        $server->addPlugin(
            new CardDAV\Plugin()
        );
        $server->addPlugin(
            new DAV\Locks\Plugin(
                new Horde_Dav_Locks($registry, $injector->getInstance('Horde_Lock'))
            )
        );
        /**
         * Since SabreDAV 3.2, we need to explicitly handle access for unauthenticated
         * Callers. For now, just disable unauthenticated calendar access altogether.
         */
        $aclPlugin = new DAVACL\Plugin();
        $aclPlugin->allowUnauthenticatedAccess = false;
        $server->addPlugin($aclPlugin);
        $server->addPlugin(new DAV\Browser\Plugin());

        return $server;
    }
}
