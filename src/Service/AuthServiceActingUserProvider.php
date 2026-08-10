<?php

namespace JUser\Service;

use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationServiceInterface;
use SionModel\Service\ActingUserProviderInterface;
use Throwable;

/**
 * Resolves the acting user id from 'JUser\AuthService' at call time.
 *
 * The AuthenticationService is looked up lazily on first call — resolving it
 * during construction would re-form the UserTable/ProblemService/ProblemTable/
 * AuthService dependency cycle. The id itself is never cached: a user can log
 * in mid-request (magic-link redemption), and every call must observe the
 * current identity.
 *
 * ## "No identity" includes "no session to have an identity in"
 *
 * `JUser\AuthService` is session-backed, and building a session outside a web request
 * is not merely unnecessary — it raises. In a console process
 * `Laminas\Session\Config\SessionConfig` rejects `session.cache_expire` with
 * "is not a valid sessions-related ini setting", because the ini settings it wants
 * cannot be changed once output has started.
 *
 * That used to escape from here, and the consequence was disproportionate. This
 * provider is consulted from write paths that are otherwise perfectly happy without an
 * identity — `JTranslate\Model\TranslationsTable::writeMissingPhrasesToDb()` only wants
 * a value for `modified_by`, a nullable column. Observed 2026-08-10: a console process
 * that discovered a phrase inserted the phrase row, asked for the acting user, and died
 * — leaving a row whose key-locale translation was never written and which, because the
 * phrase index then reported the phrase present, no later render would ever complete.
 * A permanently untranslatable row, produced by a failure to answer an optional question.
 *
 * So an unavailable authentication service is now reported the same way an
 * unauthenticated visitor is: **there is no acting user, and the answer is null.** That
 * is true rather than merely convenient — a console process genuinely has no acting user,
 * and `modified_by` being NULL is exactly how the schema says so.
 *
 * ## What this does not do
 *
 * It does not swallow failures of an authentication service that *is* available.
 * `getIdentity()` is called outside the guard, so a broken session store or a corrupt
 * identity still raises. Only the construction of the service is treated as "absent",
 * and only once — the failure is remembered so a console command that writes a thousand
 * phrases does not attempt a thousand doomed container lookups.
 *
 * A caller that needs attribution and knows its own identity should not rely on this at
 * all: `TranslationsTable::setActingUserId()` replaces the provider outright, which is
 * how the v3 API attributes an agent's writes to the agent.
 */
class AuthServiceActingUserProvider implements ActingUserProviderInterface
{
    /** @var ContainerInterface $container */
    private $container;

    /** @var AuthenticationServiceInterface|null $authService */
    private $authService;

    /**
     * Whether the service turned out to be unavailable, so we stop asking.
     *
     * A separate flag rather than a null check on $authService, because null is also
     * the "not looked up yet" state and the two must not be confused: conflating them
     * would retry the lookup on every call.
     *
     * @var bool $unavailable
     */
    private $unavailable = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getActingUserId(): ?int
    {
        if ($this->unavailable) {
            return null;
        }
        if (null === $this->authService) {
            try {
                $this->authService = $this->container->get('JUser\AuthService');
            } catch (Throwable $e) {
                //Catching Throwable rather than a service-manager exception on purpose:
                //the failure surfaces from deep inside session configuration and arrives
                //wrapped differently depending on which layer raised first. Narrowing it
                //would mean guessing, and guessing wrong reintroduces the partial write
                //this exists to prevent.
                $this->unavailable = true;

                return null;
            }
        }
        $identity = $this->authService->getIdentity();
        if (! $identity) {
            return null;
        }
        return (int) $identity->id;
    }
}
