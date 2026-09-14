<?php

namespace Tests\Unit\Modules\Shared\Infrastructure\Bus;

use Modules\Shared\Application\Query;
use Modules\Shared\Application\QueryHandler;
use Modules\Shared\Infrastructure\Bus\LaravelQueryBus;
use RuntimeException;
use Tests\TestCase;

final class LaravelQueryBusTest extends TestCase
{
    public function test_it_should_resolve_handler_and_return_result_when_query_is_registered(): void
    {
        $bus = new LaravelQueryBus($this->app);
        $bus->register(StubQuery::class, StubQueryHandler::class);

        $result = $bus->ask(new StubQuery(value: 'news'));

        $this->assertSame('handled:news', $result);
    }

    public function test_it_should_throw_when_query_has_no_handler(): void
    {
        $bus = new LaravelQueryBus($this->app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for query');

        $bus->ask(new StubQuery(value: 'missing'));
    }
}

final class StubQuery implements Query
{
    public function __construct(public readonly string $value) {}
}

final class StubQueryHandler implements QueryHandler
{
    public function handle(Query $query): mixed
    {
        assert($query instanceof StubQuery);

        return 'handled:'.$query->value;
    }
}
