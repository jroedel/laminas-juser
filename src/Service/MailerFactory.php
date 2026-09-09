<?php

namespace JUser\Service;

use Psr\Container\ContainerInterface;
use Laminas\Translator\TranslatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory responsible of priming the Mailer service.
 *
 * It used to hand the Mailer a router as well, and prime that router's request URI
 * from the laminas `Request` service so that `force_canonical` had a host to work
 * with. Both went in 3.0.0 with `Mailer::sendLoginLinkEmail()`: the caller assembles
 * the link now. That is why nothing here reaches for `Request` any more — a service
 * that does not exist under a Symfony dispatch, which is what made the priming a
 * latent 500 rather than a detail.
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class MailerFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //the shared application transport, built from `smtp_options` by
        //SionModel\Service\MailTransportFactory
        $transport = $container->get('SionModel\MailTransport');
        $translator = $container->get(TranslatorInterface::class);

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
        ->setTransport($transport);
        if (isset($logger)) {
            $mailer->setLogger($logger);
        }

        return $mailer;
    }
}
