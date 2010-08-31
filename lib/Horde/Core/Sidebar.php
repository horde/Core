<?php
/**
 * This class provides the code needed to generate the Horde sidebar.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package  Core
 */
class Horde_Core_Sidebar
{
    /**
     * Generate the sidebar tree object.
     *
     * @return Horde_Tree_Base  The sidebar tree object.
     */
    public function getTree()
    {
        global $injector, $registry;

        $isAdmin = $registry->isAdmin();
        $menu = $parents = array();

        foreach ($registry->listApps(array('active', 'admin', 'heading', 'notoolbar', 'sidebar'), true) as $app => $params) {
            /* Check if the current user has permisson to see this
             * application, and if the application is active. Headings are
             * visible to everyone (but get filtered out later if they
             * have no children). Administrators always see all
             * applications except those marked 'inactive'. */
            if ($isAdmin ||
                ($params['status'] == 'heading') ||
                ($registry->hasPermission($app, Horde_Perms::SHOW) &&
                 in_array($params['status'], array('active', 'sidebar')))) {
                $menu[$app] = $params;

                if (isset($params['menu_parent'])) {
                    $children[$params['menu_parent']] = true;
                }
            }
        }

        foreach (array_keys($menu) as $key) {
            if (($menu[$key]['status'] == 'heading') &&
                !isset($children[$key])) {
                unset($menu[$key]);
            }
        }

        // Add the administration menu if the user is an admin.
        if ($isAdmin) {
            $menu['administration'] = array(
                'name' => _("Administration"),
                'icon' => Horde_Themes::img('administration.png'),
                'status' => 'heading'
            );

            try {
                foreach ($registry->callByPackage('horde', 'admin_list') as $method => $val) {
                    $menu['administration_' . $method] = array(
                        'icon' => $val['icon'],
                        'menu_parent' => 'administration',
                        'name' => Horde::stripAccessKey($val['name']),
                        'status' => 'active',
                        'url' => Horde::url($registry->applicationWebPath($val['link'], 'horde')),
                    );
                }
            } catch (Horde_Exception $e) {}
        }

        if (Horde_Menu::showService('options') &&
            !($injector->getInstance('Horde_Prefs')->getPrefs() instanceof Horde_Prefs_Session)) {
            $menu['options'] = array(
                'icon' => Horde_Themes::img('prefs.png'),
                'name' => _("Options"),
                'status' => 'active'
            );

            /* Get a list of configurable applications. */
            $prefs_apps = $registry->listApps(array('active', 'admin'), true, Horde_Perms::READ);

            if (!empty($prefs_apps['horde'])) {
                $menu['options_' . 'horde'] = array(
                    'icon' => $registry->get('icon', 'horde'),
                    'menu_parent' => 'options',
                    'name' => _("Global Options"),
                    'status' => 'active',
                    'url' => Horde::getServiceLink('options', 'horde')
                );
                unset($prefs_apps['horde']);
            }

            asort($prefs_apps);
            foreach ($prefs_apps as $app => $params) {
                $menu['options_' . $app] = array(
                    'icon' => $registry->get('icon', $app),
                    'menu_parent' => 'options',
                    'name' => $params['name'],
                    'status' => 'active',
                    'url' => Horde::getServiceLink('options', $app)
                );
            }
        }

        if ($registry->getAuth()) {
            $menu['logout'] = array(
                'icon' => Horde_Themes::img('logout.png'),
                'name' => _("Log out"),
                'status' => 'active',
                'url' => Horde::getServiceLink('logout', 'horde')
            );
        } else {
            $menu['login'] = array(
                'icon' => Horde_Themes::img('login.png'),
                'name' => _("Log in"),
                'status' => 'active',
                'url' => Horde::getServiceLink('login', 'horde')
            );
        }

        // Set up the tree.
        $tree = $injector->getInstance('Horde_Tree')->getTree('horde_sidebar', 'Javascript', array('jsvar' => 'HordeSidebar.tree'));

        foreach ($menu as $app => $params) {
            switch ($params['status']) {
            case 'sidebar':
                try {
                    $registry->callAppMethod($params['app'], 'sidebarCreate', array('args' => array($tree, empty($params['menu_parent']) ? null : $params['menu_parent'], isset($params['sidebar_params']) ? $params['sidebar_params'] : array())));
                } catch (Horde_Exception $e) {
                    Horde::logMessage($e, 'ERR');
                }
                break;

            default:
                // Need to run the name through gettext since the user's
                // locale may not have been loaded when registry.php was
                // parsed.
                $name = _($params['name']);

                // Headings have no webroot; they're just containers for other
                // menu items.
                if (isset($params['url'])) {
                    $url = $params['url'];
                } elseif (($params['status'] == 'heading') ||
                          !isset($params['webroot'])) {
                    $url = null;
                } else {
                    $url = Horde::url($registry->getInitialPage($app));
                }

                $tree->addNode(
                    $app,
                    empty($params['menu_parent']) ? null : $params['menu_parent'],
                    $name,
                    0,
                    false,
                    array(
                        'icon' => strval((isset($params['icon']) ? $params['icon'] : $registry->get('icon', $app))),
                        'target' => isset($params['target']) ? $params['target'] : null,
                        'url' => $url
                    )
                );
                break;
            }
        }

        return $tree;
    }

}
