<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /** Standard AJAX success payload consumed by ajax-form.js */
    protected function ok(?string $message = null, ?string $redirect = null, array $data = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true, 'message' => $message, 'redirect' => $redirect, 'data' => $data ?: null,
        ], fn ($v) => $v !== null));
    }
}
