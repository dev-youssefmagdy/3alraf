<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\CustomerDataTable;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerController extends Controller implements HasMiddleware
{
    public function __construct(private CustomerRepositoryInterface $customers) {}

    public static function middleware(): array
    {
        return [new Middleware('can:customers.view')];
    }

    public function index()
    {
        return view('tenant.crud.index', ['table' => new CustomerDataTable, 'title' => __('orders.customers'), 'route' => 'tenant.customers', 'perm' => 'customers']);
    }

    public function datatable(): JsonResponse
    {
        return (new CustomerDataTable)->ajax();
    }

    public function show(int $id)
    {
        $c = $this->customers->query()->with(['addresses.city', 'orders' => fn ($q) => $q->latest('id')->limit(20)])->findOrFail($id);

        return view('tenant.customers.show', ['customer' => $c]);
    }
}
