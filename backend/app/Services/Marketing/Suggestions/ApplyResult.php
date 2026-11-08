<?php

namespace App\Services\Marketing\Suggestions;

/** Outcome of a type handler's apply(): before / after values (stored on the execution), the entity created/changed, warnings. */
final readonly class ApplyResult
{
    public function __construct(public array $before, public array $after, public ?string $resultType = null, public ?int $resultId = null, public array $warnings = []) {}
}
