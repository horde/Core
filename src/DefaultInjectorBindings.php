<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core;

use Horde\Core\Api\ApiRegistry;
use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Editor\TinymcePageBinder;
use Horde\Core\Factory\ApiRegistryFactory;
use Horde\Core\Factory\AuthBaseFactory;
use Horde\Core\Factory\ConfigMetadataProviderFactory;
use Horde\Core\Factory\DbAdapterFactory;
use Horde\Core\Factory\DriverRepositoryFactory;
use Horde\Core\Factory\EventDispatcherFactory;
use Horde\Core\Factory\HttpClientFactory;
use Horde\Core\Factory\LoggerFactory;
use Horde\Core\Factory\AuthLinkRepositoryFactory;
use Horde\Core\Factory\IdentityHistoryRepositoryFactory;
use Horde\Core\Factory\IdentityRepositoryFactory;
use Horde\Core\Factory\OAuthProviderConfigRepositoryFactory;
use Horde\Core\Factory\OAuthAccessTokenRepositoryFactory;
use Horde\Core\Factory\OAuthAuthorizationCodeRepositoryFactory;
use Horde\Core\Factory\OAuthClientRepositoryFactory;
use Horde\Core\Factory\OAuthConsentRepositoryFactory;
use Horde\Core\Factory\OAuthRefreshTokenRepositoryFactory;
use Horde\Core\Factory\OAuthScopeRepositoryFactory;
use Horde\Core\Factory\OAuthSigningKeyFactory;
use Horde\Core\Factory\OAuthClientAuthenticatorFactory;
use Horde\Core\Factory\OAuthAccessTokenIssuerFactory;
use Horde\Core\Factory\OAuthRefreshTokenIssuerFactory;
use Horde\Core\Factory\OAuthAuthorizationCodeGrantFactory;
use Horde\Core\Factory\OAuthClientCredentialsGrantFactory;
use Horde\Core\Factory\OAuthRefreshTokenGrantFactory;
use Horde\Core\Factory\OAuthTokenEndpointFactory;
use Horde\Core\Factory\OAuthAuthorizationEndpointFactory;
use Horde\Core\Factory\OAuthRevocationEndpointFactory;
use Horde\Core\Factory\OAuthIntrospectionEndpointFactory;
use Horde\Core\Factory\OAuthServerMetadataFactory;
use Horde\Core\Factory\OAuthDiscoveryEndpointFactory;
use Horde\Core\Factory\OAuthUserinfoEndpointFactory;
use Horde\Core\Factory\OAuthJwksEndpointFactory;
use Horde\Core\Factory\OAuthClaimsMapperFactory;
use Horde\Core\Factory\OAuthScopeClaimsMappingFactory;
use Horde\Core\Factory\OAuthIdTokenBuilderFactory;
use Horde\Core\Factory\OAuthConsentMiddlewareFactory;
use Horde\Core\Factory\OAuthFlowStoreFactory;
use Horde\Core\Factory\SecretManagerFactory;
use Horde\Core\Factory\SessionHandlerFactory;
use Horde\Core\Factory\SimpleCacheFactory;
use Horde\Core\Factory\TinymceFactory;
use Horde\Core\Factory\TinymcePageBinderFactory;
use Horde\Core\Middleware\OAuthConsentMiddleware;
use Horde\Core\Service\OAuthHttpClientService;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Db\Adapter as DbAdapter;
use Horde\Editor\Tinymce;
use Horde\Horde\Factory\OAuthHttpClientServiceFactory as BaseOAuthHttpClientServiceFactory;
use Horde\Horde\Factory\OAuthTokenServiceFactory as BaseOAuthTokenServiceFactory;
use Horde\Horde\Service\AuthLinkRepository;
use Horde\Identity\IdentityHistoryRepository;
use Horde\Identity\IdentityRepository;
use Horde\Jwt\Key\PrivateKey;
use Horde\Log\Logger as HordeLogger;
use Horde\OAuth\Client\OAuthFlowStore;
use Horde\OAuth\Oidc\ClaimsMapper;
use Horde\OAuth\Oidc\Handler\DiscoveryEndpoint;
use Horde\OAuth\Oidc\Handler\JwksEndpoint;
use Horde\OAuth\Oidc\Handler\UserinfoEndpoint;
use Horde\OAuth\Oidc\IdTokenBuilder;
use Horde\OAuth\Oidc\ScopeClaimsMapping;
use Horde\OAuth\Server\ClientAuthentication\ClientAuthenticatorChain;
use Horde\OAuth\Server\Grant\AuthorizationCodeGrant;
use Horde\OAuth\Server\Grant\ClientCredentialsGrant;
use Horde\OAuth\Server\Grant\RefreshTokenGrant;
use Horde\OAuth\Server\Handler\AuthorizationEndpoint;
use Horde\OAuth\Server\Handler\IntrospectionEndpoint;
use Horde\OAuth\Server\Handler\RevocationEndpoint;
use Horde\OAuth\Server\Handler\TokenEndpoint;
use Horde\OAuth\Server\Repository\AccessTokenRepository;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;
use Horde\OAuth\Server\Repository\ClientRepository;
use Horde\OAuth\Server\Repository\ConsentRepository;
use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Horde\OAuth\Server\ServerMetadata;
use Horde\OAuth\Server\Token\AccessTokenIssuer;
use Horde\OAuth\Server\Token\RefreshTokenIssuer;
use Horde\Routes\Mapper as HordeRoutesMapper;
use Horde\Secret\SecretManager;
use Horde\SessionHandler\SessionHandler;
use Horde\Http\RequestFactory;
use Horde_Injector;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

class DefaultInjectorBindings implements InjectorBindings
{
    public function register(Horde_Injector $injector): void
    {
        $factories = [
            'Horde_ActiveSyncBackend' => 'Horde_Core_Factory_ActiveSyncBackend',
            'Horde_ActiveSyncServer' => 'Horde_Core_Factory_ActiveSyncServer',
            'Horde_ActiveSyncState' => 'Horde_Core_Factory_ActiveSyncState',
            'Horde_Alarm' => 'Horde_Core_Factory_Alarm',
            'Horde_Browser' => 'Horde_Core_Factory_Browser',
            'Horde_Cache' => 'Horde_Core_Factory_Cache',
            'Horde_Controller_Request' => 'Horde_Core_Factory_Request',
            'Horde_Controller_RequestConfiguration' => [
                'Horde_Core_Controller_RequestMapper',
                'getRequestConfiguration',
            ],
            'Horde_Core_Auth_Signup' => 'Horde_Core_Factory_AuthSignup',
            'Horde_Auth_Base' => AuthBaseFactory::class,
            ApiRegistry::class => ApiRegistryFactory::class,
            'Horde_Core_CssCache' => 'Horde_Core_Factory_CssCache',
            'Horde_Core_JavascriptCache' => 'Horde_Core_Factory_JavascriptCache',
            'Horde_Core_Perms' => 'Horde_Core_Factory_PermsCore',
            'Horde_Dav_Server' => 'Horde_Core_Factory_DavServer',
            'Horde_Dav_Storage' => 'Horde_Core_Factory_DavStorage',
            'Horde_Db_Adapter' => 'Horde_Core_Factory_DbBase',
            'Horde_Editor' => 'Horde_Core_Factory_Editor',
            'Horde_ElasticSearch_Client' => 'Horde_Core_Factory_ElasticSearch',
            'Horde_Group' => 'Horde_Core_Factory_Group',
            'Horde_Group_Base' => 'Horde_Core_Factory_Group',
            'Horde_HashTable' => 'Horde_Core_Factory_HashTable',
            'Horde_History' => 'Horde_Core_Factory_History',
            'Horde_Lock' => 'Horde_Core_Factory_Lock',
            'Horde_Log_Logger' => 'Horde_Core_Factory_Logger',
            'Horde_Mail' => 'Horde_Core_Factory_MailBase',
            'Horde_Memcache' => 'Horde_Core_Factory_Memcache',
            'Horde_Nosql_Adapter' => 'Horde_Core_Factory_NosqlBase',
            'Horde_Notification' => 'Horde_Core_Factory_Notification',
            'Horde_Notification_Handler' => 'Horde_Core_Factory_Notification',
            'Horde_Perms' => 'Horde_Core_Factory_Perms',
            'Horde_Perms_Base' => 'Horde_Core_Factory_Perms',
            'Horde_Queue_Storage' => 'Horde_Core_Factory_QueueStorage',
            'Horde_Routes_Mapper' => 'Horde_Core_Factory_Mapper',
            HordeRoutesMapper::class => 'Horde_Core_Factory_Mapper',
            'Horde_Routes_Matcher' => 'Horde_Core_Factory_Matcher',
            'Horde_Secret' => 'Horde_Core_Factory_Secret',
            'Horde_Secret_Cbc' => 'Horde_Core_Factory_Secret_Cbc',
            SecretManager::class => SecretManagerFactory::class,
            DbAdapter::class => DbAdapterFactory::class,
            OAuthProviderConfigRepository::class => OAuthProviderConfigRepositoryFactory::class,
            OAuthFlowStore::class => OAuthFlowStoreFactory::class,
            OAuthTokenService::class => BaseOAuthTokenServiceFactory::class,
            OAuthHttpClientService::class => BaseOAuthHttpClientServiceFactory::class,
            IdentityRepository::class => IdentityRepositoryFactory::class,
            IdentityHistoryRepository::class => IdentityHistoryRepositoryFactory::class,
            AuthLinkRepository::class => AuthLinkRepositoryFactory::class,
            ClientRepository::class => OAuthClientRepositoryFactory::class,
            AccessTokenRepository::class => OAuthAccessTokenRepositoryFactory::class,
            RefreshTokenRepository::class => OAuthRefreshTokenRepositoryFactory::class,
            AuthorizationCodeRepository::class => OAuthAuthorizationCodeRepositoryFactory::class,
            ConsentRepository::class => OAuthConsentRepositoryFactory::class,
            ScopeRepository::class => OAuthScopeRepositoryFactory::class,
            PrivateKey::class => OAuthSigningKeyFactory::class,
            ClientAuthenticatorChain::class => OAuthClientAuthenticatorFactory::class,
            AccessTokenIssuer::class => OAuthAccessTokenIssuerFactory::class,
            RefreshTokenIssuer::class => OAuthRefreshTokenIssuerFactory::class,
            AuthorizationCodeGrant::class => OAuthAuthorizationCodeGrantFactory::class,
            ClientCredentialsGrant::class => OAuthClientCredentialsGrantFactory::class,
            RefreshTokenGrant::class => OAuthRefreshTokenGrantFactory::class,
            TokenEndpoint::class => OAuthTokenEndpointFactory::class,
            AuthorizationEndpoint::class => OAuthAuthorizationEndpointFactory::class,
            RevocationEndpoint::class => OAuthRevocationEndpointFactory::class,
            IntrospectionEndpoint::class => OAuthIntrospectionEndpointFactory::class,
            ServerMetadata::class => OAuthServerMetadataFactory::class,
            DiscoveryEndpoint::class => OAuthDiscoveryEndpointFactory::class,
            ClaimsMapper::class => OAuthClaimsMapperFactory::class,
            ScopeClaimsMapping::class => OAuthScopeClaimsMappingFactory::class,
            UserinfoEndpoint::class => OAuthUserinfoEndpointFactory::class,
            JwksEndpoint::class => OAuthJwksEndpointFactory::class,
            IdTokenBuilder::class => OAuthIdTokenBuilderFactory::class,
            OAuthConsentMiddleware::class => OAuthConsentMiddlewareFactory::class,
            'Horde_Service_Facebook' => 'Horde_Core_Factory_Facebook',
            'Horde_Service_Twitter' => 'Horde_Core_Factory_Twitter',
            'Horde_Service_UrlShortener' => 'Horde_Core_Factory_UrlShortener',
            'Horde_SessionHandler' => 'Horde_Core_Factory_SessionHandler',
            SessionHandler::class => SessionHandlerFactory::class,
            'Horde_Template' => 'Horde_Core_Factory_Template',
            'Horde_Timezone' => 'Horde_Core_Factory_Timezone',
            'Horde_Token' => 'Horde_Core_Factory_Token',
            \Horde\Token\Token::class => \Horde\Core\Factory\TokenServiceFactory::class,
            'Horde_Variables' => 'Horde_Core_Factory_Variables',
            'Horde_View' => 'Horde_Core_Factory_View',
            'Horde_View_Base' => 'Horde_Core_Factory_View',
            'Horde_Weather' => 'Horde_Core_Factory_Weather',
            'Net_DNS2_Resolver' => 'Horde_Core_Factory_Dns',
            'Text_LanguageDetect' => 'Horde_Core_Factory_LanguageDetect',
            \Horde\Core\Middleware\AuthHttpBasic::class => \Horde\Core\Factory\AuthHttpBasicFactory::class,
            HordeLogger::class => LoggerFactory::class,
            PsrLoggerInterface::class => LoggerFactory::class,
            'Horde\\Horde\\Service\\JwtService' => 'Horde\\Horde\\Factory\\JwtServiceFactory',
            'Horde\\Horde\\Service\\AuthenticationService' => 'Horde\\Horde\\Factory\\AuthenticationServiceFactory',
            'Horde\\Core\\Config\\ConfigLoader' => 'Horde\\Core\\Factory\\ConfigLoaderFactory',
            DriverRepository::class => DriverRepositoryFactory::class,
            ConfigMetadataProvider::class => ConfigMetadataProviderFactory::class,
            'Horde\\Core\\Service\\HordeDbService' => 'Horde\\Core\\Factory\\DbServiceFactory',
            'Horde\\Core\\Service\\PrefsService' => 'Horde\\Core\\Factory\\PrefsServiceFactory',
            'Horde\\Core\\Service\\IdentityService' => 'Horde\\Core\\Factory\\IdentityServiceFactory',
            'Horde\\Core\\Service\\GroupService' => 'Horde\\Core\\Factory\\GroupServiceFactory',
            'Horde\\Core\\Config\\RegistryConfigLoader' => 'Horde\\Core\\Factory\\RegistryConfigLoaderFactory',
            'Horde\\Core\\Service\\ApplicationService' => 'Horde\\Core\\Factory\\ApplicationServiceFactory',
            'Horde\\Core\\Auth\\AuthService' => 'Horde\\Core\\Factory\\AuthServiceFactory',
            'Horde\\Core\\Service\\HordeLdapService' => 'Horde\\Core\\Factory\\HordeLdapServiceFactory',
            'Horde\\Core\\Service\\PermissionService' => 'Horde\\Core\\Factory\\PermissionServiceFactory',
            Tinymce::class => TinymceFactory::class,
            TinymcePageBinder::class => TinymcePageBinderFactory::class,
            EventDispatcherInterface::class => [EventDispatcherFactory::class, 'create'],
            ListenerProviderInterface::class => [EventDispatcherFactory::class, 'createListenerProvider'],
            SimpleCacheInterface::class => SimpleCacheFactory::class,
            PsrHttpClientInterface::class => HttpClientFactory::class,
        ];

        $implementations = [
            'Horde_Controller_ResponseWriter' => 'Horde_Controller_ResponseWriter_Web',
            RequestFactoryInterface::class => RequestFactory::class,
        ];

        foreach ($factories as $key => $val) {
            if (is_string($val)) {
                $val = [$val, 'create'];
            }
            $injector->bindFactory($key, $val[0], $val[1]);
        }
        foreach ($implementations as $key => $val) {
            $injector->bindImplementation($key, $val);
        }

        $injector->bindClosure(
            \Horde\Util\Variables::class,
            function () {
                return \Horde\Util\Variables::getDefaultVariables();
            }
        );
    }
}
