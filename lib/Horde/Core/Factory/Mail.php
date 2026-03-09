<?php
/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

/**
 * A Horde_Injector based factory for creating a Horde_Mail_Transport object.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @since     2.5.0
 * @package   Core
 */
class Horde_Core_Factory_Mail extends Horde_Core_Factory_Base
{
    /**
     * Return the Horde_Mail instance.
     *
     * @param array $config  If null, use Horde defaults. Otherwise, an
     *                       array with two keys:
     * <pre>
     *   - params: (array) Configuration parameters.
     *   - transport: (string) Transport driver.
     * </pre>
     *
     * @return Horde_Mail_Transport  The singleton instance.
     * @throws Horde_Exception
     */
    public function create($config = null)
    {
        if (is_null($config)) {
            [$transport, $params] = $this->getConfig();
        } else {
            $transport = $config['transport'];
            $params = $config['params'];
        }

        if (strcasecmp($transport, 'smtp') === 0) {
            if (empty($params['lmtp'])) {
                $transport = 'Smtphorde';
                /* Explicitly set secure parameter, if not set in config. */
                if (!isset($params['secure'])) {
                    $params['secure'] = true;
                }
            } else {
                unset($params['lmtp']);
                $transport = 'Lmtphorde';
            }
        }

        if (empty($params['auth'])) {
            unset($params['username'], $params['password']);
        }

        /* TODO: Default to port 25 for H5. Change to 587 for H6. */
        if (empty($params['port'])) {
            $params['port'] = 25;
        }

        $class = $this->_getDriverName($transport, 'Horde_Mail_Transport');
        $ob = new $class($params);

        if (!empty($params['sendmail_eol'])
            && (strcasecmp($transport, 'sendmail') == 0)) {
            $ob->sep = $params['sendmail_eol'];
        }

        return $ob;
    }

    /**
     * Return the mailer configuration.
     *
     * @return array  Two-element array: transport driver (string) and
     *                configuration parameters (array).
     */
    public function getConfig()
    {
        global $conf, $registry;

        $transport = isset($conf['mailer']['type'])
            ? Horde_String::lower($conf['mailer']['type'])
            : 'null';
        $params = $conf['mailer']['params']
            ?? [];

        /* Add username/password options now, regardless of current value of
         * 'auth'. Will remove in create() if final config doesn't require
         * authentication. Need isAuthenticated() check since we may be
         * running from CLI with 'user_admin' registry flag, which sets
         * the authentication name but not the credentials. */
        if (strcasecmp($transport, 'smtp') === 0) {
            if ($registry->isAuthenticated() &&
                strlen((string) ($auth = $registry->getAuth()))) {
                /* Try to get SMTP credentials via hook (e.g. for XOAUTH2 support). */
                try {
                    $hooks = $this->_injector->getInstance('Horde_Core_Hooks');
                    $smtp_creds = $hooks->callHook('smtp_credentials', 'horde', [$auth]);

                    // Hook returned XOAUTH2 credentials
                    if (isset($smtp_creds['xoauth2_token'])) {
                        $params['xoauth2_token'] = $smtp_creds['xoauth2_token'];
                        if (isset($smtp_creds['username'])) {
                            $params['username'] = $smtp_creds['username'];
                        }
                        // Don't set password when using XOAUTH2
                    } else {
                        // Hook returned regular credentials
                        if (isset($smtp_creds['username'])) {
                            $params['username'] = $smtp_creds['username'];
                        } elseif (!empty($params['username_auth'])) {
                            $params['username'] = $auth;
                        }

                        if (isset($smtp_creds['password'])) {
                            $params['password'] = $smtp_creds['password'];
                        } elseif (!empty($params['password_auth'])) {
                            $params['password'] = $registry->getAuthCredential('password');
                        }
                    }
                } catch (Horde_Exception_HookNotSet $e) {
                    // No hook defined, use default username/password
                    if (!empty($params['username_auth'])) {
                        $params['username'] = $auth;
                    }
                    if (!empty($params['password_auth'])) {
                        $params['password'] = $registry->getAuthCredential('password');
                    }
                }
            }

            unset($params['password_auth'], $params['username_auth']);
        }

        return [$transport, $params];
    }

}
