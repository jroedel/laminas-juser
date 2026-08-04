<?php

namespace JUser\Service;

use JUser\Model\User;
use JUser\Model\UserTable;
use Psr\Log\LoggerInterface;

/**
 * Issues and redeems the single-use tokens that are the only way to sign in.
 *
 * Two flavours, both stored the same way (sha256 hex in user.verification_token
 * plus an absolute UTC expiration in user.verification_expiration):
 *  - a long web token, emailed as a magic link
 *  - a short human-typable code, emailed for the API flow
 *
 * Only the hash is ever persisted, so a database leak doesn't hand out sessions.
 */
class LoginTokenService
{
    /** Number of random bytes behind a web (magic link) token */
    public const WEB_TOKEN_BYTES = 32;

    /** Characters used for the API code: uppercase alphanumeric, no ambiguous glyphs */
    public const API_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Refuse to issue another token if the last one is newer than this many seconds */
    public const RESEND_THROTTLE_SECONDS = 60;

    /** @var UserTable $userTable */
    protected $userTable;

    /** @var string $webTokenExpirationInterval ISO 8601 duration */
    protected $webTokenExpirationInterval = 'PT15M';

    /** @var string $apiCodeExpirationInterval ISO 8601 duration */
    protected $apiCodeExpirationInterval = 'PT15M';

    /** @var int $apiCodeLength */
    protected $apiCodeLength = 6;

    /** @var LoggerInterface|null $logger */
    protected $logger;

    public function __construct(UserTable $userTable, array $config = [])
    {
        $this->userTable = $userTable;
        if (isset($config['web_verification_token_expiration_interval'])) {
            $this->webTokenExpirationInterval = (string) $config['web_verification_token_expiration_interval'];
        }
        if (isset($config['api_verification_token_expiration_interval'])) {
            $this->apiCodeExpirationInterval = (string) $config['api_verification_token_expiration_interval'];
        }
        if (isset($config['api_verification_token_length'])) {
            $this->apiCodeLength = (int) $config['api_verification_token_length'];
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
     * Generate, store and return a short plaintext code for the API flow.
     *
     * @param User $user
     * @return string
     */
    public function issueApiCode(User $user)
    {
        $code = '';
        $alphabetLength = strlen(self::API_CODE_ALPHABET);
        for ($i = 0; $i < $this->apiCodeLength; $i++) {
            $code .= self::API_CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
        }
        $this->storeToken($user, $code, $this->apiCodeExpirationInterval);
        return $code;
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
     * Verify and consume a token that must belong to one specific user.
     *
     * Preferable to redeemToken() whenever the caller already knows who is
     * signing in (the API flow): a wrong guess then can't burn somebody
     * else's outstanding token.
     *
     * @param User $user
     * @param string $token
     * @return bool
     */
    public function redeemTokenForUser(User $user, string $token): bool
    {
        $token = trim($token);
        if ('' === $token) {
            return false;
        }
        $userArray = $this->userTable->getUser($user->getId());
        if (! is_array($userArray) || empty($userArray['verificationToken'])) {
            return false;
        }
        if (! hash_equals((string) $userArray['verificationToken'], UserTable::hashToken($token))) {
            return false;
        }
        if (
            ! isset($userArray['verificationExpiration'])
            || ! $userArray['verificationExpiration'] instanceof \DateTime
            || $userArray['verificationExpiration'] < $this->now()
        ) {
            return false;
        }

        $this->userTable->clearVerificationToken($user->getId());
        $user->setVerificationToken(null);
        $user->setVerificationExpiration(null);

        return true;
    }

    /**
     * Number of minutes an API code stays valid, for display purposes
     * @return int
     */
    public function getApiCodeExpirationMinutes()
    {
        return $this->intervalToMinutes($this->apiCodeExpirationInterval);
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
