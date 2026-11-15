<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder;

/** Blog: /{locale}/blog and /{locale}/blog/{slug}; only active, already published posts. */
class BlogController extends Controller
{
    public function index(string $locale)
    {
        return view('storefront.blog.index', ['posts' => $this->published()->latest('published_at')->paginate(9)]);
    }

    public function show(string $locale, string $slug)
    {
        $post = $this->published()->where('slug', $slug)->firstOrFail();

        // "more from the blog" row under the article: the latest other published posts
        return view('storefront.blog.show', ['post' => $post, 'related' => $this->published()->whereKeyNot($post->getKey())->latest('published_at')->limit(8)->get()]);
    }

    private function published(): Builder
    {
        return BlogPost::query()->published();
    }
}
