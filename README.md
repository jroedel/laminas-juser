JUser
=====

Passwordless user management: a magic-link sign-in flow, an admin surface for accounts
and roles, and API-token issuance. Originally a fork of `manuakasam/SamUser`.

**There is no password.** Signing in means asking for a link by email and clicking it;
registering is the same request, because an address nobody has seen simply becomes an
account. Redeeming a link is also what verifies the address, so there is no separate
confirmation step. See `JUser\Service\LoginTokenService` for the token (only its sha256
digest is stored, in `user.verification_token`, with an absolute UTC expiry alongside).

**`user.state` and `user.email_verified` mean two different things, and since 2026-08-21
they are kept apart.** `state` — the Active checkbox on `/users/{id}/edit` — means *may
sign in*, enforced in three places inside this module and a fourth in the consuming
application:

| where | what it refuses |
|---|---|
| `LoginController::issueAndSend()` | mails no link, and does not say so |
| `LoginController::verifyAction()` | a link that was already in flight, with a 403 |
| `Authentication\Storage\SessionUser::read()` | a session that is **already open**, on its next request |
| the application's API identity | a bearer token for the account |

The third is what makes `state = 0` mean "cannot act" rather than "cannot sign in again",
and it needed nothing new: only the user id is in the session, so the row is re-read every
request, and `isEmpty()` already clears the storage when a read comes back null.

`email_verified` means *someone has proved they read mail here*, and redeeming a link sets
it, in `UserTable::clearVerificationToken()`. A new account starts **active and
unverified**, which is why open registration still works — created inactive, its very first
magic link would be refused. An application rendering `EditUserForm` for *creation* must
tick Active itself: the element declares `'value' => 0`, so left alone an administrator
creates accounts that can never sign in and are never told why.

Before that they were conflated: new accounts were created inactive and `verifyAction()`
activated whatever it redeemed, so every deactivated account reactivated itself on its next
sign-in link. The checkbox read like a ban control and was not one. **A consuming
application upgrading past this needs one data migration first** — any account sitting at
`state = 0` because it merely never confirmed must be moved to `state = 1`, or it becomes
retroactively banned. In schoenstatt.link that was 32 of 292 accounts
(`database/db8.6.sql`, run at the `pre` phase so there is no window in which they are
refused).

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

The host contract
-----------------

3.0.0 reaches the application through six interfaces it declares in `JUser\Host\`, and
through nothing else. Everything an application has to provide is on this list; anything
not on it, this module owns.

| interface | what the application provides | what it lets this module drop |
|---|---|---|
| `UrlBuilderInterface` | a path or an absolute URL for one of *its* route names | `laminas-router` |
| `IdentityInterface` | who is signed in; make it so; forget it | `laminas-authentication` |
| `SessionInterface` | a namespaced scratch space, plus regenerate and forget-me | `laminas-session` |
| `FlashInterface` | a message for the next page, and one for this one | `laminas-mvc-plugin-flashmessenger` |
| `RouteResolverInterface` | which route a path belongs to, if any | `laminas-http` |
| `AccessInterface` | may *this account* reach that route | `bjy-authorize`, `laminas-permissions-acl` |

Each interface's docblock is the specification, including the parts that are easy to
implement wrongly and impossible to detect: one flash-messenger instance per request, a
locale prefix stripped before a path is resolved, identity re-read per request rather
than cached for the session, and default-deny when an authorization question cannot be
answered.

Two of them exist in a shape that looks odd until you know why.
`RouteResolverInterface` takes a path rather than being handed a router, because what
has to be stripped off a path before it can be matched is a fact about the host's
routing — measured on schoenstatt.link, where a locale-prefixed `?redirect=` matched
nothing and quietly sent every visitor to the home page after signing in. And
`AccessInterface` asks about a **named account** rather than the current visitor, because
the one caller is deciding where to send someone who is anonymous as the question is
asked and signed in a few lines later.

### The template contract

The templates in this package are Twig and expect the environment to provide:

* `juser_layout` — a global, from `JUser\Twig\JUserExtension`; every template does
  `{% extends juser_layout %}`. The layout must define a `content` block and must render
  messages, or `FlashInterface` is silent with no other symptom.
* `juser_path(route, params, query)` — the same extension, over `UrlBuilderInterface`.
* `translate(...)` and `csp_nonce()` — the application's.
* `form_open`, `form_close`, `form_row`, `form_hidden`, `form_submit`, `form_button` —
  `SionModel\Twig\FormExtension` over `SionModel\Form\BootstrapFormRenderer`, which is
  in that package precisely because it is not JUser-specific: any host rendering laminas
  forms in Twig needs it.

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
| **3.0.0** | Symfony-oriented, and a **drop-in**: this module owns its own controllers, templates, forms, route fragment and Twig extension, and reaches the application only through the six interfaces in `JUser\Host\`. Thirteen of the eighteen `laminas/*` packages leave `require`; the five that stay are data concerns, not framework ones. |

3.0.0 cannot land while this module still serves the sign-in routes, because those are
what require the MVC layer. The `require` block in `composer.json` names the components
honestly for that reason: the five listed above are exactly what 3.0.0 has to remove.

### Where the 3.0.0 work stands

2026-08-21: **the contract above exists, and nothing is wired to it yet.** The six
interfaces and `JUser\Twig\JUserExtension` are declared; no class in this module
implements or consumes one, no template calls `juser_path()`, and no dependency has been
removed from `composer.json`. That is deliberate — the interfaces are what the ported
controllers get rewritten against when they arrive here, and landing them first means
that rewrite is reviewable as a rewrite rather than as a move plus a rewrite. The
right-hand column of the table is a plan, not yet a fact.

2026-08-21: **the user-administration surface is gone from this module.**
`JUser\Controller\UsersController`, its factory and its six view scripts were deleted, and
the seven `juser/*` routes are now served by the consuming application's Symfony kernel.
The routes themselves stay declared here — a name is what `laminas_path()` and a
BjyAuthorize guard address — but nothing in this module dispatches them.

What is left between here and 3.0.0 is no longer a deletion. Under the drop-in goal this
module takes **delivery** of the surface the application ported: eight Symfony
controllers, ten Twig templates and four support classes, rewritten against the
interfaces above and shipped with a route fragment a host includes. The application then
switches over and deletes its copies — the only step a visitor can see, and the one that
deploys alone.

`JUser\Controller\LoginController` still goes, and it is still the piece that carries the
dependencies: the four `zfcuser/*` sign-in routes and the three view scripts under
`view/juser/login/` are the last consumers of `AbstractActionController`, of
`laminas-router`'s route stack, of `laminas-view`'s renderer and of the flash-messenger
plugin. It is deliberately kept until the ported sign-in surface has settled, because
while the laminas routes stay declared, deleting the application's Symfony declarations
puts it back in charge — the one rollback on this module's surface that does not need a
deploy.

The laminas-shaped code that remains useful is **demoted, not deleted**: `SessionUser`,
`UserService`, `AuthServiceActingUserProvider`, the two view helpers, the two
BjyAuthorize identity/role providers and `Entity\Role` become `JUser\Bridge\Laminas\`,
which is how a laminas host implements the contract. Their packages move to `require-dev`
plus `suggest`, so they still type-check and `require` still reads honestly.

**One thing the table above does not say.** `bjy-authorize` leaving this module's
`require` is not the same as leaving a site. Every route a laminas host serves still
needs a guard and that guard is the host's; on schoenstatt.link 27 files import
`BjyAuthorize` directly and about 51 touch its config key. It is also not the constraint
people assume: bjy-authorize and `laminas-mvc` declare the *same* PHP ceiling
(`~8.4.0`), and laminas-mvc pins `laminas-servicemanager` to 3.x on its own — so removing
bjy lifts nothing that laminas-mvc is not already holding down. Dropping it site-wide
means moving to Symfony Security voters, which is a separate decision on a separate
schedule.
