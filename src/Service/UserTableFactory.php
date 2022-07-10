<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Model\UserTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class UserTableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //@todo how can we get an identity if the process requires this very UserTable?
//         $authService = $container->get('lmcuser_auth_service');
//         if ($user = $authService->getIdentity()) {
//             $userId = $user->getId();
//         } else {
//             $userId = null;
//         }

        $dbAdapter = $container->get(Adapter::class);
        $table     = new UserTable($dbAdapter, $container, null);

        $cache = $container->get('JUser\Cache');
        $em    = $container->get('Application')->getEventManager();
        $table->setPersistentCache($cache);
        $table->wireOnFinishTrigger($em);

        $mailer = $container->get(Mailer::class);
        $table->setMailer($mailer);

        $logger = $container->get('JUser\Logger');
        $table->setLogger($logger);

        return $table;
    }
}
