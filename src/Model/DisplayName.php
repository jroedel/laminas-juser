<?php

declare(strict_types=1);

namespace JUser\Model;

use function strpos;
use function substr;

/**
 * The name to show for an account, and the fallbacks when it has none.
 *
 * Three steps, in this order: the display name the account chose, then its username, then
 * the local part of its email address. The last one is why this is a function rather than
 * a property read — showing a whole address where a name belongs publishes it, and this
 * value reaches page chrome on every request.
 *
 * Extracted from the `zfcUserDisplayName` view helper, which is gone with the rest of
 * `JUser\Bridge\Laminas`. The rule is unchanged; only the place it lives is, so that it is
 * available to a host that renders with Twig and has no laminas view helpers to reach.
 */
final class DisplayName
{
    /** False, not null or an empty string, because "nobody is signed in" is a distinct answer. */
    public static function of(?User $user): string|false
    {
        if (! $user instanceof User) {
            return false;
        }

        $name = (string) $user->getDisplayName();
        if ('' !== $name) {
            return $name;
        }

        $name = (string) $user->getUsername();
        if ('' !== $name) {
            return $name;
        }

        $email = (string) $user->getEmail();
        $at    = strpos($email, '@');

        return false === $at ? $email : substr($email, 0, $at);
    }
}
