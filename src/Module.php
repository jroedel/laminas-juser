<?php
namespace JUser;

use Zend\Mvc\MvcEvent;
use Zend\Db\TableGateway\Feature\GlobalAdapterFeature;
use Zend\Session\ManagerInterface;
use JUser\Service\Mailer;
use Zend\Mvc\Controller\PluginManager;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $sm = $e->getApplication()->getServiceManager();

        //enable session manager
        $manager = $sm->get(ManagerInterface::class);

        //The static adapter is needed for the EditUserForm
        $config = $sm->get('Config');
        if (isset($config['zfcuser']['zend_db_adapter']) &&
            $sm->has($config['zfcuser']['zend_db_adapter'])
        ) {
            $adapter = $sm->get($config['zfcuser']['zend_db_adapter']);
            GlobalAdapterFeature::setStaticAdapter($adapter);
        } else {
            throw new \Exception(
                'Please set the [\'zfcuser\'][\'zend_db_adapter\'] config key for use with the JUser module.'
            );
        }

        /** @var User $userService */
        $userService = $sm->get('zfcuser_user_service');
        $events = $userService->getEventManager();
        $plugins = $sm->get(PluginManager::class);
        /** @var \Zend\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger */
        $flashMessenger = $plugins->get('flashmessenger');
        //the mailer will listen on zfcUser events to dispatch relevant emails
        $listener = $sm->get(Mailer::class);
        $listener->attach($events);
        //Let the user know that they should look for an email
        $events->attach('register.post', function($e) use ($flashMessenger) {
            $flashMessenger->addInfoMessage('Thanks so much for registering! '
                .'Please check your email for a verification link. '
                .'Make sure to check the spam folder if you don\'t see it.');
        }, 100);
    }
}
