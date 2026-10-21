<?php

namespace App\Http\Requests\Qr;

use Illuminate\Foundation\Http\FormRequest;

/** ?variant= : central variant id (affiliate web/API) or tenant-variant row id (admin download). */
class QrDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['variant' => ['nullable', 'integer', 'min:1']];
    }

    public function variantId(): ?int
    {
        return $this->filled('variant') ? (int) $this->input('variant') : null;
    }
}
