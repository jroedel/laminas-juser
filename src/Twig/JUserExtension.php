<?php

declare(strict_types=1);

namespace JUser\Twig;

use JUser\Host\UrlBuilderInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * What this module's templates are allowed to know about the application rendering them.
 *
 * Two things, and the reason there are only two is that everything else a template needs
 * — `translate()`, `csp_nonce()`, the `form_*` functions, the site chrome — is the
 * host's, already exists in the host, and would be worse for being reimplemented here.
 * See the module README for the full template contract.
 *
 * ## `juser_path()`
 *
 * Replaces `laminas_path()`, which the ten templates call between them ten times. The
 * name changes rather than being kept for compatibility, and that is the point: a
 * template calling `laminas_path()` is a template that only renders inside a host with
 * a laminas router, which is exactly the coupling 3.0.0 removes. A host wires
 * {@see UrlBuilderInterface} once and its router — laminas, Symfony or neither — stops
 * being visible from any template in this package.
 *
 * ## `juser_layout`
 *
 * A **global** rather than a function, because `{% extends %}` takes an expression and a
 * global is the plainest expression that can carry a per-host value. Every template here
 * hardcoded `'layout.html.twig'`, which is a file this package does not ship and cannot:
 * the chrome belongs to the site, and a package that shipped its own would render the
 * sign-in page looking like nothing else on it.
 *
 * The default keeps that literal, so a host whose layout is at the conventional path
 * configures nothing.
 *
 * What the named layout must provide is a `content` block and somewhere to render
 * messages. A layout with no message rendering makes {@see \JUser\Host\FlashInterface}
 * silent — every message written, none shown — which is a failure with no symptom other
 * than an absence, so it is worth checking first when a form appears to say nothing.
 */
final class JUserExtension extends AbstractExtension implements GlobalsInterface
{
    /** The path the ten templates named before it was configurable. */
    public const DEFAULT_LAYOUT = 'layout.html.twig';

    /**
     * @param string $layout the template `{% extends juser_layout %}` resolves to; must
     *        be resolvable by the same Twig environment this extension is registered on
     */
    public function __construct(
        private readonly UrlBuilderInterface $urls,
        private readonly string $layout = self::DEFAULT_LAYOUT
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('juser_path', $this->path(...)),
        ];
    }

    /** @return array<string, mixed> */
    public function getGlobals(): array
    {
        return ['juser_layout' => $this->layout];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $query
     */
    public function path(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->path($route, $params, $query);
    }
}
