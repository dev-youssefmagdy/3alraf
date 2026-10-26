<?php

use App\Models\BlogPost;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
});

it('storefront shows only active, published posts and sanitises content', function () {
    BlogPost::create(['slug' => 'live', 'title' => ['en' => 'Live post'], 'content' => ['en' => '<p>Hello</p><script>bad()</script>'], 'is_active' => true, 'published_at' => now()->subDay()]);
    BlogPost::create(['slug' => 'draft', 'title' => ['en' => 'Draft post'], 'is_active' => false]);
    BlogPost::create(['slug' => 'future', 'title' => ['en' => 'Future post'], 'is_active' => true, 'published_at' => now()->addDay()]);
    $this->get('http://egy.3alraf.test/en/blog')->assertOk()->assertSee('Live post')->assertDontSee('Draft post')->assertDontSee('Future post');
    $this->get('http://egy.3alraf.test/en/blog/live?lang=en')->assertOk()->assertSee('<p>Hello</p>', false)->assertDontSee('bad()');
    $this->get('http://egy.3alraf.test/en/blog/draft')->assertNotFound();
});

it('admin can open the blog list and create form', function () {
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get('http://egy.3alraf.test/admin/blog-posts')->assertOk();
    $this->get('http://egy.3alraf.test/admin/blog-posts/create')->assertOk();
    $this->postJson('http://egy.3alraf.test/admin/blog-posts', ['slug' => 'hello', 'title' => ['ar' => 'مرحبا', 'en' => 'Hello'], 'content' => ['ar' => 'x'], 'is_active' => 1])->assertSuccessful();
    expect(BlogPost::where('slug', 'hello')->first()?->published_at)->not->toBeNull();
});

it('footer links to the blog only when a published post exists', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('http://egy.3alraf.test/en')->assertOk()->assertDontSee('/en/blog');
    BlogPost::create(['slug' => 'f', 'title' => ['en' => 'F'], 'is_active' => true, 'published_at' => now()->subMinute()]);
    \Illuminate\Support\Facades\Cache::flush();
    $this->get('http://egy.3alraf.test/en')->assertOk()->assertSee('/en/blog', false);
});
