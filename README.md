JUser
=====

Passwordless user management: a magic-link sign-in flow, an admin surface for accounts
and roles, and API-token issuance. Originally a fork of `manuakasam/SamUser`.

**There is no password.** Signing in means asking for a link by email and clicking it;
registering is the same request, because an address nobody has seen simply becomes an
account. Redeeming a link is also what verifies the address, so there is no separate
confirmation step. See `JUser\Service\LoginTokenService` for the token (only its sha256
digest is stored, in `user.verification_token`, with an absolute UTC expiry alongside).

Requirements
------------

Laminas MVC, `BjyAuthorize` for authorization, `SionModel` for the table layer and
`JTranslate` for translation. `SlmLocale` is *not* a dependency — the locale
configuration in `config/juser.global.php.dist` is there for the consuming application's
convenience and this module reads none of it.

What this module does **not** use, despite older versions of this file saying so:
ZfcUser, ZfcBase and GoalioRememberMe. The `ZfcUser*` class names that survive — the
identity provider, the two view helpers, the `zfcuser/*` route names — are names, kept
because renaming a route breaks every `url()` call and every guard entry that names it.

Installation
------------

```
composer require jroedel/laminas-juser
```

1. Copy `config/juser.global.php.dist` and `config/juser.local.php.dist` into the
   application's autoload directory and fill them in.
2. Copy `config/acl.global.php.dist` if the application has no ACL config of its own.
3. Enable the modules, in this order:

```php
return [
    'modules' => [
        // ...
        'BjyAuthorize',
        'JUser',
        'SionModel',
        'JTranslate',
    ],
];
```

4. The admin surface is at `/users`. **Check the guard entries** in your
   `juser.global.php`: every `juser/*` route should name an administrator role, and the
   `.dist` file is the reference for which routes exist.

Releases
--------

The `modernization` branch is the maintained line. Note that **`1.0.0` (2022-07-19) is
not an ancestor of it** — it belongs to the abandoned `1.0.x` branch — so the version
numbers are not a single chain of history. That is deliberate and recorded here rather
than tidied away, because `git describe` on `modernization` reports `0.1.0-…` and reads
like the tags were lost.

| version | what it is |
|---|---|
| **2.0.0** | The passwordless line: magic-link sign-in, `symfony/mailer`, API tokens, monolog, DB adapters injected into forms rather than fetched from a static registry, and the password-era columns and routes retired. Still a Laminas MVC module — it owns laminas routes, controllers and view scripts. |
| **3.0.0** | Symfony-oriented. No `laminas-mvc`, `laminas-router`, `laminas-view`, `laminas-http` or `laminas-mvc-plugin-flashmessenger`, and no `bjy-authorize`. The consuming application owns the routing and the templates; this module contributes services, forms and the token/identity layer. |

3.0.0 cannot land while this module still serves the sign-in routes, because those are
what require the MVC layer. The `require` block in `composer.json` names the components
honestly for that reason: the five listed above are exactly what 3.0.0 has to remove.
