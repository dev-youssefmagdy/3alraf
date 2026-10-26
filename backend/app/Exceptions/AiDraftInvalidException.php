<?php

namespace App\Exceptions;

/** A generator draft broke a hard rule (margin floor, stock, dates...). $errors = [{code, params}]. */
class AiDraftInvalidException extends AiException
{
    /** @param array<int,array{code:string,params?:array}> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(__('ai.gen.blocked'), 'blocked');
    }
}
