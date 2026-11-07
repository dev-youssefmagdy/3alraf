<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\ReviewDataTable;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReviewController extends Controller implements HasMiddleware
{
    public function __construct(private ReviewService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:reviews.view', only: ['index', 'datatable']),
            new Middleware('can:reviews.edit', only: ['approve', 'reject']),
            new Middleware('can:reviews.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('tenant.reviews.index', ['table' => new ReviewDataTable]);
    }

    public function datatable(): JsonResponse
    {
        return (new ReviewDataTable)->ajax();
    }

    public function approve(int $id): JsonResponse
    {
        $this->service->moderate($this->service->find($id), ReviewStatus::APPROVED);

        return $this->ok(__('growth.review_approved'));
    }

    public function reject(int $id): JsonResponse
    {
        $this->service->moderate($this->service->find($id), ReviewStatus::REJECTED);

        return $this->ok(__('growth.review_rejected'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($this->service->find($id));

        return $this->ok(__('admin.deleted'));
    }
}
