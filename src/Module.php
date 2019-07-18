<?php
namespace JUser;

use Zend\Mvc\MvcEvent;
use Zend\Db\TableGateway\Feature\GlobalAdapterFeature;
use Zend\Session\ManagerInterface;
use JUser\Service\Mailer;
use ZfcUser\Controller\UserController;

class Module
{
    protected $isMailerWired = false;
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();

        //enable session manager
        $sm->get(ManagerInterface::class);

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
        $app->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], -100);
    }
    
    public function onDispatch(MvcEvent $e)
    {
        //theoretically I believe there could be more than 1 dispatch in a call
        if ($this->isMailerWired) {
            return;
        }
        
        $matches = $e->getRouteMatch();
        $controller = $matches->getParam('controller');
        if (UserController::class !== $controller && 'zfcuser' !== $controller) {
            // not ZfcUser's controller, we're not interested
            return;
        }
        
        $sm = $e->getApplication()->getServiceManager();
        
        /** @var User $userService */
        $userService = $sm->get('zfcuser_user_service');
        $events = $userService->getEventManager();
        
        //the mailer will listen on zfcUser events to dispatch relevant emails
        /** @var Mailer $mailer */
        $mailer = $sm->get(Mailer::class);
        $mailer->attach($events);
        $this->isMailerWired = true;
        
//         $user = [
//             'email' => 'hallo@schoenstatt-fathers.link',
//             'displayName' => 'Hallo',
//             'verificationToken' => User::generateVerificationToken(),
//         ];
//         $result = $mailer->sendVerificationEmail($user);
//         var_dump($result);
    }
}
