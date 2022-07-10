<?php

declare(strict_types=1);

namespace JUser\Service;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Log\LoggerInterface;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Swift_Mailer;

class MailerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $swift      = $container->get(Swift_Mailer::class);
        $translator = $container->get(TranslatorInterface::class);
        /** @var TreeRouteStack $router */
        $router           = $container->get(RouteStackInterface::class);
        $routerRequestUri = $router->getRequestUri();
        if (! isset($routerRequestUri)) {
            /** @var Request $request */
            $request = $container->get('Request');
            $router->setRequestUri($request->getUri());
        }

        $plugins = $container->get(PluginManager::class);
        /** @var FlashMessenger $flashMessenger */
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
