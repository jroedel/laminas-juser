<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Log\LoggerInterface;
use Laminas\Mvc\I18n\Translator;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LmcUser\Service\User;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;
use SionModel\I18n\LanguageSupport;
use SionModel\Problem\EntityProblem;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionCacheService;

class UserTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var  User $userService **/
//        $userService  = $container->get('lmcuser_user_service');
//        $user         = $userService->getAuthService()->getIdentity();
//        $actingUserId = $user ? (int) $user->id : null;

        $adapter                = $container->get(Adapter::class);
        $entitiesService        = $container->get(EntitiesService::class);
        $sionCacheService       = $container->get(SionCacheService::class);
        $entityProblemPrototype = $container->get(EntityProblem::class);
        $languageSupport        = $container->get(LanguageSupport::class);
        $logger                 = $container->get(LoggerInterface::class);
        $config                 = $container->get('Config');
        $mailer                 = $container->get(Mailer::class);

        return new UserTable(
            adapter: $adapter,
            entitySpecifications: $entitiesService->getEntities(),
            sionCacheService: $sionCacheService,
            entityProblemPrototype: $entityProblemPrototype,
            userTable: null,
            languageSupport: $languageSupport,
            logger: $logger,
            actingUserId: null,
            config: $config['sion_model'],
            mailer: $mailer
        );
    }
}
