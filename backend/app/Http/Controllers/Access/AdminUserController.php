<?php

namespace App\Http\Controllers\Access;

use App\DTOs\Access\AdminUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\AdminUserRequest;
use App\Http\Requests\Access\RoleRequest;
use App\Services\Access\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Staff accounts (admins) with role assignment, for both panels. */
class AdminUserController extends Controller implements HasMiddleware
{
    public function __construct(private AccessService $access) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:admins.view', only: ['index']),
            new Middleware('can:admins.create', only: ['create', 'store']),
            new Middleware('can:admins.edit', only: ['edit', 'update']),
            new Middleware('can:admins.delete', only: ['destroy']),
        ];
    }

    private function guard(Request $r): string
    {
        return RoleRequest::guardFor($r);
    }

    public function index(Request $request)
    {
        $g = $this->guard($request);

        return view('access.admins.index', ['guard' => $g, 'admins' => $this->access->adminQuery($g)->with('roles')->orderBy('id')->get(), 'access' => $this->access]);
    }

    public function create(Request $request)
    {
        return $this->form($request, $this->access->adminQuery($this->guard($request))->getModel());
    }

    public function edit(Request $request, int $id)
    {
        return $this->form($request, $this->access->findAdmin($this->guard($request), $id));
    }

    private function form(Request $request, $admin)
    {
        $g = $this->guard($request);

        return view('access.admins.form', [
            'guard' => $g, 'admin' => $admin, 'roles' => collect($this->access->roles($g))->pluck('name', 'id')->all(),
            'assigned' => $admin->exists ? $admin->roles->pluck('id')->all() : [],
        ]);
    }

    public function store(AdminUserRequest $request): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->saveAdmin($g, AdminUserData::fromRequest($request));

        return $this->ok(__('admin.saved'), route("$g.admins.index"));
    }

    public function update(AdminUserRequest $request, int $id): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->saveAdmin($g, AdminUserData::fromRequest($request), $this->access->findAdmin($g, $id));

        return $this->ok(__('admin.saved'), route("$g.admins.index"));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $g = $this->guard($request);
        $this->access->deleteAdmin($g, $this->access->findAdmin($g, $id));

        return $this->ok(__('admin.deleted'), route("$g.admins.index"));
    }

    public function validateForm(AdminUserRequest $request, ?int $id = null): JsonResponse
    {
        return $this->ok();
    }
}
