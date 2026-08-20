<?php

namespace JUser\Controller;

use BjyAuthorize\Service\Authorize;
use JUser\Form\LoginForm;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Request as HttpRequest;
use Psr\Log\LoggerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
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

    /**
     * @var Authorize $authorize the same ACL BjyAuthorize\Guard\Route consults, used
     *      after a link is redeemed to find out whether the place the visitor was
     *      heading is somewhere they may actually go. See verifyAction().
     */
    protected $authorize;

    public function __construct(
        AuthenticationService $authService,
        UserTable $userTable,
        LoginTokenService $tokenService,
        Mailer $mailer,
        RouteStackInterface $router,
        SessionManagerInterface $sessionManager,
        Authorize $authorize,
        array $config = []
    ) {
        $this->authService = $authService;
        $this->userTable = $userTable;
        $this->tokenService = $tokenService;
        $this->mailer = $mailer;
        $this->router = $router;
        $this->sessionManager = $sessionManager;
        $this->authorize = $authorize;
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
        return $this->handleEmailRequest();
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

        $destination = $this->resolvePostLoginUrl();

        //Signing in and being *allowed where you were going* are two different things,
        //and before this the difference was invisible. A visitor who asked for /en/admin
        //without an account got a link, clicked it, was signed in, was bounced by the
        //route guard back to the sign-in page — which, now that they had an identity,
        //redirected them to the welcome page. Three redirects, no explanation, and the
        //most natural reading of it is that the link did not work.
        //
        //So the destination is checked here, while there is still a page to say it on.
        //The resource is the same `route/<name>` key BjyAuthorize\Guard\Route uses, so
        //this cannot disagree with the guard that is about to run.
        $refusedRoute = $this->refusedRouteFor($destination, $user);
        if (null !== $refusedRoute) {
            if (isset($this->logger)) {
                $this->logger->info("JUser: Signed a user in, but their destination was not permitted.", [
                    'userId' => $user->getId(),
                    'route'  => $refusedRoute,
                ]);
            }
            //Two messages, deliberately. One of them is good news and the other is not,
            //and collapsing them into a single sentence loses the half the visitor needs
            //most: that they *are* signed in and need not try the link again (they could
            //not — it is single-use).
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                ->addMessage('You are signed in.');
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(sprintf(
                    'Your account does not have access to %s, so we have brought you here instead.',
                    $this->pathOf($destination)
                ));

            return $this->redirect()->toRoute($this->loginRedirectRoute);
        }

        return $this->redirect()->toUrl($destination);
    }

    /**
     * The route name behind $url when $user may **not** reach it, or null when they may —
     * or when the question does not arise.
     *
     * Null is the answer for anything that does not resolve to a route, and that is not
     * a loophole: resolvePostLoginUrl() only ever returns a URL that validRedirect()
     * already matched, or the configured post-login route, which has to be reachable or
     * the site has no working sign-in at all.
     *
     * ## Why this asks about the user's roles and not about Authorize::isAllowed()
     *
     * Because the obvious call gives the wrong answer here, and it gives it in the
     * dangerous direction: it refuses a destination the visitor may actually reach.
     *
     * Authorize::load() runs once per request and bakes the identity's roles into the ACL
     * as it goes - `$this->acl->addRole($this->getIdentity(), $parentRoles)`. On this
     * request BjyAuthorize\Guard\Route has already triggered that load, while the
     * visitor was still anonymous, so the identity role in the ACL is `guest` and stays
     * `guest` no matter what is written to the auth storage afterwards. Measured: it sent
     * a member who signed in from a saved search to the welcome page and told them they
     * had no access to it.
     *
     * So the question is asked the way the *next* request will answer it - against the
     * roles the account actually holds, which is what BjyAuthorize's identity meta-role
     * inherits a moment from now. Role inheritance lives in the ACL, so naming a child
     * role covers its ancestors, and `user_<id>` is included because
     * JUser\Provider\Role\UserIdRoles makes per-user rules possible.
     *
     * @param string $url
     * @param User $user
     * @return string|null
     */
    protected function refusedRouteFor($url, User $user)
    {
        $match = $this->matchUrl($url);
        if (! $match instanceof RouteMatch) {
            return null;
        }

        $route = $match->getMatchedRouteName();
        if (! is_string($route) || '' === $route) {
            return null;
        }

        $acl      = $this->authorize->getAcl();
        $resource = 'route/' . $route;

        //An unguarded route is reachable by nobody: default deny is BjyAuthorize's whole
        //posture, and its Route guard treats a missing entry exactly this way.
        if (! $acl->hasResource($resource)) {
            return $route;
        }

        //A rule naming the null role became the allRoles pseudo-parent, i.e. genuinely
        //public. Asked first and asked directly, because a public route must not depend
        //on the account happening to hold a role the ACL knows about.
        if ($this->aclAllows($acl, null, $resource)) {
            return null;
        }

        foreach ($this->aclRolesOf($user) as $role) {
            if ($acl->hasRole($role) && $this->aclAllows($acl, $role, $resource)) {
                return null;
            }
        }

        return $route;
    }

    /**
     * isAllowed() without the exceptions. Laminas throws for an unknown role or
     * resource, and here either one means "no rule says yes", which is the same answer
     * as false.
     *
     * @param \Laminas\Permissions\Acl\Acl $acl
     * @param string|null $role
     * @param string $resource
     * @return bool
     */
    protected function aclAllows($acl, $role, $resource)
    {
        try {
            return (bool) $acl->isAllowed($role, $resource);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Every role name the ACL might hold a rule for, for this account.
     *
     * Two traps here, both found by measurement rather than by reading, and both of
     * which silently produce *no roles* — which this method's caller reads as "refuse
     * the destination".
     *
     * **Not getUser().** It serves rows out of the cached `all-linked-users` map and, on
     * a miss, falls through to queryObjects('user', …), which does not link roles at all.
     * A *freshly registered* account is exactly what a first sign-in produces and exactly
     * what is not in that cache, so the roles came back empty. linkUser() is the method
     * that actually joins them.
     *
     * **Not `rolesList`.** Despite the name it is `array_keys($user['roles'])`, i.e. a
     * list of numeric `user_role.id` values — measured as `[7, 16, 23, 40]` for an account
     * holding lib_user, pub_user, sch_user and bib_user. The ACL is keyed on role *names*,
     * so every one of those misses. The name lives on the link row itself, as `name`,
     * populated from `role_name` by UserTable::processUserRoleLinkerRow().
     *
     * @param User $user
     * @return array<int, string>
     */
    protected function aclRolesOf(User $user)
    {
        $roles = [];
        $row = ['userId' => (int) $user->getId(), 'roles' => []];
        $this->userTable->linkUser($row);
        if (isset($row['roles']) && is_array($row['roles'])) {
            foreach ($row['roles'] as $link) {
                if (is_array($link) && isset($link['name']) && is_string($link['name']) && '' !== $link['name']) {
                    $roles[] = $link['name'];
                }
            }
        }
        //the per-user role JUser\Provider\Role\UserIdRoles contributes
        $roles[] = 'user_' . (int) $user->getId();

        return $roles;
    }

    /**
     * The path-and-query of a URL, for showing a visitor where they were refused.
     *
     * Never the whole URL: force_canonical is on for the emailed link, so $url may carry
     * a scheme and host, and a message that quotes those reads like a phishing warning
     * rather than a place on this site.
     *
     * @param string $url
     * @return string
     */
    protected function pathOf($url)
    {
        $path = parse_url((string) $url, PHP_URL_PATH);
        if (! is_string($path) || '' === $path) {
            return (string) $url;
        }
        $query = parse_url((string) $url, PHP_URL_QUERY);

        return is_string($query) && '' !== $query ? $path . '?' . $query : $path;
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
     * Took an $isRegistration flag until 2026-08-20, when /user/register was retired:
     * registering and signing in are one request under magic links, and the flag only
     * ever chose between two wordings of the same page.
     *
     * @return ViewModel|\Laminas\Http\Response
     */
    protected function handleEmailRequest()
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
            return $this->loginViewModel($form);
        }

        $form->setData($request->getPost());
        if (! $form->isValid()) {
            return $this->loginViewModel($form);
        }

        $data = $form->getData();
        $email = $data['email'];
        $redirect = $this->validRedirect(isset($data['redirect']) ? $data['redirect'] : null);
        if (null !== $redirect) {
            //Remembered in the session *as well as* put in the link. The session is the
            //better channel when it survives — nothing about the destination is then
            //visible or editable — but it only survives when the link is opened in the
            //browser that asked for it, and the ordinary case is asking on a desktop and
            //clicking on a phone. So the session is the optimisation and the link is the
            //guarantee; resolvePostLoginUrl() prefers whichever arrived.
            $container = new SessionContainer(self::SESSION_NAMESPACE, $this->sessionManager);
            $container->redirect = $redirect;
        }

        $this->issueAndSend($email, $redirect);

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
     * @param string|null $redirect where to send them after the link is redeemed. Already
     *        through validRedirect(), and validated again on the way back in, because
     *        between here and there it is a query parameter in an email.
     * @return void
     */
    protected function issueAndSend($email, $redirect = null)
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
                $this->tokenService->getWebTokenExpirationMinutes(),
                $redirect
            );
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error("JUser: Failed to issue a sign-in link.", ['exception' => $e]);
            }
        }
    }

    /**
     * @param LoginForm $form
     * @return ViewModel
     */
    protected function loginViewModel(LoginForm $form)
    {
        $view = new ViewModel([
            'form' => $form,
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
        return $this->matchUrl($redirect) instanceof RouteMatch ? $redirect : null;
    }

    /**
     * Ask the laminas router what route a URL resolves to, or null if none does.
     *
     * Factored out of validRedirect() so that the ACL check in verifyAction() asks the
     * *same* router the same question. Two implementations of "which route is this" is
     * how a redirect gets validated against one answer and authorized against another.
     *
     * @param string $url
     * @return RouteMatch|null
     */
    protected function matchUrl($url)
    {
        try {
            $testRequest = new HttpRequest();
            $testRequest->setUri($url);
            $match = $this->router->match($testRequest);

            return $match instanceof RouteMatch ? $match : null;
        } catch (RouterRuntimeException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
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
