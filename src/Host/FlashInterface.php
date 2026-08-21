<?php

declare(strict_types=1);

namespace JUser\Host;

use JTranslate\I18n\TranslatableMessage;

/**
 * Tells the visitor something, on a page this module is not rendering the chrome of.
 *
 * Both methods exist because the **lifetime** differs and the difference is not
 * cosmetic: {@see self::flash()} is read by the *next* page and {@see self::now()} by
 * the response being returned. A redirect that flashes nothing is silent; a re-rendered
 * form that flashes instead of showing the message now puts "please correct the
 * following" on whatever page the visitor opens next, which is a defect this module has
 * shipped twice.
 *
 * ## What it replaces
 *
 * `Laminas\Mvc\Plugin\FlashMessenger` and `JTranslate\Controller\Plugin\NowMessenger`,
 * used between them by six controllers, `SignIn` and `UserAdmin`. That takes
 * `laminas-mvc-plugin-flashmessenger` out of `require` — and it is the one dependency
 * on this list that porting the controllers into this package would otherwise have
 * *added*, since a Symfony-served controller cannot reach a laminas controller plugin
 * any other way. Abstracting it is therefore not tidying: it is the difference between
 * 3.0.0 dropping the package and inheriting it.
 *
 * ## One implementation instance per request, and the host must ensure it
 *
 * `FlashMessenger::addMessage()` calls `getMessagesFromContainer()` on first use, which
 * moves every namespace out of the session container into that instance and unsets it
 * from the container. A *second* instance doing that after the first has written takes
 * the first's message out of the session and holds it in an object discarded at the end
 * of the request — so two messages silently become one. Measured 2026-08-21 on
 * redemption, which reports "You are signed in." and "but not there" together and
 * showed only the second.
 *
 * A host implementing this over laminas must therefore hold **one** FlashMessenger for
 * the life of the request. This module makes that possible by asking for the interface
 * once per controller and never constructing a messenger itself; it cannot enforce it,
 * which is why it is written down here.
 */
interface FlashInterface
{
    /**
     * Survives a redirect; rendered by the next page in this session.
     *
     * @param string|TranslatableMessage $message A `TranslatableMessage` is how data
     *        reaches a message without becoming part of the phrase key — see that
     *        class, and note it is deliberately not `Stringable`, so an implementation
     *        that narrows this parameter to `string` turns a correct call into a fatal
     *        rather than into a bad translation.
     */
    public function flash(Severity $severity, string|TranslatableMessage $message): void;

    /**
     * Rendered by the response being returned now, and gone afterwards.
     *
     * @param string|TranslatableMessage $message see {@see self::flash()}
     */
    public function now(Severity $severity, string|TranslatableMessage $message): void;
}
