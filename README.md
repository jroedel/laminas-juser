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

`SionModel` for the table layer and `JTranslate` for translation, plus Twig and
symfony/http-foundation for the controllers and templates. `SlmLocale` is *not* a dependency
— the locale configuration in `config/juser.global.php.dist` is there for the consuming
application's convenience and this module reads none of it.

**This module registers nothing with laminas-mvc as of 2026-08-21.** No controllers, no
controller plugins, no view manager, and `Module` has no `onBootstrap()`: the session is the
host's to start (see that class). The laminas packages still in `require` are there for code
a laminas host uses — the session storage, the two view helpers, the BjyAuthorize identity
and role providers — and for the table and form layers, which are data concerns rather than
framework ones. Trimming that list is the last piece of 3.0.0.

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
| `AccessInterface` | may *this account* reach that route, and may *this visitor* | `bjy-authorize`, `laminas-permissions-acl` |

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

### Wiring it up

A host provides the six implementations, registers the Twig extension and the template
path, and includes the route fragment:

```php
$twig->addExtension(new JUserExtension($urls, 'my-layout.html.twig'));
$loader->addPath(JUserExtension::templatePath(), JUserExtension::TEMPLATE_NAMESPACE);

(require '.../module/JUser/config/symfony-routes.php')(
    function (string $name, string $path, $controller, RouteAudience $audience,
              array $defaults = [], array $requirements = []): void {
        // register with your router, and map $audience onto your authorization layer
    }
);
```

The fragment is a **closure that calls back**, not a `RouteCollection`, because a host
needs to attach its own defaults and its own authorization to each route — see
`config/symfony-routes.php` and `JUser\Routing\RouteAudience`.

### The template contract

The templates in this package are Twig and expect the environment to provide:

* `juser_layout` — a global, from `JUser\Twig\JUserExtension`; every template does
  `{% extends juser_layout %}`. The layout must define a `content` block and must render
  messages, or `FlashInterface` is silent with no other symptom.
* `juser_path(route, params, query)` — the same extension, over `UrlBuilderInterface`.
* `translate(...)` and `csp_nonce()` — the application's. The nonce is needed by exactly one
  page, the API-token screen, whose copy button is an inline script.
* an `inline_scripts` block in the layout, for that same script, and a `content` block for
  everything.
* `juser_person_template` — optional, from the same extension. The user index has a Person
  column and a person is the one thing on this surface that is entirely the host's: this
  module has no person model, only `PersonValueOptionsProviderInterface` and whatever rows a
  host answers it with. Unset leaves the column empty, which is already what a host with no
  provider gets.
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
5. **Start the session** — this module no longer does. On a laminas host, do it *above*
   module priority: a hook that asks BjyAuthorize for an identity bakes the identity's roles
   into the ACL for the whole request, and an unstarted session bakes `guest`. Prune it with
   `JUser\Session\SessionPruner::pruneIncompleteClassValues($_SESSION)` and swallow a
   validation failure. See `JUser\Module`.
6. **Wire the host contract** if you are serving these pages from Symfony rather than
   through laminas-mvc: six implementations, the Twig extension and the route fragment. See
   "The host contract" and "Wiring it up" above. This is what 3.x exists for, and a host
   doing it needs none of the laminas-mvc setup in step 3.

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

It no longer serves any route through laminas-mvc, and the laminas-shaped code that a
laminas host still uses is separated into `JUser\Bridge\Laminas\`. What is left before the
tag is the `require` trim: the block still names packages only that namespace needs,
deliberately, so it never claims to be freer of laminas than it is.

### Where the 3.0.0 work stands

2026-08-21: **the laminas-shaped code is demoted, not deleted.** Fifteen classes moved into
`JUser\Bridge\Laminas\` — the session storage, the auth-service factory, the historical
`zfcuser_user_service`, SionModel's acting-user provider, the two `.phtml` view helpers, the
BjyAuthorize identity and role providers, its role entity, and the unauthorized-strategy
redirect. Every one of them exists because a laminas host runs it; a host built on anything
else needs none. `src/Bridge/Laminas/README.md` is the table.

**Class names did not change, only namespaces**, which is what makes it reviewable as a move:
`git log --follow` still works and a name in a five-year-old incident note still finds the
file. Nothing in `JUser\Page\*`, `JUser\Controller\*` or `JUser\Host\*` reaches into it —
the dependency runs one way, a laminas host wiring these *as* implementations of the contract.

What this unblocks is the `require` trim, which is the last thing before the tag: the packages
these fifteen need become `require-dev` plus `suggest`.

2026-08-21: **`LoginController` is gone, and with it the last thing here that needed
laminas-mvc.** The controller, its factory, its four view scripts, the whole `view/` tree and
the template map are deleted, and so are the `controllers`, `controller_plugins` and
`view_manager` config sections. `Module::onBootstrap()` went too — starting a session is an
application's job, and a module that hooks `MvcEvent` cannot be dropped into a host that has
none.

Two things a consuming application has to take over, and both bite silently if it does not:

* **Start the session, and prune it.** A session that fails validation must be discarded
  rather than fail the request, and one that *passes* can still hold a value whose class no
  longer exists — which fatals at the first container access, not at `start()`, so nothing
  wraps it. `JUser\Session\SessionPruner` is still here for exactly that.
  **Where** it is started matters: on a laminas host it must run before any module hook that
  asks BjyAuthorize for an identity, because `Authorize::load()` bakes the identity's roles
  into the ACL for the whole request and an unstarted session bakes `guest`. On
  schoenstatt.link that meant a listener attached above module priority rather than another
  module's `onBootstrap()`.
* **Replace the `zfcUserAuthentication` controller plugin.** It only ever wrapped the same
  `AuthenticationService`. Its consumers use `identity()` from `laminas-mvc-plugin-identity`,
  whose factory resolves `Laminas\Authentication\AuthenticationService` — which this
  module's config aliases to its own service, so the answer is identical and `null` means
  anonymous.

What is still declared here: the routes (a name is what `url()` and a guard entry address),
the view helpers (a host layout that has not been ported still calls `zfcUserDisplayName`),
the services, and the session configuration.

2026-08-21: **the user-administration surface has arrived too**, on the same terms —
nothing dispatches it. `JUser\Controller\{UsersController, UserCreateController,
UserEditController, UserDeleteController, ApiTokensController}` over `JUser\Page\UserAdmin`,
six templates, and seven more routes in the fragment. This half has **no laminas twin at
all**: `JUser\Controller\UsersController` and its view scripts were deleted when the
consuming application ported these pages, so there is nothing to roll back to and nothing to
keep in step.

Three things the interfaces changed, beyond replacing the container lookups:

* **`AccessInterface` grew a second method.** The index draws a pencil and a crown behind a
  permission check, and that question is about *the visitor*, not about a named account — so
  `visitorMayReachRoute()` sits beside `userMayReachRoute()` rather than being folded into
  it. Folding them would mean this module resolving the current user's roles, which is
  exactly what it does not know how to do. The controller asks **once per page** where the
  laminas view asked once per row; same answer, since the question does not mention the row.
* **A misconfigured person provider is no longer representable.** The version this came from
  resolved a service id out of config and threw when it named something of the wrong type. A
  host injecting a typed `PersonValueOptionsProviderInterface|null` makes that a compile-time
  matter, so the check went where the resolution went — into the host's wiring.
* **The forms come from a PSR-11 locator, not the constructor.** `EditUserForm` is shared and
  both `setValidatorsForCreate()` and `prepareForEdit()` mutate the instance they are called
  on, so a form captured in a constructor would have the create page hand the edit page a
  form still carrying the create-only uniqueness validators. `UserAdmin::form()` is the one
  place that fetch happens.

2026-08-21: **the sign-in surface has arrived, and nothing dispatches it yet.**
`JUser\Controller\{SignInController, VerifyController, LogoutController}` over
`JUser\Page\{SignIn, RedirectTarget, CookieExplainer}`, five Twig templates under
`templates/`, and the route fragment. Ported from the consuming application, which served
them from its own `src/` from 2026-08-21, and rewritten against the interfaces — so the
laminas `LoginController` and the application's copies both still exist and both still
answer. Nothing here is reachable until a host wires it.

Two things changed shape in the port and are worth reading before the code:

* **`RedirectTarget` lost two thirds of its size** (318 lines to 113). The laminas router,
  the ACL, the locale-prefix stripping and the role resolution moved behind
  `RouteResolverInterface` and `AccessInterface`. What stayed is the part that must hold in
  *any* host: two string rules about what a redirect may look like. They are the entire
  defence against an off-site destination — resolving a route is none, since a router
  matches a URL's path and discards its host — so they live here rather than in an adapter,
  and `test/Integration/JUserRedirectTargetTest` in the consuming application drives them
  against a resolver that says yes to everything.
* **The emailed link is assembled from the host's URL builder**, not from a router inside
  `Mailer`. `Mailer::sendLoginLink()` is the new entry point and takes a finished absolute
  URL; `sendLoginLinkEmail()` still assembles one for the laminas path and delegates. That
  old arrangement is what sent links out with no locale prefix — the unprefixed twin of the
  real route, which 302s, spending a single-use token on anything that would not follow the
  hop.

**Known blocker for actual reuse:** `Mailer` hardcodes this site's identity — the `From`
address, the display name, and "Schoenstatt Link" inside the translated body and subject. A
drop-in package cannot, and fixing it means new config keys plus new phrases, so it is
listed here rather than folded into a port.

2026-08-21: **the contract above was declared, empty.** The six interfaces and
`JUser\Twig\JUserExtension`, with nothing implementing or consuming them — landed first so
that the port above reviews as a rewrite rather than as a move plus a rewrite. It is
consumed now, by the sign-in surface; the right-hand column of the table is still a plan
rather than a fact, because no dependency comes out of `composer.json` until the laminas
controller goes.

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
