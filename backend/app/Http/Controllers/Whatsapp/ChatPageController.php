<?php

namespace App\Http\Controllers\Whatsapp;

use App\Events\Whatsapp\ChatBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Whatsapp\Concerns\ResolvesChatOwner;
use App\Models\Admin;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/** The WhatsApp inbox page; all data is loaded by the JSON chat API. */
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
        $user = $request->user($guard);
        $s = WhatsappSession::current();

        $admins = [];
        if ($user->can('whatsapp.chat.manage')) {
            $admins = Admin::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->filter(fn ($a) => $a->can('whatsapp.chat.view'))->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
        }

        return view('whatsapp.chat.index', [
            'guard' => $guard,
            'channel' => ChatBroadcast::channelName(),
            'base' => Str::beforeLast(route("$guard.whatsapp.chat.limits"), '/limits'),
            'connected' => (bool) ($s && $s->is_enabled && $s->isConnected()),
            'admins' => $admins,
            'meId' => $user->getKey(),
            'can' => [
                'send' => $user->can('whatsapp.chat.send'), 'manage' => $user->can('whatsapp.chat.manage'), 'media' => $user->can('whatsapp.chat.media'),
                'connection' => $user->can('whatsapp.view'),
            ],
            'customerUrl' => $user->can('customers.view') ? route('admin.customers.show', ['id' => '__ID__']) : null,
        ]);
    }
}
