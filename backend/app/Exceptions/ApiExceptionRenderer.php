<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Renders every exception under /api/* in the {success,message,data,meta} envelope. Returns null (default handling) elsewhere. */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }
        $errors = null;
        if ($e instanceof ValidationException) {
            [$status, $message, $errors] = [422, $e->validator->errors()->first() ?: $e->getMessage(), $e->errors()];
        } elseif ($e instanceof AuthenticationException) {
            [$status, $message] = [401, __('store.api.unauthenticated')];
        } elseif ($e instanceof AuthorizationException) {
            [$status, $message] = [403, $e->getMessage() ?: __('store.api.forbidden')];
        } elseif ($e instanceof ModelNotFoundException) {
            [$status, $message] = [404, __('store.api.not_found')];
        } elseif ($e instanceof ThrottleRequestsException) {
            [$status, $message] = [429, __('store.api.too_many')];
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: ($status === 404 ? __('store.api.not_found') : __('store.api.server_error'));
        } elseif (config('app.debug')) {
            return null; // let Laravel show the debug payload
        } else {
            [$status, $message] = [500, __('store.api.server_error')];
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return response()->json(['success' => false, 'message' => $message] + ($errors !== null ? ['errors' => $errors] : []) + ['data' => null, 'meta' => (object) []], $status, $headers);
    }
}
