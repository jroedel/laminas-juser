<?php

namespace JUser\Service;

use Firebase\JWT\JWT;
use JUser\Model\ApiTokenTable;
use JUser\Model\User;
use JUser\Model\UserTable;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The one place a JWT is minted, and the one place issuance is recorded.
 *
 * Before this class there were two halves of a credential system that did not
 * know about each other: the since-retired LoginV1ApiController built a payload and
 * called RestApi\Controller\ApiController::generateJwtToken(), a *protected
 * controller method* — so signing was only reachable from inside a controller that
 * happened to extend the right base class. That is why the admin screen could not
 * issue a token without this refactor, and it is also why nothing recorded the jti:
 * the code that knew how to sign was not the code that knew what had been signed.
 * Both of those classes went away with /api/v1; this one is now the only signer,
 * which is what the refactor was for.
 *
 * Minting and recording are one operation here. They cannot be performed
 * separately, which is the property the registry depends on — an unrecorded
 * token is a token BotIdentity will refuse, so a code path that forgot to record
 * would produce credentials that fail at the worst possible moment rather than
 * loudly at issue time.
 *
 * ## What it does not do
 *
 * It does not decide *who* may be issued a token. That is the caller's business:
 * the email flow requires proof of mailbox control, and the admin screen requires
 * an administrator plus a target account holding one of `api_token_roles`. Both
 * checks are about the request, not about the credential, and putting them here
 * would mean this class needed to know about sessions and ACLs.
 */
class ApiTokenService
{
    /**
     * Characters of a `jti`: base62, 43 of them, ~256 bits.
     *
     * Long enough that guessing one is not a strategy, though guessing is not
     * actually the attack this defends against — a guessed jti is worth nothing
     * without a signature over it. The length is really about collisions: the
     * column is UNIQUE, and a collision would be an insert failure at sign-in.
     */
    public const JTI_LENGTH = 43;

    /**
     * The `jti` alphabet: base62, in the order it has always been generated in.
     *
     * Written out rather than assembled from `range()` so that the set is
     * readable at the call site and cannot drift by an off-by-one — which is
     * the only way this could break without anything failing: a jti drawn from
     * 61 characters is still a perfectly valid jti, just weaker, and nothing
     * would ever say so.
     */
    public const JTI_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** Default token lifetime, ISO 8601 duration. Six months, as it has always been. */
    public const DEFAULT_LIFETIME = 'P6M';

    /** @var ApiTokenTable $tokenTable */
    protected $tokenTable;

    /** @var UserTable $userTable */
    protected $userTable;

    /** @var string $cypherKey */
    protected $cypherKey;

    /** @var string $tokenAlgorithm */
    protected $tokenAlgorithm;

    /** @var string $lifetime ISO 8601 duration */
    protected $lifetime = self::DEFAULT_LIFETIME;

    /**
     * Roles whose holders may be issued a token from the admin screen.
     *
     * Empty by default, which disables the admin screen's issue button entirely.
     * JUser ships the mechanism; the application names the role — this module has
     * no business knowing that schoenstatt.link calls it `sch_api_bot`.
     *
     * @var string[] $issuableRoles
     */
    protected $issuableRoles = [];

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(
        ApiTokenTable $tokenTable,
        UserTable $userTable,
        $cypherKey,
        $tokenAlgorithm = 'HS256',
        array $config = []
    ) {
        $this->tokenTable = $tokenTable;
        $this->userTable = $userTable;
        $this->cypherKey = (string) $cypherKey;
        $this->tokenAlgorithm = (string) $tokenAlgorithm;
        if (isset($config['api_jwt_lifetime'])) {
            $this->lifetime = (string) $config['api_jwt_lifetime'];
        }
        if (isset($config['api_token_roles']) && is_array($config['api_token_roles'])) {
            $this->issuableRoles = array_values($config['api_token_roles']);
        }
    }

    /**
     * Mint a signed JWT for an account and record that it was issued.
     *
     * @param User $user
     * @param string|null $label free text, for telling an account's tokens apart
     * @param int|null $issuedBy the admin issuing it; null for a self-service sign-in
     * @return array{jwt: string, jti: string, expiration: \DateTime}
     */
    public function issue(User $user, $label = null, $issuedBy = null)
    {
        $userId = $user->getId();
        if (! is_numeric($userId)) {
            throw new RuntimeException('Cannot issue an API token for an unsaved user.');
        }
        $userId = (int) $userId;

        $this->assertUsableCypherKey();

        $expiration = $this->now()->add(new \DateInterval($this->lifetime));
        $jti = self::generateJti();

        //Recorded *before* the JWT exists. If the insert fails — a jti collision,
        //a table that has not been migrated — the caller gets an exception and no
        //token, which is the safe direction. Signing first and recording second
        //would hand out a credential and then discover it could not be tracked.
        $this->tokenTable->pruneExpired($userId);
        $this->tokenTable->recordIssued($jti, $userId, $expiration, $label, $issuedBy);

        $jwt = JWT::encode(
            [
                'sub' => $userId,
                //Kept as a string of the unix timestamp, matching what the retired
                //LoginV1ApiController always emitted; php-jwt is content with
                //either and changing it would invalidate nothing but would be a
                //gratuitous difference between tokens issued before and after this.
                'exp' => $expiration->format('U'),
                'jti' => $jti,
            ],
            $this->cypherKey,
            $this->tokenAlgorithm
        );

        if (isset($this->logger)) {
            $this->logger->info("JUser: Issued an API token.", [
                'userId'     => $userId,
                'jti'        => $jti,
                'issuedBy'   => $issuedBy,
                'label'      => $label,
                'expiration' => $expiration->format('Y-m-d H:i:s'),
            ]);
        }

        return ['jwt' => $jwt, 'jti' => $jti, 'expiration' => $expiration];
    }

    /**
     * Revoke one of an account's tokens.
     *
     * @param int $tokenId
     * @param int $userId
     * @param int|null $revokedBy
     * @return bool whether anything was actually revoked
     */
    public function revoke($tokenId, $userId, $revokedBy = null)
    {
        $revoked = $this->tokenTable->revoke($tokenId, $userId, $revokedBy);

        if ($revoked && isset($this->logger)) {
            $this->logger->notice("JUser: Revoked an API token.", [
                'tokenId'   => (int) $tokenId,
                'userId'    => (int) $userId,
                'revokedBy' => $revokedBy,
            ]);
        }

        return $revoked;
    }

    /**
     * @param int $userId
     * @return array[]
     */
    public function getTokensForUser($userId)
    {
        return $this->tokenTable->getTokensForUser($userId);
    }

    /**
     * May this account be issued a token from the admin screen?
     *
     * The restriction exists so the button is not a general "mint a six-month
     * bearer token for anybody" tool. Unrestricted it would let anyone holding an
     * admin session mint a credential for an *administrator* account — one that
     * outlives the session that made it and that no password change touches.
     * Confined to the configured roles, the worst it can produce is a credential
     * reaching exactly what those roles already reach.
     *
     * @param int $userId
     * @return bool
     */
    public function mayIssueForUser($userId)
    {
        if ([] === $this->issuableRoles) {
            return false;
        }

        $user = $this->userTable->getUser((int) $userId);
        if (! is_array($user) || ! isset($user['roles']) || ! is_array($user['roles'])) {
            return false;
        }

        foreach ($user['roles'] as $role) {
            $name = is_array($role) ? ($role['name'] ?? null) : null;
            if (is_string($name) && in_array($name, $this->issuableRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getIssuableRoles()
    {
        return $this->issuableRoles;
    }

    /**
     * Number of days a freshly issued token will last, for display.
     *
     * @return int
     */
    public function getLifetimeDays()
    {
        $reference = new \DateTimeImmutable('@0');
        $end = $reference->add(new \DateInterval($this->lifetime));

        return (int) round(($end->getTimestamp() - $reference->getTimestamp()) / 86400);
    }

    /**
     * Refuse to sign with a key php-jwt 7 will reject.
     *
     * The same rule App\Api\BotIdentity applies on the verifying side, for the
     * same reason: v7 rejects HMAC keys shorter than the digest size, and a
     * too-short key otherwise surfaces as a DomainException indistinguishable
     * from a caller's malformed token. Ours must not be reported as theirs.
     *
     * @return void
     */
    protected function assertUsableCypherKey()
    {
        if ('' === $this->cypherKey) {
            throw new RuntimeException('ApiRequest.jwtAuth.cypherKey is missing or empty; no token can be signed.');
        }
        if (0 === strncmp($this->tokenAlgorithm, 'HS', 2) && strlen($this->cypherKey) < 32) {
            throw new RuntimeException(sprintf(
                'ApiRequest.jwtAuth.cypherKey is %d bytes; %s requires at least 32 under php-jwt 7.',
                strlen($this->cypherKey),
                $this->tokenAlgorithm
            ));
        }
    }

    /**
     * @return \DateTime
     */
    protected function now()
    {
        return new \DateTime('now', new \DateTimeZone('UTC'));
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
     * A fresh `jti`: JTI_LENGTH characters drawn from JTI_ALPHABET.
     *
     * Public and static because it is the one piece of this class that can be
     * checked without a database — and it is worth checking, since a weakened
     * token generator produces output that looks exactly like a strong one.
     * `SchoenstattTest\Integration\JUserApiTokenEntropyTest` is what holds it.
     *
     * @return string
     */
    public static function generateJti()
    {
        //`random_int()` rather than `random_bytes()` and a modulo, which is the
        //shorter thing to write and is biased: 256 is not a multiple of 62, so the
        //first eight characters of the alphabet would come up about 1.21x as often
        //as the rest. `random_int()` rejects and redraws to avoid exactly that, and
        //throws rather than falling back to a weak source if the CSPRNG is
        //unavailable — an exception at issue time being much the better outcome.
        //The bound comes off the alphabet rather than being written as 61, so that
        //changing the alphabet cannot leave its last character unreachable — a
        //weakening nothing would report.
        $last = strlen(self::JTI_ALPHABET) - 1;
        $jti  = '';
        for ($i = 0; $i < self::JTI_LENGTH; $i++) {
            $jti .= self::JTI_ALPHABET[random_int(0, $last)];
        }

        return $jti;
    }
}
