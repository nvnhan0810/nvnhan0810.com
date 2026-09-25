<?php

namespace Modules\Blog\Application\Handler;

use Modules\Blog\Application\Command\DeletePost;
use Modules\Blog\Domain\Exceptions\PostNotFoundException;
use Modules\Blog\Domain\Ports\PostRepository;
use Modules\Shared\Application\Command;
use Modules\Shared\Application\CommandHandler;

final class DeletePostHandler implements CommandHandler
{
    public function __construct(
        private readonly PostRepository $posts,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof DeletePost);

        if ($this->posts->findById($command->id) === null) {
            throw new PostNotFoundException($command->id);
        }

        $this->posts->delete($command->id);

        return null;
    }
}
