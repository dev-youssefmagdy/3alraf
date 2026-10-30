<?php

namespace App\Http\Controllers\Whatsapp;

use App\Events\Whatsapp\ChatBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Whatsapp\Concerns\ResolvesChatOwner;
use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/** The WhatsApp inbox page for BOTH panels; all data is loaded by the JSON chat API (owner from the route prefix). */
class ChatPageController extends Controller implements HasMiddleware
{
    use ResolvesChatOwner;

    public static function middleware(): array
    {
        return [new Middleware('can:whatsapp.chat.view')];
    }

    public function index(Request $request)
    {
        $guard = $this->guardName($request);
        $owner = $this->owner($request);
        $user = $request->user($guard);
        $s = WhatsappSession::where('tenant_id', $owner)->first();

        $admins = [];
        if ($user->can('whatsapp.chat.manage')) {
            $q = $guard === 'central'
                ? CentralAdmin::query()
                : TenantAdmin::withoutGlobalScopes()->where('tenant_id', $owner);
            $admins = $q->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->filter(fn ($a) => $a->can('whatsapp.chat.view'))->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
        }

        return view('whatsapp.chat.index', [
            'guard' => $guard,
            'channel' => ChatBroadcast::channelName($owner),
            'base' => Str::beforeLast(route("$guard.whatsapp.chat.limits"), '/limits'),
            'connected' => (bool) ($s && $s->is_enabled && $s->isConnected()),
            'admins' => $admins,
            'meId' => $user->getKey(),
            'can' => [
                'send' => $user->can('whatsapp.chat.send'), 'manage' => $user->can('whatsapp.chat.manage'), 'media' => $user->can('whatsapp.chat.media'),
                'connection' => $user->can('whatsapp.view'),
            ],
            'customerUrl' => $guard === 'tenant' && $user->can('customers.view') ? route('tenant.customers.show', ['id' => '__ID__']) : null,
        ]);
    }
}
