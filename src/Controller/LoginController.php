<?php

namespace JUser\Controller;

use JUser\Form\LoginForm;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Request as HttpRequest;
use Psr\Log\LoggerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Router\Exception\RuntimeException as RouterRuntimeException;
use Laminas\Router\RouteMatch;
use Laminas\Router\RouteStackInterface;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Laminas\View\Model\ViewModel;

/**
 * Passwordless sign-in.
 *
 * The user gives us an email address, we mail them a single-use link, clicking
 * the link is what authenticates them. There is no password anywhere.
 */
class LoginController extends AbstractActionController
{
    /** Session container namespace used to remember where to go after verifying */
    public const SESSION_NAMESPACE = 'JUser';

    /** @var AuthenticationService $authService */
    protected $authService;

    /** @var UserTable $userTable */
    protected $userTable;

    /** @var LoginTokenService $tokenService */
    protected $tokenService;

    /** @var Mailer $mailer */
    protected $mailer;

    /** @var RouteStackInterface $router */
    protected $router;

    /** @var SessionManagerInterface $sessionManager */
    protected $sessionManager;

    /** @var string $loginRedirectRoute */
    protected $loginRedirectRoute = 'welcome';

    /** @var string $logoutRedirectRoute */
    protected $logoutRedirectRoute = 'zfcuser/login';

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(
        AuthenticationService $authService,
        UserTable $userTable,
        LoginTokenService $tokenService,
        Mailer $mailer,
        RouteStackInterface $router,
        SessionManagerInterface $sessionManager,
        array $config = []
    ) {
        $this->authService = $authService;
        $this->userTable = $userTable;
        $this->tokenService = $tokenService;
        $this->mailer = $mailer;
        $this->router = $router;
        $this->sessionManager = $sessionManager;
        if (isset($config['login_redirect_route'])) {
            $this->loginRedirectRoute = (string) $config['login_redirect_route'];
        }
        if (isset($config['logout_redirect_route'])) {
            $this->logoutRedirectRoute = (string) $config['logout_redirect_route'];
        }
    }

    /**
     * Route 'zfcuser' (/user): nothing lives here, bounce the visitor somewhere useful.
     */
    public function indexAction()
    {
        if ($this->authService->hasIdentity()) {
            return $this->redirect()->toRoute($this->loginRedirectRoute);
        }
        return $this->redirect()->toRoute('zfcuser/login');
    }

    /**
     * Route 'zfcuser/login' (/user/login)
     */
    public function loginAction()
    {
        return $this->handleEmailRequest(false);
    }

    /**
     * Route 'zfcuser/register' (/user/register).
     * Same mechanics as login: registration happens implicitly on first sign-in.
     */
    public function registerAction()
    {
        return $this->handleEmailRequest(true);
    }

    /**
     * Route 'zfcuser/verify' (/user/verify?token=...)
     */
    public function verifyAction()
    {
        $token = $this->params()->fromQuery('token');
        if (! is_string($token) || '' === trim($token)) {
            return $this->failedVerification();
        }

        $user = $this->tokenService->redeemToken($token);
        if (! $user instanceof User) {
            return $this->failedVerification();
        }

        //redeeming the link proves the address works, so the account goes live
        if (1 != $user->getState()) {
            $this->userTable->activateUser($user->getId());
            $user->setState(1);
        }

        //new privilege level, new session id
        $this->sessionManager->regenerateId(true);
        $this->authService->getStorage()->write((int) $user->getId());

        if (isset($this->logger)) {
            $this->logger->info("JUser: A user signed in with a login link.", ['userId' => $user->getId()]);
        }

        return $this->redirect()->toUrl($this->resolvePostLoginUrl());
    }

    /**
     * Route 'zfcuser/logout' (/user/logout)
     */
    public function logoutAction()
    {
        $this->authService->clearIdentity();
        $this->sessionManager->forgetMe();
        $this->sessionManager->regenerateId(true);

        return $this->redirect()->toRoute($this->logoutRedirectRoute);
    }

    /**
     * GET renders the email form, POST issues and mails a link.
     * The response is deliberately identical whether or not the address is known.
     *
     * @param bool $isRegistration only changes the wording
     * @return ViewModel|\Laminas\Http\Response
     */
    protected function handleEmailRequest($isRegistration)
    {
        if ($this->authService->hasIdentity()) {
            return $this->redirect()->toRoute($this->loginRedirectRoute);
        }

        $form = new LoginForm();
        $redirect = $this->validRedirect($this->params()->fromQuery('redirect'));
        if (null !== $redirect) {
            $form->get('redirect')->setValue($redirect);
        }

        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->loginViewModel($form, $isRegistration);
        }

        $form->setData($request->getPost());
        if (! $form->isValid()) {
            return $this->loginViewModel($form, $isRegistration);
        }

        $data = $form->getData();
        $email = $data['email'];
        $redirect = $this->validRedirect(isset($data['redirect']) ? $data['redirect'] : null);
        if (null !== $redirect) {
            //remember it: the click may come back on a request we can't tie to this form
            $container = new SessionContainer(self::SESSION_NAMESPACE, $this->sessionManager);
            $container->redirect = $redirect;
        }

        $this->issueAndSend($email);

        $view = new ViewModel([
            'email' => $email,
            'expirationMinutes' => $this->tokenService->getWebTokenExpirationMinutes(),
        ]);
        $view->setTemplate('juser/login/check-email');
        return $view;
    }

    /**
     * Look the address up (registering it if it's new) and mail a link.
     * Failures are swallowed on purpose: the caller must not learn anything.
     *
     * @param string $email
     * @return void
     */
    protected function issueAndSend($email)
    {
        try {
            $user = $this->userTable->findByEmail($email);
            if (! $user instanceof User) {
                //open registration: an unknown address simply becomes an account
                $userArray = $this->userTable->createUserFromEmail($email);
                if (! is_array($userArray)) {
                    return;
                }
                $user = new User($userArray);
            }

            if (! $this->tokenService->mayIssueToken($user)) {
                if (isset($this->logger)) {
                    $this->logger->info(
                        "JUser: Throttled a sign-in link request.",
                        ['userId' => $user->getId()]
                    );
                }
                return;
            }

            $token = $this->tokenService->issueWebToken($user);
            $this->mailer->sendLoginLinkEmail(
                $user,
                $token,
                $this->tokenService->getWebTokenExpirationMinutes()
            );
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error("JUser: Failed to issue a sign-in link.", ['exception' => $e]);
            }
        }
    }

    /**
     * @param LoginForm $form
     * @param bool $isRegistration
     * @return ViewModel
     */
    protected function loginViewModel(LoginForm $form, $isRegistration)
    {
        $view = new ViewModel([
            'form' => $form,
            'isRegistration' => (bool) $isRegistration,
        ]);
        $view->setTemplate('juser/login/login');
        return $view;
    }

    /**
     * @return ViewModel
     */
    protected function failedVerification()
    {
        $view = new ViewModel();
        $view->setTemplate('juser/login/verify-failed');
        $this->getResponse()->setStatusCode(400);
        return $view;
    }

    /**
     * Where to send a freshly signed-in user.
     * @return string
     */
    protected function resolvePostLoginUrl()
    {
        $redirect = $this->validRedirect($this->params()->fromQuery('redirect'));
        if (null === $redirect) {
            $container = new SessionContainer(self::SESSION_NAMESPACE, $this->sessionManager);
            if (isset($container->redirect)) {
                $redirect = $this->validRedirect($container->redirect);
                unset($container->redirect);
            }
        }
        if (null !== $redirect) {
            return $redirect;
        }
        return $this->router->assemble([], ['name' => $this->loginRedirectRoute]);
    }

    /**
     * Only accept a redirect target that the router actually recognises,
     * so it can never point off-site. Mirrors the old ZfcUser RedirectCallback.
     *
     * @param mixed $redirect
     * @return string|null
     */
    protected function validRedirect($redirect)
    {
        if (! is_string($redirect) || '' === $redirect) {
            return null;
        }
        //refuse anything that could leave the site
        if (0 !== strpos($redirect, '/') || 0 === strpos($redirect, '//')) {
            return null;
        }
        try {
            $testRequest = new HttpRequest();
            $testRequest->setUri($redirect);
            $match = $this->router->match($testRequest);
            if (! $match instanceof RouteMatch) {
                return null;
            }
        } catch (RouterRuntimeException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
        return $redirect;
    }

    /**
     * @param LoggerInterface $logger
     * @return self
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
