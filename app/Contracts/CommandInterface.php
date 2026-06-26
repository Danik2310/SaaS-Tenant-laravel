<?php

declare(strict_types=1);

namespace App\Contracts;

interface CommandInterface
{
    public function execute(): mixed;

    public function rollback(): void;
}
