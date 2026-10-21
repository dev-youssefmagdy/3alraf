<?php

namespace App\Http\Controllers\Central;

use App\DTOs\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CategoryRequest;
use App\Models\Category;
use App\Models\Language;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    public function __construct(private CategoryService $service, private CategoryRepositoryInterface $categories) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:categories.view', only: ['index']),
            new Middleware('can:categories.create', only: ['create', 'store']),
            new Middleware('can:categories.edit', only: ['edit', 'update']),
            new Middleware('can:categories.delete', only: ['destroy']),
        ];
    }

    /** Tree UI (depth-first list with indentation, collapsible by CSS/JS). */
    public function index()
    {
        return view('central.categories.index', ['tree' => $this->categories->flatTree()]);
    }

    public function create(Request $request)
    {
        return view('central.categories.form', $this->formData(new Category(['is_active' => true, 'sort_order' => 0, 'parent_id' => $request->integer('parent') ?: null])));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $this->service->create(CategoryData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.categories.index'));
    }

    public function edit(Category $category)
    {
        return view('central.categories.form', $this->formData($category));
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $this->service->update($category, CategoryData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.categories.index'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return $this->ok(__('admin.deleted'), route('central.categories.index'));
    }

    public function validateForm(CategoryRequest $request, ?Category $category = null): JsonResponse
    {
        return $this->ok();
    }

    private function formData(Category $category): array
    {
        $parents = $this->categories->flatTree($category->exists ? $category->id : null)
            ->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', $c->depth).$c->name]);

        return ['category' => $category, 'languages' => Language::activeList(), 'parents' => $parents->all()];
    }
}
