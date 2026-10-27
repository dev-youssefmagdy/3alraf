<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AmbassadorEnrollRequest;
use App\Http\Requests\Tenant\AmbassadorIdeaRequest;
use App\Http\Requests\Tenant\AmbassadorUpdateRequest;
use App\Models\Affiliate;
use App\Models\Ambassador;
use App\Models\AmbassadorIdea;
use App\Services\Growth\AmbassadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** N5 Ambassador program admin (gated by growth.feature:ambassadors). */
class AmbassadorAdminController extends Controller implements HasMiddleware
{
    public function __construct(private AmbassadorService $svc) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['index']), new Middleware('can:growth.manage', except: ['index'])];
    }

    public function index()
    {
        $to = now();
        $from = now()->subDays(29);
        $rows = Ambassador::with('affiliate')->orderByDesc('id')->get()->each(fn ($a) => $a->report = $this->svc->report($a, $from, $to));

        return view('tenant.growth.ambassadors', ['rows' => $rows, 'ideas' => AmbassadorIdea::orderByDesc('id')->get(), 'affiliates' => Affiliate::where('status', AffiliateStatus::APPROVED->value)->orderBy('name')->get(['id', 'name', 'email'])]);
    }

    public function enroll(AmbassadorEnrollRequest $r): JsonResponse
    {
        $this->svc->enroll((int) $r->validated()['affiliate_id'], $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.ambassadors.index'));
    }

    public function update(AmbassadorUpdateRequest $r, int $id): JsonResponse
    {
        $this->svc->update(Ambassador::findOrFail($id), $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.ambassadors.index'));
    }

    public function ideaStore(AmbassadorIdeaRequest $r): JsonResponse
    {
        $d = $r->validated();
        $this->svc->assertClean('body', $d['body']);
        $this->svc->assertClean('title', $d['title']);
        AmbassadorIdea::create($d);

        return $this->ok(__('admin.saved'), route('tenant.growth.ambassadors.index'));
    }

    public function ideaDestroy(int $id): JsonResponse
    {
        AmbassadorIdea::findOrFail($id)->delete();

        return $this->ok(__('admin.deleted'), route('tenant.growth.ambassadors.index'));
    }
}
