<?php

namespace App\Http\Controllers\Marketer;

use App\DataTables\MarketingSuggestionApprovalDataTable;
use App\DataTables\MarketingSuggestionDataTable;
use App\DataTables\MarketingSuggestionExecutionDataTable;
use App\DTOs\Marketing\SuggestionEditData;
use App\Enums\Marketing\SuggestionExecutionStatus;
use App\Enums\Marketing\SuggestionStatus;
use App\Enums\Marketing\SuggestionTypeKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\SuggestionActionRequest;
use App\Http\Requests\Marketer\SuggestionEditRequest;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionActivityLog;
use App\Models\MarketingTheme;
use App\Repositories\Contracts\MarketingSuggestionRepositoryInterface;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Marketer panel → Suggestions Center (P5, MCP.md §16.2 / §17.1 / §24). Pages: overview, one page per type group, detail (compare, preview,
 * history), edit, execution & approval log. Actions answer JSON for the page script; every one goes through SuggestionEngine
 * (permissions per route + the type's own approve permission inside the engine).
 */
class SuggestionController extends Controller
{
    public function __construct(private SuggestionEngine $engine, private MarketingSuggestionRepositoryInterface $repo, private ThemeService $themes) {}

    public function index(MarketingSuggestionDataTable $table)
    {
        return $this->listing('overview', $table);
    }

    public function page(Request $r, MarketingSuggestionDataTable $table)
    {
        return $this->listing((string) $r->route()->defaults['page'], $table);
    }

    private function listing(string $page, MarketingSuggestionDataTable $table)
    {
        $types = $page === 'overview' ? [] : array_map(fn ($t) => $t->value, SuggestionTypeKey::forPage($page));
        $counts = $this->repo->counts($types);
        $data = ['page' => $page, 'types' => $types, 'counts' => $counts, 'table' => $table,
            'dtUrl' => route('marketer.suggestions.datatable', array_filter(['page' => $page === 'overview' ? null : $page]))];
        if ($page === 'overview') {
            $data['recent'] = MarketingSuggestionActivityLog::query()->with('suggestion:id,title,type')->latest('id')->limit(12)->get();
        }
        if ($page === 'themes') {
            $data['themeVersions'] = MarketingTheme::query()->latest('id')->limit(30)->get();
            $data['activeTheme'] = $this->themes->active();
        }

        return view('marketer.suggestions.index', $data);
    }

    public function datatable(MarketingSuggestionDataTable $table)
    {
        return $table->ajax();
    }

    public function show(int $id)
    {
        $s = $this->repo->findOrFail($id);
        $h = $this->engine->registry()->get($s->type);
        $check = $this->engine->warnings($s);
        $admin = auth('admin')->user();
        $can = fn (string $base) => collect($this->engine->permissionsFor($s, $base))->every(fn ($p) => $admin->can($p));

        return view('marketer.suggestions.show', [
            's' => $s, 'handler' => $h, 'compare' => $this->engine->compare($s), 'check' => $check, 'target' => $this->engine->target($s),
            'stale' => in_array($s->status, [SuggestionStatus::APPROVED, SuggestionStatus::FAILED, SuggestionStatus::PENDING, SuggestionStatus::UNDER_REVIEW, SuggestionStatus::EDITED], true) ? $this->engine->staleness($s) : null,
            'approval' => $s->validApproval(), 'revisions' => $s->revisions()->get(), 'approvals' => $s->approvals()->with('admin:id,name')->get(),
            'executions' => $s->executions()->with('executor:id,name')->get(), 'activity' => $s->activity()->limit(100)->get(),
            'contrast' => $s->type === SuggestionTypeKey::SEASONAL_THEME ? $this->themes->contrast((array) ($s->payload['tokens'] ?? [])) : null,
            'palette' => $s->type === SuggestionTypeKey::SEASONAL_THEME ? $this->themes->palette((array) ($s->payload['tokens'] ?? [])) : null,
            'can' => ['edit' => $admin->can('ai_suggestions.edit'), 'approve' => $can('ai_suggestions.approve'), 'reject' => $admin->can('ai_suggestions.reject'),
                'apply' => $can('ai_suggestions.apply'), 'archive' => $admin->can('ai_suggestions.archive')],
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function edit(int $id)
    {
        $s = $this->repo->findOrFail($id);

        return view('marketer.suggestions.edit', ['s' => $s, 'fields' => $this->engine->registry()->get($s->type)->editFields($s->target_entity_type)]);
    }

    public function validateEdit(SuggestionEditRequest $r): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function update(SuggestionEditRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            [$s] = $this->engine->update($this->repo->findOrFail($id), SuggestionEditData::fromArray($r->editData()), SuggestionActor::admin($r->user('admin')));

            return $this->ok(__('marketing.suggestions.done.saved', ['v' => $s->version]), route('marketer.suggestions.show', $s->id));
        });
    }

    public function review(int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->startReview($this->repo->findOrFail($id), auth('admin')->user()) ? $this->ok(__('marketing.suggestions.done.review')) : null);
    }

    public function approve(SuggestionActionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->approve($this->repo->findOrFail($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.suggestions.done.approved')) : null);
    }

    public function reject(SuggestionActionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->reject($this->repo->findOrFail($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.suggestions.done.rejected')) : null);
    }

    public function archive(int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->archive($this->repo->findOrFail($id), auth('admin')->user()) ? $this->ok(__('marketing.suggestions.done.archived')) : null);
    }

    public function resnapshot(int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->resnapshot($this->repo->findOrFail($id), auth('admin')->user()) ? $this->ok(__('marketing.suggestions.done.resnapshot')) : null);
    }

    public function apply(SuggestionActionRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $exec = $this->engine->apply($this->repo->findOrFail($id), $r->user('admin'), $r->input('idempotency_key'), (array) $r->input('options', []));
            if ($exec->status === SuggestionExecutionStatus::FAILED) {
                return response()->json(['success' => false, 'message' => __('marketing.suggestions.done.apply_failed', ['error' => Str::limit((string) $exec->error, 300)]),
                    'data' => ['execution_id' => $exec->id]], 422);
            }

            return $this->ok(__('marketing.suggestions.done.applied'), null, ['execution_id' => $exec->id]);
        });
    }

    public function rollback(int $id): JsonResponse
    {
        return $this->run(fn () => $this->engine->rollback($this->repo->findOrFail($id), auth('admin')->user()) ? $this->ok(__('marketing.suggestions.done.rolled_back')) : null);
    }

    public function publish(SuggestionActionRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $at = $r->filled('publish_at') ? \Carbon\CarbonImmutable::parse((string) $r->input('publish_at')) : null;
            $this->engine->publishBlog($this->repo->findOrFail($id), $r->user('admin'), $at, $r->input('idempotency_key'));

            return $this->ok(__('marketing.suggestions.done.'.($at ? 'scheduled' : 'published')));
        });
    }

    /** Seasonal theme: open the storefront with the proposed colours (signed URL, admins with ai_suggestions.view only). */
    public function preview(int $id): RedirectResponse
    {
        $s = $this->repo->findOrFail($id);
        abort_unless($s->type === SuggestionTypeKey::SEASONAL_THEME, 404);

        return redirect()->away($this->themes->previewUrl($s, (string) (session('admin_locale') ?: setting('default_locale', 'ar'))));
    }

    public function log(MarketingSuggestionExecutionDataTable $executions, MarketingSuggestionApprovalDataTable $approvals)
    {
        return view('marketer.suggestions.log', ['executions' => $executions, 'approvals' => $approvals]);
    }

    public function logExecutions(MarketingSuggestionExecutionDataTable $t)
    {
        return $t->ajax();
    }

    public function logApprovals(MarketingSuggestionApprovalDataTable $t)
    {
        return $t->ajax();
    }

    // themes (perm ai_theme.approve on the routes)
    public function themeActivate(int $id): JsonResponse
    {
        MarketingTheme::query()->findOrFail($id);
        $this->themes->activateVersion($id, auth('admin')->id());

        return $this->ok(__('marketing.suggestions.done.theme'));
    }

    public function themeRevert(): JsonResponse
    {
        $this->themes->revert(auth('admin')->id());

        return $this->ok(__('marketing.suggestions.done.theme'));
    }

    public function themeOff(): JsonResponse
    {
        $this->themes->activateVersion(null, auth('admin')->id());

        return $this->ok(__('marketing.suggestions.done.theme'));
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (SuggestionException $e) {
            return response()->json(['success' => false, 'error' => $e->errorCode, 'message' => $e->getMessage(), 'errors' => $e->errors ?: null, 'data' => $e->data ?: null], $e->status);
        }
    }
}
