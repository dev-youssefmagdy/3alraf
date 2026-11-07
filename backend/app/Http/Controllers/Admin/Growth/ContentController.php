<?php

namespace App\Http\Controllers\Admin\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentCalendarRequest;
use App\Models\ContentAsset;
use App\Models\ContentCalendarItem;
use App\Models\Product;
use App\Services\Growth\ContentCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ContentController extends Controller implements HasMiddleware
{
    public function __construct(private ContentCalendarService $calendar) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index']), new Middleware('can:growth.manage', except: ['index'])];
    }

    public function index()
    {
        $names = Product::query()->whereNotNull('price')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all();

        return view('admin.growth.content', [
            'assets' => ContentAsset::orderByDesc('id')->limit(200)->get(), 'names' => $names, 'channels' => ContentCalendarService::CHANNELS,
            'items' => ContentCalendarItem::with('asset')->orderBy('scheduled_for')->limit(200)->get(),
        ]);
    }

    public function schedule(ContentCalendarRequest $r): JsonResponse
    {
        $d = $r->validated();
        $this->calendar->schedule((int) $d['content_asset_id'], $d['channel'], $d['scheduled_for'], $d['note'] ?? null, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.content.index'));
    }

    public function publish(int $id): JsonResponse
    {
        $this->calendar->markPublished($id, auth('admin')->id());

        return $this->ok(__('growth_hub.content.marked'), route('admin.growth.content.index'));
    }

    public function skip(int $id): JsonResponse
    {
        $this->calendar->skip($id, auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.growth.content.index'));
    }
}
