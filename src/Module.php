<?php

namespace JUser;

/**
 * **This module hooks nothing.**
 *
 * `onBootstrap()` was removed on 2026-08-21, with `LoginController`. It started the session
 * and pruned it — a `MvcEvent` hook, and therefore something only a laminas-mvc application
 * ever ran, which is exactly what JUser 3.0.0 stops assuming a host is. The consuming
 * application does it now: on this site `Application\Module::onBootstrap()` for a
 * laminas-served request and `App\Http\SessionListener` for a Symfony-served one, both
 * calling `JUser\Session\SessionPruner::pruneIncompleteClassValues()`.
 *
 * That pruning is not optional and is worth naming here rather than only where it is called.
 * A session that passes validation can still hold a value whose class no longer exists —
 * written before a class-renaming migration — and it fatals at the *first container access*
 * rather than at `start()`, so nothing wraps it and the request dies with no useful message.
 * A host that starts a session for this module and does not prune it inherits that.
 *
 * `getConfig()` is all that is left, which makes the class keepable rather than deletable:
 * laminas-mvc still discovers modules by class and the config still declares routes, view
 * helpers, services and session settings. A host with no laminas-mvc reads
 * `config/module.config.php` itself, or ignores it and wires the six services it wants.
 */
class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
