<?php

namespace Modules\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandBus;
use Modules\Shared\Application\CommandHandler;
use RuntimeException;

final class LaravelCommandBus implements CommandBus
{
    /**
     * @param  array<class-string<Command>, class-string<CommandHandler>>  $map
     */
    public function __construct(
        private readonly Container $container,
        private array $map = [],
    ) {}

    /**
     * @param  class-string<Command>  $commandClass
     * @param  class-string<CommandHandler>  $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->map[$commandClass] = $handlerClass;
    }

    public function dispatch(Command $command): mixed
    {
        $handlerClass = $this->map[$command::class] ?? null;

        if ($handlerClass === null) {
            throw new RuntimeException(sprintf(
                'No handler registered for command [%s].',
                $command::class,
            ));
        }

        /** @var CommandHandler $handler */
        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }
}
