<?php

namespace Tests\Unit\Modules\Shared\Infrastructure\Bus;

use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;
use Modules\Shared\Infrastructure\Bus\LaravelCommandBus;
use RuntimeException;
use Tests\TestCase;

final class LaravelCommandBusTest extends TestCase
{
    public function test_it_should_resolve_handler_and_return_result_when_command_is_registered(): void
    {
        $bus = new LaravelCommandBus($this->app);
        $bus->register(StubCommand::class, StubCommandHandler::class);

        $result = $bus->dispatch(new StubCommand(value: 'vote'));

        $this->assertSame('handled:vote', $result);
    }

    public function test_it_should_throw_when_command_has_no_handler(): void
    {
        $bus = new LaravelCommandBus($this->app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for command');

        $bus->dispatch(new StubCommand(value: 'missing'));
    }
}

final class StubCommand implements Command
{
    public function __construct(public readonly string $value) {}
}

final class StubCommandHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof StubCommand);

        return 'handled:'.$command->value;
    }
}
