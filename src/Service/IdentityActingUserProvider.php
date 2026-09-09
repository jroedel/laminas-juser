<?php

declare(strict_types=1);

namespace JUser\Service;

use JUser\Host\IdentityInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use Throwable;

/**
 * Answers "who is making this change" from {@see IdentityInterface} at call time.
 *
 * The identity is looked up lazily on first call. Resolving it during construction would
 * re-form the UserTable/ProblemService/ProblemTable dependency cycle this module spent a
 * release breaking. The id itself is never cached: an account can sign in mid-request when
 * a magic link is redeemed, and every call must observe the identity as it is now.
 *
 * ## "No identity" includes "nowhere to have an identity"
 *
 * The identity is session-backed, and a console process has no session — building one is
 * not merely unnecessary, it raises, because the ini settings a session wants cannot be
 * changed once output has started.
 *
 * That used to escape from here, and the consequence was disproportionate. This provider
 * is consulted from write paths that are otherwise perfectly happy without an identity:
 * `JTranslate\Model\TranslationsTable::writeMissingPhrasesToDb()` only wants a value for
 * `modified_by`, a nullable column. Observed 2026-08-10: a console process that discovered
 * a phrase inserted the phrase row, asked for the acting user, and died — leaving a row
 * whose key-locale translation was never written and which, because the phrase index then
 * reported the phrase present, no later render would ever complete. A permanently
 * untranslatable row, produced by a failure to answer an optional question.
 *
 * So an unavailable identity service is reported the same way an anonymous visitor is:
 * **there is no acting user, and the answer is null.** That is true rather than merely
 * convenient — a console process genuinely has no acting user, and `modified_by` being
 * NULL is exactly how the schema says so.
 *
 * ## What this does not do
 *
 * It does not swallow failures of an identity that *is* available. {@see
 * IdentityInterface::current()} is called outside the guard, so a broken session store or
 * a corrupt row still raises. Only the construction of the service is treated as
 * "absent", and only once — the failure is remembered so a console command writing a
 * thousand phrases does not attempt a thousand doomed container lookups.
 *
 * A caller that needs attribution and knows its own identity should not rely on this at
 * all: `TranslationsTable::setActingUserId()` replaces the provider outright, which is how
 * the v3 API attributes an agent's writes to the agent.
 */
class IdentityActingUserProvider implements ActingUserProviderInterface
{
    private ?IdentityInterface $identity = null;

    /**
     * Whether the service turned out to be unavailable, so we stop asking.
     *
     * A separate flag rather than a null check on $identity, because null is also the
     * "not looked up yet" state and the two must not be confused: conflating them would
     * retry the lookup on every call.
     */
    private bool $unavailable = false;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function getActingUserId(): ?int
    {
        if ($this->unavailable) {
            return null;
        }
        if (null === $this->identity) {
            try {
                /** @var IdentityInterface $identity */
                $identity       = $this->container->get(IdentityInterface::class);
                $this->identity = $identity;
            } catch (Throwable) {
                //Catching Throwable rather than a service-manager exception on purpose:
                //the failure surfaces from deep inside session configuration and arrives
                //wrapped differently depending on which layer raised first. Narrowing it
                //would mean guessing, and guessing wrong reintroduces the partial write
                //this exists to prevent.
                $this->unavailable = true;

                return null;
            }
        }

        $user = $this->identity->current();

        return null === $user ? null : (int) $user->getId();
    }
}
