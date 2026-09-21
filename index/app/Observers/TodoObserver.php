<?php

namespace App\Observers;

use App\Models\Todo;
use Modules\Todo\Domain\MatrixStreamVersion;

class TodoObserver
{
    public function saved(Todo $todo): void
    {
        MatrixStreamVersion::bump();
    }

    public function deleted(Todo $todo): void
    {
        MatrixStreamVersion::bump();
    }

    public function restored(Todo $todo): void
    {
        MatrixStreamVersion::bump();
    }

    public function forceDeleted(Todo $todo): void
    {
        MatrixStreamVersion::bump();
    }
}
