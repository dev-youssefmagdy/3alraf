<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\BlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Blog: only active, already published posts. */
class BlogController extends ApiController
{
    /** GET blog?per_page=&page= (newest first; list items carry no `content`). */
    public function index(Request $request): JsonResponse
    {
        $p = BlogPost::query()->published()->latest('published_at')->latest('id')->paginate(max(1, min(30, (int) $request->query('per_page', 9))));

        return $this->page($p, BlogPostResource::class);
    }

    public function show(string $slug): JsonResponse
    {
        return $this->respond(new BlogPostResource(BlogPost::query()->published()->where('slug', $slug)->firstOrFail(), true));
    }
}
