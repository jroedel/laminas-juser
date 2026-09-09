<?php

declare(strict_types=1);

namespace JUser\Service;

use Psr\Container\ContainerInterface;

/**
 * The container goes in whole, on purpose: {@see IdentityActingUserProvider} resolves the
 * identity on first use rather than at construction, and it must be able to discover that
 * there is none without that being an error here.
 */
class IdentityActingUserProviderFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName = IdentityActingUserProvider::class,
        ?array $options = null
    ): IdentityActingUserProvider {
        return new IdentityActingUserProvider($container);
    }
}
