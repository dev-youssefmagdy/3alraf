<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiContext;

/** Read-only, size-capped tool exposed to the model. */
interface AiTool
{
    public function name(): string;

    public function description(): string;

    /** JSON schema of the arguments */
    public function schema(): array;

    public function execute(array $arguments, AiContext $context): array;
}
