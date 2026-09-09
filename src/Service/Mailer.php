<?php

namespace JUser\Service;

use Laminas\Translator\TranslatorInterface;
use JUser\Model\UserTable;
use Psr\Log\LoggerInterface;
use JUser\Model\User;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer
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
     * @var LoggerInterface|null $logger
     */
    protected $logger;

    /**
     * Email a sign-in link that the **caller has already assembled**.
     *
     * This class does no routing, which in 3.0.0 is the whole of what it has to say
     * about laminas: assembling the link itself is how it came to hold a
     * `Laminas\Router\RouteStackInterface`, and holding one is what made the emailed
     * link go out with no locale prefix — the unprefixed twin of the real route, one
     * 302 away from the page that redeems a single-use token. `sendLoginLinkEmail()`
     * and the router went together in 3.0.0; `JUser\Page\SignIn::sendLoginLink()` is
     * the caller now, and it builds the URL through `JUser\Host\UrlBuilderInterface`,
     * which is locale-aware because everything else on the site's pages is.
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
     * @return self
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
     * @return self
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
