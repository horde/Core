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
use Horde\Core\Auth\AuthService;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Config\StateFactory;
use Horde\Core\Editor\TinymcePageBinder;
use Horde\Core\Factory\ApiRegistryFactory;
use Horde\Core\Factory\ApplicationServiceFactory;
use Horde\Core\Factory\AuthBaseFactory;
use Horde\Core\Factory\AuthIsGlobalAdminFactory;
use Horde\Core\Factory\AuthServiceFactory;
use Horde\Core\Factory\ConfigLoaderFactory;
use Horde\Core\Factory\ConfigMetadataProviderFactory;
use Horde\Core\Factory\DbAdapterFactory;
use Horde\Core\Factory\DbServiceFactory;
use Horde\Core\Factory\DriverRepositoryFactory;
use Horde\Core\Factory\ErrorFilterFactory;
use Horde\Core\Factory\EventDispatcherFactory;
use Horde\Core\Factory\GroupServiceFactory;
use Horde\Core\Factory\HashTableFactory;
use Horde\Core\Factory\HordeLdapServiceFactory;
use Horde\Core\Factory\HttpClientFactory;
use Horde\Core\Factory\IdentityServiceFactory;
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
use Horde\Core\Factory\PermissionServiceFactory;
use Horde\Core\Factory\PrefsServiceFactory;
use Horde\Core\Factory\RegistryConfigLoaderFactory;
use Horde\Core\Factory\RouteUrlWriterFactory;
use Horde\Core\Factory\RuntimeRoutesProviderFactory;
use Horde\Core\Factory\ServerRequestFactory;
use Horde\Core\RuntimeRoutesProvider;
use Horde\Core\Factory\SecretManagerFactory;
use Horde\Core\Factory\SessionHandlerFactory;
use Horde\Core\Factory\SimpleCacheFactory;
use Horde\Core\Factory\TinymceFactory;
use Horde\Core\Factory\TinymcePageBinderFactory;
use Horde\Core\Factory\TokenServiceFactory;
use Horde\Core\Factory\VersionServiceFactory;
use Horde\Core\Middleware\AuthIsGlobalAdmin;
use Horde\Core\Middleware\ErrorFilter;
use Horde\Core\Middleware\OAuthConsentMiddleware;
use Horde\Core\Service\ApplicationService;
use Horde\Core\Service\GroupService;
use Horde\Core\Service\HordeDbService;
use Horde\Core\Service\HordeLdapService;
use Horde\Core\Service\IdentityService;
use Horde\Core\Service\OAuthHttpClientService;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Service\VersionCheck\VersionService;
use Horde\Core\Uri\RegistryRouteMapperProvider;
use Horde\Core\Uri\RouteMapperProvider;
use Horde\Core\Uri\RoutesProvider;
use Horde\Core\Uri\RouteUrlWriter;
use Horde\Db\Adapter as DbAdapter;
use Horde\Editor\Tinymce;
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\HashTable\RedisHashTable;
use Horde\Horde\Factory\AuthenticationServiceFactory;
use Horde\Horde\Factory\OAuthHttpClientServiceFactory as BaseOAuthHttpClientServiceFactory;
use Horde\Horde\Factory\OAuthTokenServiceFactory as BaseOAuthTokenServiceFactory;
use Horde\Horde\Service\AuthenticationService;
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
use Horde\Token\Token;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Util\Variables;
use Horde\Injector\Injector;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

class DefaultInjectorBindings implements InjectorBindings
{
    public function register(Injector $injector): void
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
            HashTable::class => HashTableFactory::class,
            LockableHashTable::class => [HashTableFactory::class, 'createLockable'],
            RedisHashTable::class => [HashTableFactory::class, 'createRedis'],
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
            Secret\SessionSecret::class => Factory\SessionSecretFactory::class,
            Session\SessionEncryptionCoordinator::class => Factory\SessionEncryptionCoordinatorFactory::class,
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
            AuthIsGlobalAdmin::class => AuthIsGlobalAdminFactory::class,
            ErrorFilter::class => ErrorFilterFactory::class,
            'Horde_Service_Facebook' => 'Horde_Core_Factory_Facebook',
            'Horde_Service_Twitter' => 'Horde_Core_Factory_Twitter',
            'Horde_Service_UrlShortener' => 'Horde_Core_Factory_UrlShortener',
            'Horde_SessionHandler' => 'Horde_Core_Factory_SessionHandler',
            SessionHandler::class => SessionHandlerFactory::class,
            'Horde_Template' => 'Horde_Core_Factory_Template',
            'Horde_Timezone' => 'Horde_Core_Factory_Timezone',
            'Horde_Token' => 'Horde_Core_Factory_Token',
            Token::class => TokenServiceFactory::class,
            'Horde_Variables' => 'Horde_Core_Factory_Variables',
            'Horde_View' => 'Horde_Core_Factory_View',
            'Horde_View_Base' => 'Horde_Core_Factory_View',
            'Horde_Weather' => 'Horde_Core_Factory_Weather',
            'Net_DNS2_Resolver' => 'Horde_Core_Factory_Dns',
            'Text_LanguageDetect' => 'Horde_Core_Factory_LanguageDetect',
            Middleware\AuthHttpBasic::class => Factory\AuthHttpBasicFactory::class,
            HordeLogger::class => LoggerFactory::class,
            PsrLoggerInterface::class => LoggerFactory::class,
            AuthenticationService::class => AuthenticationServiceFactory::class,
            ConfigLoader::class => ConfigLoaderFactory::class,
            State::class => StateFactory::class,
            DriverRepository::class => DriverRepositoryFactory::class,
            ConfigMetadataProvider::class => ConfigMetadataProviderFactory::class,
            HordeDbService::class => DbServiceFactory::class,
            PrefsService::class => PrefsServiceFactory::class,
            IdentityService::class => IdentityServiceFactory::class,
            GroupService::class => GroupServiceFactory::class,
            RegistryConfigLoader::class => RegistryConfigLoaderFactory::class,
            ApplicationService::class => ApplicationServiceFactory::class,
            AuthService::class => AuthServiceFactory::class,
            HordeLdapService::class => HordeLdapServiceFactory::class,
            PermissionService::class => PermissionServiceFactory::class,
            VersionService::class => VersionServiceFactory::class,
            Tinymce::class => TinymceFactory::class,
            TinymcePageBinder::class => TinymcePageBinderFactory::class,
            EventDispatcherInterface::class => [EventDispatcherFactory::class, 'create'],
            ListenerProviderInterface::class => [EventDispatcherFactory::class, 'createListenerProvider'],
            SimpleCacheInterface::class => SimpleCacheFactory::class,
            PsrHttpClientInterface::class => HttpClientFactory::class,
            RouteUrlWriter::class => RouteUrlWriterFactory::class,
            RuntimeRoutesProvider::class => RuntimeRoutesProviderFactory::class,
            RoutesProvider::class => RuntimeRoutesProviderFactory::class,
            ServerRequestInterface::class => ServerRequestFactory::class,
        ];

        $implementations = [
            'Horde_Controller_ResponseWriter' => 'Horde_Controller_ResponseWriter_Web',
            RequestFactoryInterface::class => RequestFactory::class,
            ResponseFactoryInterface::class => ResponseFactory::class,
            StreamFactoryInterface::class => StreamFactory::class,
            RouteMapperProvider::class => RegistryRouteMapperProvider::class,
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
            Variables::class,
            function () {
                return Variables::getDefaultVariables();
            }
        );
    }
}
