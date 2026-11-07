<?php

use App\Models\BlogPost;
use App\Models\Admin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
});

it('storefront shows only active, published posts and sanitises content', function () {
    BlogPost::create(['slug' => 'live', 'title' => ['en' => 'Live post'], 'content' => ['en' => '<p>Hello</p><script>bad()</script>'], 'is_active' => true, 'published_at' => now()->subDay()]);
    BlogPost::create(['slug' => 'draft', 'title' => ['en' => 'Draft post'], 'is_active' => false]);
    BlogPost::create(['slug' => 'future', 'title' => ['en' => 'Future post'], 'is_active' => true, 'published_at' => now()->addDay()]);
    $this->get('/en/blog')->assertOk()->assertSee('Live post')->assertDontSee('Draft post')->assertDontSee('Future post');
    $this->get('/en/blog/live?lang=en')->assertOk()->assertSee('<p>Hello</p>', false)->assertDontSee('bad()');
    $this->get('/en/blog/draft')->assertNotFound();
});

it('admin can open the blog list and create form', function () {
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->get('/admin/blog-posts')->assertOk();
    $this->get('/admin/blog-posts/create')->assertOk();
    $this->postJson('/admin/blog-posts', ['slug' => 'hello', 'title' => ['ar' => 'مرحبا', 'en' => 'Hello'], 'content' => ['ar' => 'x'], 'is_active' => 1])->assertSuccessful();
    expect(BlogPost::where('slug', 'hello')->first()?->published_at)->not->toBeNull();
});

it('footer links to the blog only when a published post exists', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('/en')->assertOk()->assertDontSee('/en/blog');
    BlogPost::create(['slug' => 'f', 'title' => ['en' => 'F'], 'is_active' => true, 'published_at' => now()->subMinute()]);
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('/en')->assertOk()->assertSee('/en/blog', false);
});
