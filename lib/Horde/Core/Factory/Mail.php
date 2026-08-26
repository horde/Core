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
         * authentication. Need the isAuthenticated() check since we may be
         * running from CLI with the 'user_admin' registry flag. That flag
         * sets the authentication name but not the credentials.
         *
         * password_auth means "use the logged-in user's password for SMTP".
         * CLI tools like horde-alarms authenticate by name only. They have
         * no session password. So getAuthCredential('password') comes back
         * empty. When password_auth wants that missing password, we derive
         * neither the username nor the password and keep both master values.
         * Pairing the auth username with the master password would mismatch
         * the SMTP account. This keeps the fix in the mail factory. It does
         * not fabricate a session. See the review on horde/Core#219.
         *
         * Problem originally reported and fixed by Torben Dannhauer
         * <torben@dannhauer.de> in horde/Core#219. */
        if (strcasecmp($transport, 'smtp') === 0) {
            if ($registry->isAuthenticated()
                && strlen((string) ($auth = $registry->getAuth()))) {
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
                        // Hook returned regular credentials. Resolve the
                        // session password once. When password_auth wants
                        // it but the session has none, derive neither field
                        // and keep both master values. Pairing the auth
                        // username with the master password would mismatch
                        // the SMTP account.
                        $cred = $registry->getAuthCredential('password');
                        $hasCred = strlen((string) $cred) > 0;

                        if (isset($smtp_creds['username'])) {
                            $params['username'] = $smtp_creds['username'];
                        } elseif (!empty($params['username_auth'])
                            && (empty($params['password_auth']) || $hasCred)) {
                            $params['username'] = $auth;
                        }

                        if (isset($smtp_creds['password'])) {
                            $params['password'] = $smtp_creds['password'];
                        } elseif (!empty($params['password_auth']) && $hasCred) {
                            $params['password'] = $cred;
                        }
                    }
                } catch (Horde_Exception_HookNotSet $e) {
                    // No hook defined, use default username/password. Same
                    // pairing rule as above: skip the auth username when
                    // password_auth wants a session password we don't have.
                    $cred = $registry->getAuthCredential('password');
                    $hasCred = strlen((string) $cred) > 0;

                    if (!empty($params['username_auth'])
                        && (empty($params['password_auth']) || $hasCred)) {
                        $params['username'] = $auth;
                    }
                    if (!empty($params['password_auth']) && $hasCred) {
                        $params['password'] = $cred;
                    }
                }
            }

            unset($params['password_auth'], $params['username_auth']);
        }

        return [$transport, $params];
    }

}
