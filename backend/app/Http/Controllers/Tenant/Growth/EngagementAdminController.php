<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\DataTables\InteractionDataTable;
use App\DataTables\MissedOpportunityDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AdminLinkRequest;
use App\Http\Requests\Tenant\InteractionActionRequest;
use App\Http\Requests\Tenant\MissedUpdateRequest;
use App\Http\Requests\Tenant\ReplyLevelRequest;
use App\Http\Requests\Tenant\TaskUpdateRequest;
use App\Models\ImprovementTask;
use App\Models\Interaction;
use App\Models\ReputationAlert;
use App\Models\TenantAdmin;
use App\Models\TrackedLink;
use App\Services\Growth\InteractionService;
use App\Services\Growth\MissedOpportunityService;
use App\Services\Growth\ReputationService;
use App\Services\Growth\SuggestedReplyService;
use App\Services\Growth\TrackedLinkService;
use App\Services\Growth\VocService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Admin pages for N4 (each route is gated by growth.feature:{key} in routes/tenant_admin.php). Messages are never sent from here: replies are copied by a human. */
class EngagementAdminController extends Controller implements HasMiddleware
{
    public function __construct(private InteractionService $ix) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['inbox', 'inboxData', 'voc', 'reputation', 'missed', 'missedData']), new Middleware('can:growth.manage', except: ['inbox', 'inboxData', 'voc', 'reputation', 'missed', 'missedData'])];
    }

    public function inboxData(InteractionDataTable $table)
    {
        return $table->ajax();
    }

    public function inbox(TrackedLinkService $links, InteractionDataTable $table)
    {
        $tl = TrackedLink::orderByDesc('id')->limit(20)->get()->each(fn ($l) => [$l->stats = $links->stats($l), $l->short = $links->url($l)]);

        return view('tenant.growth.interactions', ['table' => $table, 'level' => $this->ix->replyLevel(), 'links' => $tl, 'unanswered' => $this->ix->unanswered()->count()]);
    }

    public function action(InteractionActionRequest $r, SuggestedReplyService $svc, int $id): JsonResponse
    {
        $a = $r->validated()['action'];
        if ($a === 'responded') {
            $this->ix->markResponded($id, auth('tenant')->id());
        } elseif ($a === 'close') {
            $this->ix->close($id, auth('tenant')->id());
        } else {
            $res = $svc->suggest(Interaction::findOrFail($id));

            return $this->ok($res['reply'] ?? __('growth_hub.inbox.no_reply', ['reason' => $res['reason'] ?? $res['mode']]), null, $res);
        }

        return $this->ok(__('admin.saved'), route('tenant.growth.interactions.index'));
    }

    public function level(ReplyLevelRequest $r): JsonResponse
    {
        $this->ix->setReplyLevel((int) $r->validated()['level'], auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.interactions.index'));
    }

    public function link(AdminLinkRequest $r, TrackedLinkService $svc): JsonResponse
    {
        $d = $r->validated();
        $l = $svc->create((int) $d['product_id'], $d['channel'] ?? null, null, $d['campaign'] ?? null);

        return $this->ok(__('growth_hub.links.created', ['url' => $svc->url($l)]), route('tenant.growth.interactions.index'));
    }

    public function voc(VocService $svc)
    {
        return view('tenant.growth.voc', ['data' => $svc->aggregate(30), 'tasks' => ImprovementTask::orderByRaw("FIELD(status,'open','doing','done','dismissed')")->orderBy('due_on')->limit(100)->get()]);
    }

    public function vocGenerate(VocService $svc): JsonResponse
    {
        $n = $svc->generateTasks();

        return $this->ok(__('growth_hub.voc.generated', ['n' => $n]), route('tenant.growth.voc.index'));
    }

    public function taskUpdate(TaskUpdateRequest $r, VocService $svc, int $id): JsonResponse
    {
        $svc->updateTask($id, $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.voc.index'));
    }

    public function reputation(ReputationService $svc)
    {
        return view('tenant.growth.reputation', ['open' => $svc->open(), 'closed' => ReputationAlert::where('status', 'resolved')->latest('id')->limit(20)->get()]);
    }

    public function reputationScan(ReputationService $svc): JsonResponse
    {
        return $this->ok(__('growth_hub.rep.scanned', ['n' => $svc->detect()]), route('tenant.growth.reputation.index'));
    }

    public function reputationResolve(ReputationService $svc, int $id): JsonResponse
    {
        $svc->resolve($id, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.reputation.index'));
    }

    public function missed(MissedOpportunityDataTable $table)
    {
        return view('tenant.growth.missed', ['table' => $table, 'owners' => TenantAdmin::where('is_active', true)->orderBy('name')->pluck('name')->all()]);
    }

    public function missedData(MissedOpportunityDataTable $table)
    {
        return $table->ajax();
    }

    public function missedStatus(Request $r, MissedOpportunityService $svc, int $id): JsonResponse
    {
        $svc->update($id, ['status' => (string) $r->validate(['status' => ['required', 'in:'.implode(',', MissedOpportunityService::STATUSES)]])['status']], auth('tenant')->id());

        return $this->ok(__('admin.saved'));
    }

    public function missedScan(MissedOpportunityService $svc): JsonResponse
    {
        return $this->ok(__('growth_hub.missed.scanned', ['n' => $svc->scan()]), route('tenant.growth.missed.index'));
    }

    public function missedUpdate(MissedUpdateRequest $r, MissedOpportunityService $svc, int $id): JsonResponse
    {
        $svc->update($id, $r->validated(), auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.missed.index'));
    }
}
