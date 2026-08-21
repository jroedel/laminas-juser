<?php

declare(strict_types=1);

namespace JUser\Twig;

use JUser\Host\UrlBuilderInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

use function dirname;

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
     * The Twig namespace this module's own templates are addressed under — `@juser/…`.
     *
     * A namespace rather than a bare directory on the loader's search path, because a
     * host has templates of its own and `login.html.twig` is not a name anyone should
     * have to avoid. Every reference inside this module is fully qualified with it, so a
     * host registers the path once and nothing else can collide.
     */
    public const TEMPLATE_NAMESPACE = 'juser';

    /**
     * @param string $layout the template `{% extends juser_layout %}` resolves to; must
     *        be resolvable by the same Twig environment this extension is registered on
     * @param string|null $personTemplate a host template rendering one person record, for
     *        the user index's Person column; null means the column stays empty. See
     *        {@see getGlobals()}.
     */
    public function __construct(
        private readonly UrlBuilderInterface $urls,
        private readonly string $layout = self::DEFAULT_LAYOUT,
        private readonly ?string $personTemplate = null
    ) {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('juser_path', $this->path(...)),
        ];
    }

    /**
     * ## `juser_person_template`
     *
     * The user index has a Person column, and a person is the one thing on this surface
     * that is entirely the host's: this module has no person model, only
     * `JUser\Model\PersonValueOptionsProviderInterface` and whatever rows a host answers
     * it with. So the cell is rendered by a host template, included with the row as
     * `person`, and null — the default — renders the column empty for every account, which
     * is already what happens on a host that configures no provider.
     *
     * A template rather than a Twig function because formatting a person is markup: on the
     * application this came from it is a macro that draws a link and an edit pencil, and a
     * function returning pre-escaped HTML would be the same thing with worse failure modes.
     *
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return [
            'juser_layout'          => $this->layout,
            'juser_person_template' => $this->personTemplate,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $query
     */
    public function path(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->path($route, $params, $query);
    }

    /**
     * Where this module's templates live on disk, for a host wiring the loader:
     *
     *     $loader->addPath(JUserExtension::templatePath(), JUserExtension::TEMPLATE_NAMESPACE);
     *
     * A method rather than documentation, because the answer depends on where composer
     * put the package and a host should never have to spell that out.
     */
    public static function templatePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    /**
     * One of this module's templates, fully qualified: `template('login')` is
     * `@juser/login.html.twig`.
     *
     * Every reference in this module goes through here so that the namespace is named
     * once. A bare `'login.html.twig'` would resolve against the *host's* loader paths and
     * find whatever it found — which is a template that renders, not an error.
     */
    public static function template(string $name): string
    {
        return '@' . self::TEMPLATE_NAMESPACE . '/' . $name . '.html.twig';
    }
}
