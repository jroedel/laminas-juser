<?php
namespace JUser\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of priming the Swift Mailer service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class SwiftMailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $smtpOptions = $config['smtp_options'];

        // Create the Transport
        $transport = (new \Swift_SmtpTransport($smtpOptions['server'], $smtpOptions['port'], $smtpOptions['ssl']))
            ->setUsername($smtpOptions['username'])
            ->setPassword($smtpOptions['password'])
        ;

        // Create the Mailer using your created Transport
        $mailer = new \Swift_Mailer($transport);

        return $mailer;
    }
}
