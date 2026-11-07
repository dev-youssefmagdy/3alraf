<?php

namespace App\Http\Controllers\Central;

use App\DataTables\TagDataTable;
use App\DTOs\TagData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\TagRequest;
use App\Models\Tag;
use App\Models\Language;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TagController extends Controller implements HasMiddleware
{
    public function __construct(private TagService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:tags.view', only: ['index', 'datatable']),
            new Middleware('can:tags.create', only: ['create', 'store']),
            new Middleware('can:tags.edit', only: ['edit', 'update']),
            new Middleware('can:tags.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.tags.index', ['table' => new TagDataTable]);
    }

    public function datatable(TagDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('central.tags.form', ['tag' => new Tag(['is_active' => true, 'sort_order' => 0]), 'languages' => Language::activeList()]);
    }

    public function store(TagRequest $request): JsonResponse
    {
        $this->service->create(TagData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.tags.index'));
    }

    public function edit(Tag $tag)
    {
        return view('central.tags.form', ['tag' => $tag, 'languages' => Language::activeList()]);
    }

    public function update(TagRequest $request, Tag $tag): JsonResponse
    {
        $this->service->update($tag, TagData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.tags.index'));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->service->delete($tag);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(TagRequest $request, ?Tag $tag = null): JsonResponse
    {
        return $this->ok();
    }
}
