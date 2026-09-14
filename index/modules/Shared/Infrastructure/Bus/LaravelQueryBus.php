<?php

namespace Modules\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryBus;
use Modules\Shared\Application\QueryHandler;
use RuntimeException;

final class LaravelQueryBus implements QueryBus
{
    /**
     * @param  array<class-string<Query>, class-string<QueryHandler>>  $map
     */
    public function __construct(
        private readonly Container $container,
        private array $map = [],
    ) {}

    /**
     * @param  class-string<Query>  $queryClass
     * @param  class-string<QueryHandler>  $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->map[$queryClass] = $handlerClass;
    }

    public function ask(Query $query): mixed
    {
        $handlerClass = $this->map[$query::class] ?? null;

        if ($handlerClass === null) {
            throw new RuntimeException(sprintf(
                'No handler registered for query [%s].',
                $query::class,
            ));
        }

        /** @var QueryHandler $handler */
        $handler = $this->container->make($handlerClass);

        return $handler->handle($query);
    }
}
