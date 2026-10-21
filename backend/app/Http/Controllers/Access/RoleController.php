<?php

namespace App\Http\Controllers\Access;

use App\DTOs\Access\RoleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Role;

/** Roles + permission matrix for the central and tenant admin panels (guard from the route-name prefix). */
class RoleController extends Controller implements HasMiddleware
{
    public function __construct(private AccessService $access) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.view', only: ['index']),
            new Middleware('can:roles.create', only: ['create', 'store']),
            new Middleware('can:roles.edit', only: ['edit', 'update']),
            new Middleware('can:roles.delete', only: ['destroy']),
        ];
    }

    private function guard(Request $r): string
    {
        return RoleRequest::guardFor($r);
    }

    public function index(Request $request)
    {
        $g = $this->guard($request);

        return view('access.roles.index', ['guard' => $g, 'roles' => $this->access->roles($g)]);
    }

    public function create(Request $request)
    {
        return $this->form($request, new Role);
    }

    public function edit(Request $request, int $id)
    {
        return $this->form($request, $this->access->findRole($this->guard($request), $id));
    }

    private function form(Request $request, Role $role)
    {
        $g = $this->guard($request);
        abort_if($role->exists && $this->access->isSystem($g, $role), 403);

        return view('access.roles.form', [
            'guard' => $g, 'role' => $role, 'name' => $role->exists ? $this->access->displayName($g, $role) : '',
            'matrix' => $this->access->matrix($g), 'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
        ]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->saveRole($g, RoleData::fromRequest($request));

        return $this->ok(__('admin.saved'), route("$g.roles.index"));
    }

    public function update(RoleRequest $request, int $id): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->saveRole($g, RoleData::fromRequest($request), $this->access->findRole($g, $id));

        return $this->ok(__('admin.saved'), route("$g.roles.index"));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->deleteRole($g, $this->access->findRole($g, $id));

        return $this->ok(__('admin.deleted'), route("$g.roles.index"));
    }

    public function validateForm(RoleRequest $request, ?int $id = null): JsonResponse
    {
        return $this->ok();
    }
}
