<?php

namespace JUser\Service;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use JUser\Model\UserTable;
use Laminas\Router\RouteStackInterface;
use Psr\Log\LoggerInterface;
use JUser\Model\User;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer implements TranslatorAwareInterface
{
    /** @var TransportInterface $transport */
    protected $transport;

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
     * @var LoggerInterface|null $logger
     */
    protected $logger;

    /**
     * Email the user a magic link that signs them in.
     *
     * @param User $user
     * @param string $plaintextToken the token as it must appear in the link; only its hash is stored
     * @param int $expirationMinutes how long the link stays valid, for the copy
     * @param string|null $redirect a path on this site to land on after redeeming, carried
     *        in the link rather than only in the session because the link is very often
     *        opened on a different device than the one that asked for it. It is
     *        re-validated on arrival (JUser\Controller\LoginController::validRedirect),
     *        so what travels here is a hint, not a grant: the worst a tampered value can
     *        do is send its own owner to another page of this site, which the ACL then
     *        checks anyway.
     * @return \Symfony\Component\Mailer\SentMessage|null
     */
    public function sendLoginLinkEmail(
        User $user,
        string $plaintextToken,
        int $expirationMinutes = 15,
        ?string $redirect = null
    ) {
        if (isset($this->logger)) {
            $this->logger->info("JUser: Sending a sign-in link.", ['email' => $user->getEmail()]);
        }
        $start = microtime(true);

        $query = ['token' => $plaintextToken];
        if (null !== $redirect && '' !== $redirect) {
            $query['redirect'] = $redirect;
        }
        $link = $this->router->assemble([], [
            'name' => 'zfcuser/verify',
            'force_canonical' => true,
            'query' => $query,
        ]);

        return $this->sendLoginLink($user, $link, $expirationMinutes, $start);
    }

    /**
     * Email a sign-in link that has **already been assembled**.
     *
     * The half of sendLoginLinkEmail() that is not routing, split out so that a caller
     * holding a URL builder can hand over a finished link instead of handing over a
     * router. JUser\Page\SignIn is that caller, and 3.0.0 makes it the only one:
     * assembling the link is how this class came to need laminas-router, and it is the
     * reason the emailed link once went out without a locale prefix, one 302 away from the
     * page that redeems it. See JUser\Page\SignIn::sendLoginLink().
     *
     * @param User $user
     * @param string $link the absolute URL to put in the email. Absolute, not relative:
     *        a relative path in an email is not a link at all.
     * @param int $expirationMinutes how long the link stays valid, for the copy
     * @param float|null $start when the caller began, for the elapsed-time log line; null
     *        means "now", i.e. measure only what happens here
     * @return \Symfony\Component\Mailer\SentMessage|null
     */
    public function sendLoginLink(
        User $user,
        string $link,
        int $expirationMinutes = 15,
        ?float $start = null
    ) {
        if (null === $start) {
            if (isset($this->logger)) {
                $this->logger->info("JUser: Sending a sign-in link.", ['email' => $user->getEmail()]);
            }
            $start = microtime(true);
        }

        $body = <<<EOT
Hello %s,

Click the link below to sign in to Schoenstatt Link:

%s

The link is good for %s minutes and can only be used once.
If you didn't ask to sign in, you can safely ignore this message.
EOT;
        $subject = 'Your sign-in link for schoenstatt.link';
        if ($this->isTranslatorEnabled() && $this->hasTranslator()) {
            $translator = $this->getTranslator();
            $body = $translator->translate($body);
            $subject = $translator->translate($subject);
        }
        $displayName = $user->getDisplayName();
        if (null === $displayName || '' === $displayName) {
            $displayName = $user->getUsername();
        }
        $body = sprintf($body, $displayName, $link, $expirationMinutes);

        $message = (new Email())
            ->subject($subject)
            ->from(new Address('webmaster@schoenstatt.link', 'Schoenstatt Link'))
            ->to(new Address($user->getEmail(), $displayName))
            ->text($body);

        $result = $this->getTransport()->send($message);
        if (isset($this->logger)) {
            $this->logger->debug("JUser: Finished sending sign-in link.", [
                'email' => $user->getEmail(),
                'messageId' => isset($result) ? $result->getMessageId() : null,
                'elapsedSeconds' => microtime(true) - $start,
            ]);
        }
        return $result;
    }

    /**
     * Email the user a short code to type into an app (API sign-in flow).
     *
     * @param User $user
     * @param string $code
     * @param int $expirationMinutes
     * @return \Symfony\Component\Mailer\SentMessage|null
     */
    public function sendLoginCodeEmail(User $user, string $code, int $expirationMinutes = 15)
    {
        if (isset($this->logger)) {
            $this->logger->info("JUser: Sending a sign-in code.", ['email' => $user->getEmail()]);
        }

        $body = <<<EOT
Your login code: %s

It is good for %s minutes and can only be used once.
If you didn't ask to sign in, you can safely ignore this message.
EOT;
        $subject = 'Your login code for schoenstatt.link';
        if ($this->isTranslatorEnabled() && $this->hasTranslator()) {
            $translator = $this->getTranslator();
            $body = $translator->translate($body);
            $subject = $translator->translate($subject);
        }
        $body = sprintf($body, $code, $expirationMinutes);

        $displayName = $user->getDisplayName();
        if (null === $displayName || '' === $displayName) {
            $displayName = $user->getUsername();
        }

        $message = (new Email())
            ->subject($subject)
            ->from(new Address('webmaster@schoenstatt.link', 'Schoenstatt Link'))
            ->to(new Address($user->getEmail(), $displayName))
            ->text($body);

        return $this->getTransport()->send($message);
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
    public function setTranslator(?TranslatorInterface $translator = null, $textDomain = null)
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
     * Get the mail transport
     * @return TransportInterface
     */
    public function getTransport()
    {
        if (! isset($this->transport)) {
            throw new \Exception('Something went wrong, no mail transport available');
        }
        return $this->transport;
    }

    /**
     * Set the mail transport
     * @param TransportInterface $transport
     * @return self
     */
    public function setTransport(?TransportInterface $transport)
    {
        $this->transport = $transport;
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
     * Get the logger object
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the logger object
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
