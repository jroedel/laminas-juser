<?php

namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use Zend\Db\Adapter\Adapter;

class UserTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        //@todo how can we get an identity if the process requires this very UserTable?
//         $authService = $container->get('zfcuser_auth_service');
//         if ($user = $authService->getIdentity()) {
//             $userId = $user->getId();
//         } else {
//             $userId = null;
//         }

        $dbAdapter = $container->get(Adapter::class);
        $table = new UserTable($dbAdapter, $container, null);

        $cache = $container->get('JUser\Cache');
        $em = $container->get('Application')->getEventManager();
        $table->setPersistentCache($cache);
        $table->wireOnFinishTrigger($em);

        $mailer = $container->get(Mailer::class);
        $table->setMailer($mailer);

        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $table->setLogger($logger);
        }

        return $table;
    }
}
