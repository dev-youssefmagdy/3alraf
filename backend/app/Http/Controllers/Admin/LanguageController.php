<?php

namespace App\Http\Controllers\Central;

use App\DataTables\LanguageDataTable;
use App\DTOs\LanguageData;
use App\Enums\TextDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\LanguageRequest;
use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LanguageController extends Controller implements HasMiddleware
{
    public function __construct(private LanguageService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:languages.view', only: ['index', 'datatable']),
            new Middleware('can:languages.create', only: ['create', 'store']),
            new Middleware('can:languages.edit', only: ['edit', 'update']),
            new Middleware('can:languages.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.languages.index', ['table' => new LanguageDataTable]);
    }

    public function datatable(LanguageDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.languages.form', ['language' => new Language(['is_active' => true, 'direction' => TextDirection::LTR]), 'directions' => TextDirection::options()]);
    }

    public function store(LanguageRequest $request): JsonResponse
    {
        $this->service->create(LanguageData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.languages.index'));
    }

    public function edit(Language $language)
    {
        return view('central.languages.form', ['language' => $language, 'directions' => TextDirection::options()]);
    }

    public function update(LanguageRequest $request, Language $language): JsonResponse
    {
        $this->service->update($language, LanguageData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.languages.index'));
    }

    public function destroy(Language $language): JsonResponse
    {
        $this->service->delete($language);

        return $this->ok(__('admin.deleted'));
    }

    /** AJAX pre-validation (same FormRequest rules as store/update). */
    public function validateForm(LanguageRequest $request, ?Language $language = null): JsonResponse
    {
        return $this->ok();
    }
}
