<?php

namespace Modules\Blog\Domain\Exceptions;

use RuntimeException;

final class PostNotFoundException extends RuntimeException
{
    public function __construct(int $postId)
    {
        parent::__construct(sprintf('Post [%d] was not found.', $postId));
    }
}
