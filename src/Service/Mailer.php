<?php

namespace JUser\Service;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use JUser\Model\UserTable;
use Laminas\Router\RouteStackInterface;
use Laminas\Log\LoggerInterface;
use JUser\Model\User;

class Mailer implements TranslatorAwareInterface
{
    /** @var \Swift_Mailer $mailer */
    protected $mailer;

    /** @var TranslatorInterface $translator */
    protected $translator;

    /**
     * @var string $translatorEnabled
     */
    protected $translatorEnabled = true;

    protected $textDomain = 'JUser';

    /**
     * @var UserTable $userTable
     */
    protected $userTable;

    /**
     * @var RouteStackInterface $router
     */
    protected $router;

    /**
     * @var \Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger
     */
    protected $flashMessenger;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    public function onRegister(User $user): void
    {
        if ($this->logger) {
            $this->logger->debug("JUser: Recieved a trigger for register.post");
        }
        $userArray = [];
        $userArray['verificationToken'] = $user->getVerificationToken();
        $userArray['displayName'] = $user->getDisplayName();
        $userArray['email'] = $user->getEmail();
        $this->sendVerificationEmail($userArray);

        //Let the user know that they should look for an email
        $flashMessenger = $this->getFlashMessenger();
        if (isset($flashMessenger)) {
            $flashMessenger->addInfoMessage('Thanks so much for registering! '
                . 'Please check your email for a verification link. '
                . 'Make sure to check the spam folder if you don\'t see it.');
        }
    }

    /**
     * Send a flash message to the user to look for a verification email
     *
     * @param User $user
     * @param UserTable $callback A reference to the calling UserTable to be able to update User
     *
     * @throws \Exception
     */
    public function onInactiveUser(User $user, UserTable $callback): void
    {
        if (isset($this->logger)) {
            $this->logger->notice(
                "JUser: An inactive user is trying to logon, we'll ask them to check their email.",
                ['email' => $user->getEmail()]
            );
        }
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
        $flashMessenger = $this->getFlashMessenger();
        if (isset($flashMessenger)) {
            $flashMessenger->addInfoMessage(
                'Please check your email for a verification link. '
                . 'Make sure to check the spam folder if you don\'t see it.'
            );
        }
    }

    /**
     * Send an email to the user to verify their account
     *
     * @todo add a beautified HTML version of the email. Add mailing address as required
     *
     * @param mixed $user
     */
    public function sendVerificationEmail($user): int
    {
        if (isset($this->logger)) {
            $this->logger->info("JUser: Sending a verification email.", ['email' => $user['email']]);
        }
        $start = microtime(true);

        $link = $this->router->assemble([], [
            'name' => 'juser/verify-email',
            'force_canonical' => true,
            'query' => ['token' => $user['verificationToken']]
        ]);
        $body = <<<EOT
Dear %s,

Welcome to Schoenstatt Link! Before we get started, please confirm
your e-mail address by clicking on this link:

%s

If you haven't registered with Schoenstatt Link, please ignore this message.
If you have any questions or comments, please contact support at support@schoenstatt.link.
EOT;
        $subject = 'Please confirm your email address';
        if ($this->isTranslatorEnabled()) {
            $translator = $this->getTranslator();
            $body = $translator->translate($body);
            $subject = $translator->translate($subject);
        }
        $body = sprintf($body, $user['displayName'], $link);

        // Create the Transport
        /** @var \Swift_Mailer $mailer */
        $mailer = $this->getMailer();

        // Create the message
        $message = (new \Swift_Message())

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

        $result = $mailer->send($message);
        $timeElapsedSecs = microtime(true) - $start;
        if (isset($this->logger)) {
            $this->logger->debug("JUser: Finished sending verification email.", [
                'email' => $user['email'],
                'verificationToken' => substr($user['verificationToken'], 0, 4) . '...',
                'result' => $result,
                'elapsedSeconds' => $timeElapsedSecs,
            ]);
        }
        return $result;
    }

    /**
     * Get the translator value
     * @return TranslatorInterface
     */
    public function getTranslator()
    {
        if (! isset($this->translator)) {
            throw new \Exception('Something went wrong, no translator available');
        }
        return $this->translator;
    }

    /**
     * Set the translator value
     * @param TranslatorInterface $translator
     * @return self
     */
    public function setTranslator(TranslatorInterface $translator = null, $textDomain = null)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Checks if the object has a translator
     *
     * @return bool
     */
    public function hasTranslator()
    {
        return is_object($this->translator);
    }

    /**
     * Sets whether translator is enabled and should be used
     *
     * @param  bool $enabled [optional] whether translator should be used.
     *                       Default is true.
     * @return TranslatorAwareInterface
     */
    public function setTranslatorEnabled($enabled = true)
    {
        $this->translatorEnabled = $enabled;
        return $this;
    }

    /**
     * Returns whether translator is enabled and should be used
     *
     * @return bool
     */
    public function isTranslatorEnabled()
    {
        return (bool) $this->translatorEnabled;
    }

    /**
     * Set translation text domain
     *
     * @param  string $textDomain
     * @return TranslatorAwareInterface
     */
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        $this->textDomain = $textDomain;
        return $this;
    }

    /**
     * Return the translation text domain
     *
     * @return string
     */
    public function getTranslatorTextDomain()
    {
        return $this->textDomain;
    }

    /**
     * Get the mailer value
     * @return \Swift_Mailer
     */
    public function getMailer()
    {
        if (! isset($this->mailer)) {
            throw new \Exception('Something went wrong, no mailer available');
        }
        return $this->mailer;
    }

    /**
     * Set the mailer value
     * @param \Swift_Mailer $mailer
     * @return self
     */
    public function setMailer(?\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
        return $this;
    }

    /**
     * Get the userTable value
     * @return UserTable
     */
    public function getUserTable()
    {
        if (! isset($this->userTable)) {
            throw new \Exception('Something went wrong, no userTable available');
        }
        return $this->userTable;
    }

    /**
     * Set the userTable value
     * @param UserTable $userTable
     * @return self
     */
    public function setUserTable(?UserTable $userTable)
    {
        $this->userTable = $userTable;
        return $this;
    }

    /**
     * Get the router
     * @return RouteStackInterface
     */
    public function getRouter()
    {
        if (! isset($this->router)) {
            throw new \Exception('Something went wrong, no router available');
        }
        return $this->router;
    }

    /**
     * Set the router
     * @param RouteStackInterface $router
     * @return self
     */
    public function setRouter(?RouteStackInterface $router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Get the flashMessenger object
     * @return \Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger
     */
    public function getFlashMessenger()
    {
        return $this->flashMessenger;
    }

    /**
     * Set the flashMessenger object
     * @param \Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger
     * @return self
     */
    public function setFlashMessenger(\Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger $flashMessenger)
    {
        $this->flashMessenger = $flashMessenger;
        return $this;
    }

    /**
     * Get the logger object
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the logger object
     * @param LoggerInterface $flashMessenger
     * @return self
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
