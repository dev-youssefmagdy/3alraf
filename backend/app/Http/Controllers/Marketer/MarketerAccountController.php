<?php

namespace App\Http\Controllers\Marketer;

use App\DTOs\Marketing\MarketerAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MarketerAccountRequest;
use App\Models\Marketer;
use App\Services\Marketing\MarketerAccountService;
use App\Support\Marketing\MarketingPermissions;
use Illuminate\Http\JsonResponse;

/** Marketer panel → Team → Marketers: list (marketers.view), create / edit / delete (marketers.manage). */
class MarketerAccountController extends Controller
{
    public function __construct(private MarketerAccountService $accounts) {}

    public function index()
    {
        return view('marketer.accounts.index', ['marketers' => Marketer::query()->with('roles')->orderBy('id')->get()]);
    }

    public function create()
    {
        return $this->form(new Marketer(['is_active' => true]));
    }

    public function edit(int $id)
    {
        return $this->form(Marketer::query()->findOrFail($id));
    }

    private function form(Marketer $m)
    {
        return view('marketer.accounts.form', ['m' => $m, 'roles' => [MarketingPermissions::ROLE, MarketingPermissions::OWNER_ROLE],
            'role' => $m->exists ? ($m->isOwner() ? MarketingPermissions::OWNER_ROLE : MarketingPermissions::ROLE) : MarketingPermissions::ROLE]);
    }

    public function validateForm(MarketerAccountRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function store(MarketerAccountRequest $request): JsonResponse
    {
        $this->accounts->save(MarketerAccountData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('marketer.accounts.index'));
    }

    public function update(MarketerAccountRequest $request, int $id): JsonResponse
    {
        $this->accounts->save(MarketerAccountData::fromRequest($request), Marketer::query()->findOrFail($id));

        return $this->ok(__('admin.saved'), route('marketer.accounts.index'));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->accounts->delete(Marketer::query()->findOrFail($id));

        return $this->ok(__('admin.deleted'), route('marketer.accounts.index'));
    }
}
