<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\StaticPageDataTable;
use App\DTOs\StaticPageData;
use App\Http\Requests\Admin\StaticPageRequest;
use App\Models\StaticPage;
use App\Services\StaticPageService;
use Illuminate\Database\Eloquent\Model;

class StaticPageController extends ResourceController
{
    protected static string $perm = 'static_pages';
    protected static string $routeBase = 'admin.static-pages';
    protected static string $view = 'admin.static-pages';
    protected static string $titleKey = 'growth.static_pages';
    protected static string $requestClass = StaticPageRequest::class;
    protected static string $dtoClass = StaticPageData::class;
    protected static string $tableClass = StaticPageDataTable::class;

    public function __construct(StaticPageService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new StaticPage(['is_active' => true]);
    }
}
