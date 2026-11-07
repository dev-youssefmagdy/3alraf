<?php

namespace App\Http\Controllers\Central;

use App\DataTables\AttributeDataTable;
use App\DTOs\AttributeData;
use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\AttributeRequest;
use App\Models\Attribute;
use App\Models\Language;
use App\Services\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AttributeController extends Controller implements HasMiddleware
{
    public function __construct(private AttributeService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:attributes.view', only: ['index', 'datatable']),
            new Middleware('can:attributes.create', only: ['create', 'store']),
            new Middleware('can:attributes.edit', only: ['edit', 'update']),
            new Middleware('can:attributes.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.attributes.index', ['table' => new AttributeDataTable]);
    }

    public function datatable(AttributeDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.attributes.form', $this->formData(new Attribute(['type' => AttributeType::SELECT, 'sort_order' => 0])));
    }

    public function store(AttributeRequest $request): JsonResponse
    {
        $this->service->create(AttributeData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.attributes.index'));
    }

    public function edit(Attribute $attribute)
    {
        return view('central.attributes.form', $this->formData($attribute->load('values')));
    }

    public function update(AttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $this->service->update($attribute, AttributeData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.attributes.index'));
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $this->service->delete($attribute);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(AttributeRequest $request, ?Attribute $attribute = null): JsonResponse
    {
        return $this->ok();
    }

    private function formData(Attribute $attribute): array
    {
        return ['attribute' => $attribute, 'languages' => Language::activeList(), 'types' => AttributeType::options()];
    }
}
