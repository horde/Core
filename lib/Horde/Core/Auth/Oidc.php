<?php
/**
 * Horde_Core_Auth_Oidc - Auth driver for OIDC/OAuth2 sessions.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Jean Charles Delépine <jean.charles.delepine@u-picardie.fr>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Auth_Oidc extends Horde_Auth_Base
{
    /**
     * Driver capabilities.
     */
    protected $_capabilities = [
        'transparent' => true,
        'list'        => true,
        'logout'      => true,
    ];

    /**
     * Constructor
     */
    public function __construct(array $params = [])
    {
        parent::__construct($params);

        // Disable user listing if LDAP is not configured.
        if (empty($GLOBALS['conf']['ldap']['hostspec'])) {
            $this->_capabilities['list'] = false;
        }
    }

    /**
     * Validate authentication on each Horde request.
     *
     * Returns true if the user has OAuth2 tokens stored, false otherwise.
     * Safe failure policy: any unexpected error returns true to avoid
     * logging the user out due to a transient infrastructure failure.
     *
     * @return bool True if the session should be considered valid.
     */
    public function validateAuth(): bool
    {
        global $injector;

        $username = $GLOBALS['registry']->getAuth();
        if (!$username) {
            return true;
        }

        try {
            $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
            $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);
        } catch (Exception $e) {
            // Infrastructure unavailable — allow session to continue.
            return true;
        }

        foreach ($providerConfig->listEnabled() as $row) {
           if ($tokenService->hasTokens($username, $row['provider_id'])) {
               return true;
           }
        }

        $this->setError(Horde_Auth::REASON_SESSION);
        return false;
    }

    /**
     * Logout — no-op.
     *
     * Token revocation and SLO are handled by OidcPreLogoutHandler,
     * which runs before clearAuth() inside LoginService::performLogout().
     *
     * @return bool
     */
    public function logout(): bool
    {
        return true;
    }

    /**
     * Authentication — not used directly.
     *
     * Authentication is handled by OAuthAccountController via
     * the /auth/oauth/login flow.
     */
    protected function _authenticate($userId, $credentials): void
    {
        throw new Horde_Auth_Exception('Direct authentication not supported; use OIDC login flow.');
    }

    /**
     * List all users from LDAP.
     *
     * Uses $conf['ldap']['user'] configuration (basedn, uid, filter).
     *
     * @param bool $sort Sort the user list.
     * @return array Array of user IDs.
     * @throws Horde_Auth_Exception
     */
    public function listUsers($sort = false): array
    {
        global $conf;

        $ldapParams = $conf['ldap']['user'] ?? null;
        if (empty($ldapParams['basedn']) || empty($ldapParams['uid'])) {
            throw new Horde_Auth_Exception('LDAP user configuration missing (ldap.user.basedn or ldap.user.uid)');
        }

        try {
            $ldap = $GLOBALS['injector']
                ->getInstance('Horde_Core_Factory_Ldap')
                ->create('horde', 'ldap');

            $uid    = $ldapParams['uid'];
            $filter = !empty($ldapParams['filter'])
                ? Horde_Ldap_Filter::build(['filter' => $ldapParams['filter']])
                : Horde_Ldap_Filter::create('objectClass', 'present');

            $search = $ldap->search(
                $ldapParams['basedn'],
                $filter,
                ['attributes' => [$uid]]
            );

            $users = [];
            foreach ($search as $entry) {
                if ($entry->exists($uid)) {
                    $users[] = $entry->getValue($uid, 'single');
                }
            }

            if ($sort) {
                sort($users);
            }

            return $users;

        } catch (Horde_Ldap_Exception $e) {
            throw new Horde_Auth_Exception('LDAP listUsers failed: ' . $e->getMessage());
        }
    }
}
