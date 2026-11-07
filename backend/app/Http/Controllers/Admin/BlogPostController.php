<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\BlogPostDataTable;
use App\DTOs\BlogPostData;
use App\Http\Requests\Tenant\BlogPostRequest;
use App\Models\BlogPost;
use App\Services\BlogPostService;
use Illuminate\Database\Eloquent\Model;

class BlogPostController extends ResourceController
{
    protected static string $perm = 'blog_posts';
    protected static string $routeBase = 'tenant.blog-posts';
    protected static string $view = 'tenant.blog-posts';
    protected static string $titleKey = 'growth.blog_posts';
    protected static string $requestClass = BlogPostRequest::class;
    protected static string $dtoClass = BlogPostData::class;
    protected static string $tableClass = BlogPostDataTable::class;

    public function __construct(BlogPostService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new BlogPost(['is_active' => true]);
    }
}
