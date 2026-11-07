<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Generic tenant CRUD controller (index/datatable/create/store/edit/update/destroy/validate).
 * Subclasses set the static config below and pass their service. Route param is `{id}` (tenant scope applies via model scope).
 * Requests are resolved from the container so each module keeps its own FormRequest + DTO.
 */
abstract class ResourceController extends Controller implements HasMiddleware
{
    protected static string $perm;          // e.g. 'cities'  -> can:cities.view ...
    protected static string $routeBase;     // e.g. 'tenant.cities'
    protected static string $view;          // e.g. 'tenant.cities'  (needs form.blade.php; index is generic)
    protected static string $titleKey;      // lang key of the page title
    protected static string $requestClass;
    protected static string $dtoClass;
    protected static string $tableClass;

    public function __construct(protected CrudService $service) {}

    public static function middleware(): array
    {
        $p = static::$perm;

        return [
            new Middleware("can:$p.view", only: ['index', 'datatable']),
            new Middleware("can:$p.create", only: ['create', 'store']),
            new Middleware("can:$p.edit", only: ['edit', 'update']),
            new Middleware("can:$p.delete", only: ['destroy']),
        ];
    }

    /** Fresh model for the create form. */
    abstract protected function blank(): \Illuminate\Database\Eloquent\Model;

    /** Extra data for form view. */
    protected function formData(\Illuminate\Database\Eloquent\Model $model): array
    {
        return [];
    }

    public function index()
    {
        return view('tenant.crud.index', ['table' => new (static::$tableClass), 'title' => __(static::$titleKey), 'route' => static::$routeBase, 'perm' => static::$perm]);
    }

    public function datatable(): JsonResponse
    {
        return (new (static::$tableClass))->ajax();
    }

    public function create()
    {
        return $this->form($this->blank());
    }

    public function edit(int|string $id)
    {
        return $this->form($this->service->find($id));
    }

    public function store(): JsonResponse
    {
        $dto = (static::$dtoClass)::fromRequest(app(static::$requestClass));
        $this->service->create($dto);

        return $this->ok(__('admin.saved'), route(static::$routeBase.'.index'));
    }

    public function update(int|string $id): JsonResponse
    {
        $model = $this->service->find($id);
        $dto = (static::$dtoClass)::fromRequest(app(static::$requestClass));
        $this->service->update($model, $dto);

        return $this->ok(__('admin.saved'), route(static::$routeBase.'.index'));
    }

    public function destroy(int|string $id): JsonResponse
    {
        $this->service->delete($this->service->find($id));

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(int|string|null $id = null): JsonResponse
    {
        app(static::$requestClass); // validates on resolve

        return $this->ok();
    }

    private function form($model)
    {
        return view(static::$view.'.form', ['model' => $model, 'editing' => $model->exists, 'route' => static::$routeBase, 'languages' => \App\Models\Language::activeList()] + $this->formData($model));
    }
}
