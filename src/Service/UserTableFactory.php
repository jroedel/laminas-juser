<?php
namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use JUser\Model\UserTable;
use Zend\Db\Adapter\Adapter;
use Zend\Mvc\MvcEvent;

class UserTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $authService = $container->get('zfcuser_auth_service');
        $user = $authService->getIdentity();

        $dbAdapter = $container->get(Adapter::class);
        $table = new UserTable($dbAdapter, $user);

        $cache = $container->get('JUser\Cache');
        $table->setPersistentCache($cache);
        $em = $container->get('Application')->getEventManager();
        $em->attach(MvcEvent::EVENT_FINISH, [$table, 'onFinish'], -1);
        return $table;
    }
}
