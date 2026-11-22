<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\VirtualModel;
use App\Models\VirtualModelReferenceImage;
use App\Services\Casting\CastingStorage;
use App\Services\Casting\ModelLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Virtual model library (/marketer/casting/models). */
class CastingModelController extends Controller
{
    public function __construct(private ModelLibraryService $lib) {}

    private const RULES = ['name' => ['required', 'string', 'max:120'], 'gender' => ['nullable', 'in:female,male'], 'age_range' => ['nullable', 'string', 'max:20'], 'appearance' => ['nullable', 'string', 'max:600'],
        'hairstyle' => ['nullable', 'string', 'max:200'], 'clothing_style' => ['nullable', 'string', 'max:300'], 'personality' => ['nullable', 'string', 'max:300'], 'ad_role' => ['nullable', 'string', 'max:200']];

    public function index(Request $r)
    {
        $status = in_array($r->query('status'), ['draft', 'approved', 'archived'], true) ? $r->query('status') : null;
        $q = VirtualModel::query()->with(['references' => fn ($q) => $q->where('kind', 'face')])->withCount('usages')->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'archived'));

        return view('marketer.casting.models.index', ['models' => $q->latest('id')->paginate(18)->withQueryString(), 'status' => $status]);
    }

    public function create()
    {
        return view('marketer.casting.models.form', ['m' => new VirtualModel]);
    }

    public function store(Request $r)
    {
        $m = $this->lib->create($r->validate(self::RULES), $r->user('marketer'));

        return redirect()->route('marketer.casting.models.show', $m->id);
    }

    public function show(int $id)
    {
        $m = VirtualModel::query()->with(['references', 'generations' => fn ($q) => $q->with('product:id,name')->limit(24), 'usages' => fn ($q) => $q->with('product:id,name')->limit(24)])->findOrFail($id);

        return view('marketer.casting.models.show', ['m' => $m, 'canApprove' => auth('marketer')->user()->can('ai_casting.approve')]);
    }

    public function update(Request $r, int $id)
    {
        $this->lib->update(VirtualModel::findOrFail($id), $r->validate(self::RULES));

        return redirect()->route('marketer.casting.models.show', $id);
    }

    public function duplicate(Request $r, int $id)
    {
        return redirect()->route('marketer.casting.models.show', $this->lib->duplicate(VirtualModel::findOrFail($id), $r->user('marketer'))->id);
    }

    public function archive(int $id)
    {
        $this->lib->archive(VirtualModel::findOrFail($id));

        return redirect()->route('marketer.casting.models.index');
    }

    public function restore(int $id)
    {
        $this->lib->restore(VirtualModel::withTrashed()->findOrFail($id));

        return redirect()->route('marketer.casting.models.show', $id);
    }

    public function approve(Request $r, int $id)
    {
        $this->lib->approve(VirtualModel::findOrFail($id), $r->user('marketer'));

        return redirect()->route('marketer.casting.models.show', $id);
    }

    public function addReference(Request $r, int $id): JsonResponse
    {
        $v = $r->validate(['file' => ['required', 'file', 'max:'.config('casting.upload.max_kb')], 'kind' => ['required', 'in:face,full_body,reference,pose']]);
        $ref = $this->lib->addReference(VirtualModel::findOrFail($id), $v['file'], $v['kind'], $r->user('marketer'));

        return response()->json(['id' => $ref->id], 201);
    }

    public function deleteReference(int $id, int $rid)
    {
        $this->lib->deleteReference(VirtualModelReferenceImage::query()->where('virtual_model_id', $id)->findOrFail($rid));

        return back();
    }

    public function referenceImage(int $id, int $rid): Response
    {
        $path = VirtualModelReferenceImage::query()->where('virtual_model_id', $id)->findOrFail($rid)->getRawOriginal('path');
        abort_unless(CastingStorage::disk()->exists($path), 404);

        return CastingStorage::disk()->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }
}
