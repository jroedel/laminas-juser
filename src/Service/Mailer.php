<?php

declare(strict_types=1);

namespace JUser\Service;

use Exception;
use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Log\LoggerInterface;
use Laminas\Mail\Message;
use Laminas\Mvc\I18n\Translator;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Router\RouteStackInterface;
use SionModel\Mailing\MailingMessage;

use function microtime;
use function sprintf;
use function substr;

class Mailer
{
    protected string $textDomain = 'JUser';

    public function __construct(
        private \SionModel\Mailing\Mailer $mailer,
        private Translator $translator,
        private LoggerInterface $logger,
        private FlashMessenger $flashMessenger,
        private UserTable $userTable, //@todo do we need this?
        private RouteStackInterface $router
    ) {
    }

    public function onRegister(User $user): void
    {
        $this->logger->info("JUser: Received a trigger for register.post");
        $userArray                      = [];
        $userArray['verificationToken'] = $user->getVerificationToken();
        $userArray['displayName']       = $user->getDisplayName();
        $userArray['email']             = $user->getEmail();
        $this->sendVerificationEmail($userArray);

        //Let the user know that they should look for an email
        $this->flashMessenger->addInfoMessage('Thanks so much for registering! '
            . 'Please check your email for a verification link. '
            . 'Make sure to check the spam folder if you don\'t see it.');
    }

    /**
     * Send a flash message to the user to look for a verification email
     *
     * @param UserTable $callback A reference to the calling UserTable to be able to update User
     * @throws Exception
     */
    public function onInactiveUser(User $user, UserTable $callback): void
    {
        $this->logger->notice(
            "JUser: An inactive user is trying to logon, we'll ask them to check their email.",
            ['email' => $user->getEmail()]
        );
        //if someone's trying to login to an account with expired token, give them a new one
        if (! $user->isVerificationTokenValid()) {
            $this->logger->info(
                "JUser: An inactive user's token is expired, giving them a new one.",
                ['email' => $user->getEmail()]
            );
            $user->setNewVerificationToken();
            $callback->updateUser($user);
            $this->sendVerificationEmail($user->getArrayCopy());
        }
        //Let the user know that they should look for an email
        $this->flashMessenger->addInfoMessage(
            'Please check your email for a verification link. '
            . 'Make sure to check the spam folder if you don\'t see it.'
        );
    }

    /**
     * Send an email to the user to verify their account
     *
     * @todo add a beautified HTML version of the email. Add mailing address as required
     */
    public function sendVerificationEmail(array $user): void
    {
        $this->logger->info("JUser: Sending a verification email.", ['email' => $user['email']]);
        $start = microtime(true);

        $link    = $this->router->assemble([], [
            'name'            => 'juser/verify-email',
            'force_canonical' => true,
            'query'           => ['token' => $user['verificationToken']],
        ]);
        //@todo extract this into config
        $body    = <<<EOT
Dear %s,

Welcome to Schoenstatt Link! Before we get started, please confirm
your e-mail address by clicking on this link:

%s

If you haven't registered with Schoenstatt Link, please ignore this message.
If you have any questions or comments, please contact support at support@schoenstatt.link.
EOT;
        $subject = 'Please confirm your email address';
        $body    = $this->translator->translate($body);
        $subject = $this->translator->translate($subject);
        $body    = sprintf($body, $user['displayName'], $link);

        // Create the Transport
        $mailer = $this->mailer;

        // Create the message
        $message = (new Message())

        // Give the message a subject
        ->setSubject($subject)

        // Set the From address with an associative array
        ->setFrom(['webmaster@schoenstatt.link' => 'Schoenstatt Link'])

        // Set the To addresses with an associative array (setTo/setCc/setBcc)
        ->setTo([$user['email'] => $user['displayName']])
        ->setBcc('webmaster@schoenstatt.link')

        // Give it a body
        ->setBody($body);
        // And optionally an alternative body
        //->addPart('<q>Here is the message itself</q>', 'text/html')

        // Optionally add any attachments
        //->attach(Swift_Attachment::fromPath('my-document.pdf'))

        $success         = $mailer->sendMessageAndLog(new MailingMessage(
            message: $message,
            locale: null,
            template: null,
            trackingToken: null,
            tags: null
        ));
        $timeElapsedSecs = microtime(true) - $start;
        if (! $success) {
            $this->logger->err("JUser: Error while sending verification email.", [
                'email'             => $user['email'],
                'verificationToken' => substr($user['verificationToken'], 0, 4) . '...',
                'elapsedSeconds'    => $timeElapsedSecs,
            ]);
        } else {
            $this->logger->info("JUser: Finished sending verification email.", [
                'email'             => $user['email'],
                'verificationToken' => substr($user['verificationToken'], 0, 4) . '...',
                'elapsedSeconds'    => $timeElapsedSecs,
            ]);
        }
    }
}
