<?php

namespace JUser\Model;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Sql;

/**
 * The registry of API tokens that have been issued.
 *
 * Deliberately NOT a SionTable, and the two reasons are worth stating because
 * every other table in this module is one:
 *
 * 1. **No cache.** SionTable caches query results in APCu and invalidates them
 *    by entity name. A revocation that takes effect "once the cache expires" is
 *    not a revocation. Every read here goes to the database, every time, and the
 *    reads are single-row lookups on a unique or covering index.
 * 2. **No change tracking.** SionTable writes a row per changed field into
 *    sch_changes, which exists so edits to *content* can be reviewed and
 *    reverted. Issuing a credential is not a content edit, and "revert" is a
 *    meaningless operation on one. The provenance columns on the row itself
 *    (issued_by, revoked_by, and the timestamps) are the audit trail.
 *
 * Nothing here ever sees a JWT. It stores and reads `jti` claims — see
 * database/db6.7.sql on why that distinction is the point.
 */
class ApiTokenTable
{
    public const TABLE_NAME = 'user_api_token';

    /** @var AdapterInterface $adapter */
    protected $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Record that a token was issued.
     *
     * @param string $jti the token's jti claim
     * @param int $userId the account the token authenticates as
     * @param \DateTimeInterface $expiresOn mirrors the token's own exp claim
     * @param string|null $label free text to tell an account's tokens apart
     * @param int|null $issuedBy the admin who issued it; null for a self-service sign-in
     * @return void
     */
    public function recordIssued($jti, $userId, \DateTimeInterface $expiresOn, $label = null, $issuedBy = null)
    {
        $sql = new Sql($this->adapter);
        $insert = $sql->insert(self::TABLE_NAME)->values([
            'jti'        => $jti,
            'user_id'    => (int) $userId,
            'label'      => (null === $label || '' === $label) ? null : $label,
            'issued_on'  => $this->now()->format('Y-m-d H:i:s'),
            'issued_by'  => null === $issuedBy ? null : (int) $issuedBy,
            'expires_on' => $expiresOn->format('Y-m-d H:i:s'),
        ]);
        $sql->prepareStatementForSqlObject($insert)->execute();
    }

    /**
     * Is this jti a token we issued, to this user, and not since revoked?
     *
     * The user id is part of the question rather than something the caller
     * checks afterwards: a valid jti belonging to a *different* account must not
     * vouch for this one, and comparing the two outside the query is an easy
     * thing to forget.
     *
     * Expiry is not checked here. php-jwt has already rejected an expired token
     * on its `exp` claim before anything reaches this table, and duplicating the
     * rule in a second place invites the two to disagree.
     *
     * @param string $jti
     * @param int $userId
     * @return bool
     */
    public function isLive($jti, $userId)
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select(self::TABLE_NAME)
            ->columns(['token_id'])
            ->where([
                'jti'     => $jti,
                'user_id' => (int) $userId,
                'revoked_on' => null,
            ]);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        //laminas-db returns **false**, not null, for an empty result set, and
        //`false !== null` is true — so a `null !== $row` test here would answer
        //"live" for every token ever presented, which is the whole check
        //inverted. The same trap cost us a day in BotIdentity::holdsRequiredRole().
        return is_array($row) || is_object($row);
    }

    /**
     * Every token on record for an account, newest first, live and dead alike.
     *
     * Revoked and expired rows are returned too: the screen showing this is an
     * audit surface, and a list that hides what was revoked cannot answer the
     * question an admin actually has after an incident.
     *
     * @param int $userId
     * @return array[] each with keys tokenId, jti, label, issuedOn, issuedBy,
     *                 expiresOn, revokedOn, revokedBy, isLive
     */
    public function getTokensForUser($userId)
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select(self::TABLE_NAME)
            ->where(['user_id' => (int) $userId])
            ->order(['issued_on' => 'DESC', 'token_id' => 'DESC']);

        $results = $sql->prepareStatementForSqlObject($select)->execute();

        $now = $this->now();
        $tokens = [];
        foreach ($results as $row) {
            $expiresOn = $this->toDateTime($row['expires_on']);
            $revokedOn = $this->toDateTime($row['revoked_on']);
            $tokens[] = [
                'tokenId'   => (int) $row['token_id'],
                'jti'       => $row['jti'],
                'label'     => $row['label'],
                'issuedOn'  => $this->toDateTime($row['issued_on']),
                'issuedBy'  => isset($row['issued_by']) ? (int) $row['issued_by'] : null,
                'expiresOn' => $expiresOn,
                'revokedOn' => $revokedOn,
                'revokedBy' => isset($row['revoked_by']) ? (int) $row['revoked_by'] : null,
                'isLive'    => null === $revokedOn && $expiresOn instanceof \DateTime && $expiresOn > $now,
            ];
        }

        return $tokens;
    }

    /**
     * Look one token up by its surrogate id.
     *
     * @param int $tokenId
     * @return array|null
     */
    public function getToken($tokenId)
    {
        $sql = new Sql($this->adapter);
        $select = $sql->select(self::TABLE_NAME)->where(['token_id' => (int) $tokenId]);
        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if (! is_array($row) && ! is_object($row)) {
            return null;
        }
        $row = (array) $row;

        return [
            'tokenId'   => (int) $row['token_id'],
            'jti'       => $row['jti'],
            'userId'    => (int) $row['user_id'],
            'label'     => $row['label'],
            'expiresOn' => $this->toDateTime($row['expires_on']),
            'revokedOn' => $this->toDateTime($row['revoked_on']),
        ];
    }

    /**
     * Revoke a token. Takes effect on the next request that presents it.
     *
     * Scoped by user id as well as token id so a mis-typed id cannot revoke a
     * different account's token, and idempotent on `revoked_on IS NULL` so
     * re-submitting the form does not overwrite who revoked it first.
     *
     * @param int $tokenId
     * @param int $userId the account the token must belong to
     * @param int|null $revokedBy the admin doing it
     * @return bool whether a row actually moved
     */
    public function revoke($tokenId, $userId, $revokedBy = null)
    {
        $sql = new Sql($this->adapter);
        $update = $sql->update(self::TABLE_NAME)
            ->set([
                'revoked_on' => $this->now()->format('Y-m-d H:i:s'),
                'revoked_by' => null === $revokedBy ? null : (int) $revokedBy,
            ])
            ->where([
                'token_id'   => (int) $tokenId,
                'user_id'    => (int) $userId,
                'revoked_on' => null,
            ]);

        return $sql->prepareStatementForSqlObject($update)->execute()->getAffectedRows() > 0;
    }

    /**
     * Drop this account's rows for tokens that expired more than a day ago.
     *
     * Called when the account is issued a new token, so the table stays
     * proportional to what is live rather than to everything ever issued, and
     * no scheduled job has to exist for it to be true.
     *
     * **Revoked rows are kept regardless of age.** They are the record that
     * somebody deliberately killed a credential, which is the one thing here
     * worth looking up long after the fact. The day of slack keeps a token that
     * has only just lapsed visible on the screen, where "expired yesterday"
     * explains an agent that stopped working.
     *
     * @param int $userId
     * @return int rows removed
     */
    public function pruneExpired($userId)
    {
        $cutoff = $this->now()->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        $sql = new Sql($this->adapter);
        //Built in two statements on purpose: `$delete->where(...)` returns the
        //Delete, but `$delete->where` is the Where predicate set, so chaining
        //the two forms silently hands prepareStatementForSqlObject() a Where
        //instead of a Delete.
        $delete = $sql->delete(self::TABLE_NAME);
        $delete->where(['user_id' => (int) $userId, 'revoked_on' => null]);
        $delete->where->lessThan('expires_on', $cutoff);

        return $sql->prepareStatementForSqlObject($delete)->execute()->getAffectedRows();
    }

    /**
     * @param mixed $value
     * @return \DateTime|null
     */
    protected function toDateTime($value)
    {
        if (! is_string($value) || '' === $value || '0000-00-00 00:00:00' === $value) {
            return null;
        }
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));

        return false === $date ? null : $date;
    }

    /**
     * @return \DateTime
     */
    protected function now()
    {
        return new \DateTime('now', new \DateTimeZone('UTC'));
    }
}
