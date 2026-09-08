JUser\Bridge\Laminas
====================

**Code that only a laminas host runs.** Everything in this namespace exists because
schoenstatt.link — the application this module was extracted from — is a laminas-mvc
application that has not finished moving to Symfony. A host built on anything else needs none
of it, and JUser 3.0.0's `require` treats it accordingly: the packages these bridge classes
need are `suggest`, not hard dependencies — `laminas-authentication`, `laminas-session` and
`laminas-view`. Each `suggest` line names the class here that wants it, so a host reading the
manifest can tell what it would lose by leaving one out.

**BjyAuthorize is gone (2026-09-09).** The three classes that bridged to it — the identity
provider `ZfcUserZendDbPlusSelfAsRole` (+ factory), the role entity `Role`, and the
`unauthorized_strategy` `RedirectionStrategy` — were deleted when schoenstatt.link removed
`bjy-authorize` (and with them this module's need for `bjy-authorize`, `laminas-permissions-acl`,
`laminas-mvc`, `laminas-eventmanager` and `laminas-http`). A laminas + bjy host now supplies its
own identity provider and unauthorized strategy; JUser's own surface reaches authorization
through the host's `Host\AccessInterface`, and the host's engine reads the roles and route
guards this module still declares in `bjyauthorize` config.

It is a **namespace, not a package**, and deliberately so. A fourth repository would need its
own release cycle, its own CI and its own version constraint against this one, to hold code
that has one consumer and a finite life. Here it stays type-checked by the same PHPStan run
and covered by the same tests, and it is obvious what it is from its path.

What is in it, and which host concern each answers:

| class | what a laminas host uses it for |
|---|---|
| `SessionUser` | `AuthenticationService` storage that keeps only the user id and re-reads the row |
| `AuthenticationServiceFactory` | builds that service — this is `JUser\AuthService` |
| `UserService`, `UserServiceFactory` | the historical `zfcuser_user_service` |
| `AuthServiceActingUserProvider` (+ factory) | SionModel's `ActingUserProviderInterface`, over the auth service |
| `ZfcUserDisplayName`, `ZfcUserIdentity`, `ZfcUserViewHelperFactory` | two `.phtml` view helpers a bridged layout still calls |

`UserIdRoles` (+ factory) was a fifteenth, a BjyAuthorize role provider registering one
`user_<id>` role per account. Deleted 2026-08-22: nothing in the life of the database ever
wrote a rule naming one, and their real cost was making the assembled ACL depend on the
`user` table — which an account is added to on every first-time sign-in — and therefore
uncacheable. (The identity provider had to stop returning `user_<id>` in the same commit;
both classes are now gone entirely with the BjyAuthorize removal above.)

**Class names did not change, only namespaces.** That is what makes the move reviewable as a
move: `git log --follow` still works, and a name in a five-year-old commit message or an
incident note still finds the file. The `ZfcUser*` names are the same kind of decision one
level down — ZfcUser has not been installed here for years, and renaming them would break
every `.phtml` calling `zfcUserDisplayName()` in exchange for tidier strings.

**`SessionUser` is the one to read before changing anything.** It keeps only the user id in
the session and resolves it against the database on every request, which is why deactivating
an account takes effect on the *next request* of a session that is already open rather than at
an invisible timeout. A host implementing `JUser\Host\IdentityInterface` over something that
caches a user object for the life of the session silently loses that.

**Nothing here is reached from `JUser\Page\*`, `JUser\Controller\*` or `JUser\Host\*`.** The
dependency runs one way: a laminas host wires these *as* implementations of the contract. If
you find yourself importing `Bridge\Laminas` from outside it, the thing you want is an
interface in `JUser\Host\`.
