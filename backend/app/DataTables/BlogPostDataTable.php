<?php

namespace App\DataTables;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class BlogPostDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return BlogPost::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'title_label', 'title' => __('growth.fields.title'), 'orderable' => false],
            ['data' => 'slug', 'title' => __('admin.fields.slug_short')],
            ['data' => 'published_at', 'title' => __('growth.fields.published_at')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('title_label', fn (BlogPost $m) => e($m->title))
            ->filterColumn('title_label', fn ($q, $k) => $q->where('title', 'like', "%$k%"))
            ->editColumn('published_at', fn (BlogPost $m) => $m->published_at?->format('Y-m-d H:i'))
            ->addColumn('status', fn (BlogPost $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (BlogPost $m) => $this->tenantActions($m, 'tenant.blog-posts', 'blog_posts'));
    }

    protected function rawColumns(): array
    {
        return ['title_label', 'status', 'actions'];
    }
}
