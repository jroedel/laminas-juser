<?php

namespace JUser\Session;

/**
 * Repairs session data written by an earlier incarnation of the code base.
 *
 * When a class stored in the session no longer exists — the 2026 Zend →
 * Laminas migration renamed every session container class, and a future
 * framework migration will rename them again — PHP unserializes the stored
 * value as __PHP_Incomplete_Class. Laminas' session Container then throws
 * "Container cannot write to storage due to type mismatch" from
 * verifyNamespace() on every page that touches that namespace. The flash
 * messenger in the site layout touches its namespace on every page, so an
 * affected visitor gets a broken response on every request until their
 * session expires (30 days) or they clear cookies.
 *
 * Dropping exactly those values lets the request proceed; the visitor loses
 * nothing readable (the owning class is gone) and self-heals on the next hit.
 *
 * Deliberately dependency-free so the vendor-less unit suite can cover it.
 */
final class SessionPruner
{
    /**
     * Remove every top-level session value that unserialized to
     * __PHP_Incomplete_Class. Nothing else is touched: any other value, the
     * session code that owns it can still read.
     *
     * @param array<string, mixed> $session usually $_SESSION
     * @return int how many values were dropped
     */
    public static function pruneIncompleteClassValues(array &$session): int
    {
        $pruned = 0;
        foreach ($session as $key => $value) {
            if ($value instanceof \__PHP_Incomplete_Class) {
                unset($session[$key]);
                $pruned++;
            }
        }

        return $pruned;
    }
}
