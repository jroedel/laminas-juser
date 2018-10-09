<?php
namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Patres\Form\EditCourseForm;
use JUser\Form\EditUserForm;
use JUser\Model\UserTable;
use JUser\Model\PersonValueOptionsProviderInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\View\Helper\Url;
use Zend\Router\RouteStackInterface;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class MailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $swift = $container->get(\Swift_Mailer::class);
        $translator = $container->get(TranslatorInterface::class);
        $router = $container->get(RouteStackInterface::class);
        /** @var \Zend\Mvc\Application $app */
        $app = $container->get('Application');
        $event = $app->getMvcEvent();
        $matches = $event->getRouteMatch();
        $urlHelper = new Url();
        $urlHelper->setRouter($router);
        if (isset($matches)) {
            $urlHelper->setRouteMatch($matches);
        }
        
        $mailer = new Mailer();
        $mailer->setTranslator($translator);
        $mailer->setMailer($swift);
        $mailer->setUrlHelper($urlHelper);
        
        return $mailer;
    }
}
