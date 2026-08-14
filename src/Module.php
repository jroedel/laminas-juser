<?php

namespace JUser;

use JUser\Session\SessionPruner;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\ManagerInterface;

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

        //enable session manager, and start it here so that a session which
        //fails validation (see the 'session_manager'.'validators' config) is
        //discarded rather than fataling the request. This was the whole job of
        //the BeaucalInvalidSession module, abandoned and never PHP 8 ready.
        $sessionManager = $sm->get(ManagerInterface::class);
        try {
            $sessionManager->start();
            //a validated session can still carry values whose class no longer
            //exists (written before a class-renaming migration); they fatal at
            //first container access, not here — prune them instead
            SessionPruner::pruneIncompleteClassValues($_SESSION);
        } catch (\Exception $e) {
            session_unset();
        }

        //This used to also publish the db adapter into
        //Laminas\Db\TableGateway\Feature\GlobalAdapterFeature's static registry,
        //"needed for the EditUserForm". Nothing else in the module ever read that
        //registry — no TableGateway here uses the feature — so its only effect was to
        //make three forms' getInputFilterSpecification() work inside a booted
        //laminas-mvc request and nowhere else. The forms take the adapter as a
        //constructor argument now (JUser\Service\DbAdapterResolver), so this listener
        //is back to being about the session and JUser has no process-global state.
    }
}
