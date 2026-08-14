<?php

namespace JUser\Service;

use JUser\Model\User;
use JUser\Model\UserTable;
use Psr\Log\LoggerInterface;

/**
 * Issues and redeems the single-use tokens that are the only way to sign in.
 *
 * One flavour: a long web token, emailed as a magic link, stored as sha256 hex in
 * user.verification_token plus an absolute UTC expiration in
 * user.verification_expiration. Only the hash is ever persisted, so a database leak
 * doesn't hand out sessions.
 *
 * There used to be a second flavour — a short human-typable code emailed for the v1
 * API sign-in flow (issueApiCode/redeemTokenForUser/getApiCodeExpirationMinutes, plus
 * an API_CODE_ALPHABET and two config keys). All of it went with LoginV1ApiController
 * when /api/v1 was retired; the access log showed no client had used that flow since
 * 2022. A v3 agent does not sign in at all — it presents a JWT minted from the users
 * screen, so nothing here is on its path. See ApiTokenService.
 */
class LoginTokenService
{
    /** Number of random bytes behind a web (magic link) token */
    public const WEB_TOKEN_BYTES = 32;

    /** Refuse to issue another token if the last one is newer than this many seconds */
    public const RESEND_THROTTLE_SECONDS = 60;

    /** @var UserTable $userTable */
    protected $userTable;

    /** @var string $webTokenExpirationInterval ISO 8601 duration */
    protected $webTokenExpirationInterval = 'PT15M';

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(UserTable $userTable, array $config = [])
    {
        $this->userTable = $userTable;
        if (isset($config['web_verification_token_expiration_interval'])) {
            $this->webTokenExpirationInterval = (string) $config['web_verification_token_expiration_interval'];
        }
    }

    /**
     * Generate, store and return a plaintext magic-link token.
     *
     * @param User $user
     * @return string 64-character hex string; hand it to the user, never store it
     */
    public function issueWebToken(User $user)
    {
        $token = bin2hex(random_bytes(self::WEB_TOKEN_BYTES));
        $this->storeToken($user, $token, $this->webTokenExpirationInterval);
        return $token;
    }

    /**
     * Exchange a plaintext token for the user it belongs to, consuming it.
     *
     * @param string $token
     * @return User|null null when unknown, expired or malformed
     */
    public function redeemToken(string $token): ?User
    {
        $token = trim($token);
        if ('' === $token) {
            return null;
        }
        $userArray = $this->userTable->getUserFromHashedToken(UserTable::hashToken($token));
        if (! isset($userArray) || ! is_array($userArray)) {
            return null;
        }
        if (
            ! isset($userArray['verificationExpiration'])
            || ! $userArray['verificationExpiration'] instanceof \DateTime
            || $userArray['verificationExpiration'] < $this->now()
        ) {
            if (isset($this->logger)) {
                $this->logger->notice(
                    "JUser: A login token was presented after it expired.",
                    ['userId' => $userArray['userId']]
                );
            }
            return null;
        }

        //single use: burn the token before handing back the identity
        $this->userTable->clearVerificationToken($userArray['userId']);
        $userArray['verificationToken'] = null;
        $userArray['verificationExpiration'] = null;

        return new User($userArray);
    }

    /**
     * Rate limit: has this user already been sent a still-valid token in the last minute?
     *
     * @param User $user
     * @return bool true when it's fine to issue and send a new token
     */
    public function mayIssueToken(User $user)
    {
        $expiration = $user->verificationExpiration;
        if (! $expiration instanceof \DateTime) {
            return true;
        }
        $now = $this->now();
        if ($expiration <= $now) {
            //the outstanding token is already dead, no reason to hold back
            return true;
        }
        if (null === $user->verificationToken || '' === $user->verificationToken) {
            return true;
        }
        //we don't store the issue time, so derive it from the expiration
        $issuedAt = (clone $expiration)->sub(new \DateInterval($this->webTokenExpirationInterval));
        $threshold = $now->sub(new \DateInterval('PT' . self::RESEND_THROTTLE_SECONDS . 'S'));

        return $issuedAt <= $threshold;
    }

    /**
     * @param User $user
     * @param string $plaintextToken
     * @param string $interval ISO 8601 duration
     * @return void
     */
    protected function storeToken(User $user, $plaintextToken, $interval)
    {
        $expiration = $this->now()->add(new \DateInterval($interval));
        $hash = UserTable::hashToken($plaintextToken);
        $this->userTable->setVerificationToken($user->getId(), $hash, $expiration);
        //keep the in-memory entity in step so rate limiting sees the new token
        $user->setVerificationToken($hash);
        $user->setVerificationExpiration($expiration);
        if (isset($this->logger)) {
            $this->logger->info("JUser: Issued a login token.", [
                'userId' => $user->getId(),
                'expiration' => $expiration->format('Y-m-d H:i:s'),
            ]);
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
     * @return string
     */
    public function getWebTokenExpirationInterval()
    {
        return $this->webTokenExpirationInterval;
    }

    /**
     * Number of minutes a magic link stays valid, for display purposes
     * @return int
     */
    public function getWebTokenExpirationMinutes()
    {
        return $this->intervalToMinutes($this->webTokenExpirationInterval);
    }

    /**
     * @param string $interval ISO 8601 duration
     * @return int
     */
    protected function intervalToMinutes($interval)
    {
        $reference = new \DateTimeImmutable('@0');
        $end = $reference->add(new \DateInterval($interval));
        return (int) round(($end->getTimestamp() - $reference->getTimestamp()) / 60);
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
