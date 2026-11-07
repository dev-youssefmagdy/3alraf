<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ContentCalendarRequest;
use App\Models\ContentAsset;
use App\Models\ContentCalendarItem;
use App\Models\TenantProduct;
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
        $names = TenantProduct::with('product')->get()->mapWithKeys(fn ($t) => [$t->product_id => $t->product?->name])->all();

        return view('tenant.growth.content', [
            'assets' => ContentAsset::orderByDesc('id')->limit(200)->get(), 'names' => $names, 'channels' => ContentCalendarService::CHANNELS,
            'items' => ContentCalendarItem::with('asset')->orderBy('scheduled_for')->limit(200)->get(),
        ]);
    }

    public function schedule(ContentCalendarRequest $r): JsonResponse
    {
        $d = $r->validated();
        $this->calendar->schedule((int) $d['content_asset_id'], $d['channel'], $d['scheduled_for'], $d['note'] ?? null, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.content.index'));
    }

    public function publish(int $id): JsonResponse
    {
        $this->calendar->markPublished($id, auth('tenant')->id());

        return $this->ok(__('growth_hub.content.marked'), route('tenant.growth.content.index'));
    }

    public function skip(int $id): JsonResponse
    {
        $this->calendar->skip($id, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.content.index'));
    }
}
