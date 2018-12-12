<?php
namespace JUser\Service;

use Zend\I18n\Translator\TranslatorInterface;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use JUser\Model\UserTable;
use Zend\View\Helper\Url;

class Mailer extends AbstractListenerAggregate implements TranslatorAwareInterface
{
    /** @var \Swift_Mailer $mailer */
    protected $mailer;

    /** @var TranslatorInterface $translator */
    protected $translator;

    protected $translatorEnabled = true;

    protected $textDomain = 'JUser';

    protected $userTable;

    protected $urlHelper;

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach('register.post', array($this, 'listenOnRegisterPost'), $priority);
    }

    public function listenOnRegisterPost($event)
    {
        /** @var User $user */
        $user = $event->getParam('user');
        $userArray = [];
        $userArray['verificationToken'] = $user->getVerificationToken();
        $userArray['displayName'] = $user->getDisplayName();
        $userArray['email'] = $user->getEmail();
        $this->sendVerificationEmail($userArray);
    }

    public function sendVerificationEmail($user)
    {
        $link = $this->urlHelper->__invoke('juser/verify-email', [], [
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
        $translator = $this->getTranslator();
        $body = sprintf($translator->translate($body), $user['displayName'], $link);

        // Create the Transport
        /** @var \Swift_Mailer $mailer */
        $mailer = $this->getMailer();

        // Create the message
        $message = (new \Swift_Message())

        // Give the message a subject
        ->setSubject('Please confirm your email address')

        // Set the From address with an associative array
        ->setFrom(['webmaster@schoenstatt.link' => 'Schoenstatt Link'])

        // Set the To addresses with an associative array (setTo/setCc/setBcc)
        ->setTo([$user['email'] => $user['displayName']])
        ->setBcc('webmaster@schoenstatt.link')

        // Give it a body
        ->setBody($body)
        // And optionally an alternative body
        //->addPart('<q>Here is the message itself</q>', 'text/html')

        // Optionally add any attachments
        //->attach(Swift_Attachment::fromPath('my-document.pdf'))
        ;
        $result = $mailer->send($message);
        return $result;
    }

    /**
     * Get the translator value
     * @return TranslatorInterface
     */
    public function getTranslator()
    {
        if (!isset($this->translator)) {
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
        if (!isset($this->mailer)) {
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
        if (!isset($this->userTable)) {
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
     * Get the urlHelper value
     * @return Url
     */
    public function getUrlHelper()
    {
        if (!isset($this->urlHelper)) {
            throw new \Exception('Something went wrong, no urlHelper available');
        }
        return $this->urlHelper;
    }

    /**
     * Set the urlHelper value
     * @param Url $urlHelper
     * @return self
     */
    public function setUrlHelper(?Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
        return $this;
    }
}
