<?php

namespace JUser\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Router\RouteStackInterface;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Log\LoggerInterface;

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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $swift = $container->get(\Swift_Mailer::class);
        $translator = $container->get(TranslatorInterface::class);
        /** @var \Laminas\Router\Http\TreeRouteStack $router */
        $router = $container->get(RouteStackInterface::class);
        $routerRequestUri = $router->getRequestUri();
        if (! isset($routerRequestUri)) {
            /** @var \Laminas\Http\PhpEnvironment\Request $request */
            $request = $container->get('Request');
            $router->setRequestUri($request->getUri());
        }

        $plugins = $container->get(PluginManager::class);
        /** @var \Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger */
        $flashMessenger = $plugins->get('flashmessenger');

        $config = $container->get('Config');
        if (
            isset($config['juser'])
            && isset($config['juser']['logger_service'])
            && $container->has($config['juser']['logger_service'])
        ) {
            $logger = $container->get($config['juser']['logger_service']);
        } elseif ($container->has(LoggerInterface::class)) {
            $logger = $container->get(LoggerInterface::class);
        }

        $mailer = (new Mailer())
        ->setTranslator($translator)
        ->setMailer($swift)
        ->setFlashMessenger($flashMessenger)
        ->setRouter($router);
        if (isset($logger)) {
            $mailer->setLogger($logger);
        }

        return $mailer;
    }
}
