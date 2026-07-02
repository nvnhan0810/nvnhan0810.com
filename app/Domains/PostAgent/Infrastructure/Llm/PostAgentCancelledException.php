<?php

namespace App\Domains\PostAgent\Infrastructure\Llm;

use RuntimeException;

class PostAgentCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Đã hủy yêu cầu');
    }
}
