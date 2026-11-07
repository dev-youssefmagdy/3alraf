<?php

use App\Events\Whatsapp\ChatConversationUpdated;
use App\Events\Whatsapp\ChatMessageReceived;
use App\Events\Whatsapp\ChatMessageUpdated;
use App\Events\Whatsapp\ChatPresence;
use App\Models\Admin;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappReaction;
use App\Services\Whatsapp\Chat\ChatIngestService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'tok', 'callback_secret' => 'sekret'], 'whatsapp_chat.disk' => 'chat-test']);
    Storage::fake('chat-test');
    $this->ing = app(ChatIngestService::class);
});

function inMsg(array $o = []): array
{
    return $o + ['id' => 'MSG1', 'jid' => '201001112222@s.whatsapp.net', 'jid_alt' => null, 'phone' => '201001112222', 'from_me' => false, 'timestamp' => 1790000000, 'push_name' => 'Ali',
        'type' => 'text', 'body' => 'hello', 'caption' => null, 'payload' => null, 'quoted' => null, 'media' => null];
}

function signedPost(string $sid, string $type, array $data)
{
    $body = json_encode(['session_id' => $sid, 'type' => $type, 'data' => $data, 'at' => now()->toIso8601String()]);
    $ts = (string) time();

    return test()->call('POST', '/internal/whatsapp/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WA_TIMESTAMP' => $ts,
        'HTTP_X_WA_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$body, 'sekret'),
    ], $body);
}

it('stores a text message, upserts the conversation and broadcasts after ingest', function () {
    Event::fake([ChatMessageReceived::class]);
    signedPost('central', 'message.in', inMsg())->assertOk();
    $c = WhatsappConversation::query()->first();
    expect($c->jid)->toBe('201001112222@s.whatsapp.net')->and($c->push_name)->toBe('Ali')->and($c->unread_count)->toBe(1)->and($c->last_message_preview)->toBe('hello');
    $m = WhatsappMessage::query()->first();
    expect($m->direction)->toBe('in')->and($m->body)->toBe('hello')->and($m->status)->toBe('delivered');
    Event::assertDispatched(ChatMessageReceived::class, fn ($e) => $e->message['wa_message_id'] === 'MSG1' && $e->conversation['unread_count'] === 1);
});

it('is idempotent: replays create nothing and do not re-broadcast or bump unread', function () {
    Event::fake([ChatMessageReceived::class]);
    signedPost('central', 'message.in', inMsg())->assertOk();
    signedPost('central', 'message.in', inMsg())->assertOk();
    expect(WhatsappMessage::query()->count())->toBe(1)->and(WhatsappConversation::query()->first()->unread_count)->toBe(1);
    Event::assertDispatchedTimes(ChatMessageReceived::class, 1);
});

it('keeps lid chats keyed by jid with null phone and merges via jid_alt', function () {
    $this->ing->handle('message.in', inMsg(['id' => 'A', 'jid' => '9988@lid', 'phone' => null]));
    expect(WhatsappConversation::query()->first()->phone)->toBeNull();
    $this->ing->handle('message.in', inMsg(['id' => 'B', 'jid' => '201001112222@s.whatsapp.net', 'jid_alt' => '9988@lid']));
    expect(WhatsappConversation::query()->count())->toBe(1)->and(WhatsappConversation::query()->first()->phone)->toBe('201001112222')
        ->and(WhatsappMessage::query()->count())->toBe(2);
});

it('rejects bad signature and ignores groups, unknown types and retired/unknown session ids', function () {
    $this->postJson('/internal/whatsapp/events', ['session_id' => 'central', 'type' => 'message.in', 'data' => inMsg()])->assertStatus(401);
    signedPost('central', 'message.in', inMsg(['jid' => '123-456@g.us']))->assertOk();
    signedPost('central', 'weird.thing', ['x' => 1])->assertOk();
    signedPost('tenant-1', 'message.in', inMsg())->assertOk();   // retired per-tenant id
    signedPost('nope', 'message.in', inMsg())->assertOk();
    expect(WhatsappMessage::query()->count())->toBe(0);
});

it('stores unknown message types as unsupported and from-me messages as sent without unread', function () {
    $this->ing->handle('message.in', inMsg(['id' => 'U', 'type' => 'newfangled', 'body' => null]));
    $this->ing->handle('message.in', inMsg(['id' => 'O', 'from_me' => true, 'timestamp' => 1790000100]));
    expect(WhatsappMessage::query()->where('wa_message_id', 'U')->value('type'))->toBe('unsupported');
    $o = WhatsappMessage::query()->where('wa_message_id', 'O')->first();
    expect($o->direction)->toBe('out')->and($o->status)->toBe('sent')->and(WhatsappConversation::query()->first()->unread_count)->toBe(1);
});

it('stores payload types: location, contact, poll, link preview, and reply linkage', function () {
    $this->ing->handle('message.in', inMsg(['id' => 'T']));
    $this->ing->handle('message.in', inMsg(['id' => 'L', 'type' => 'location', 'body' => null, 'payload' => ['lat' => 30.1, 'lng' => 31.2, 'name' => 'Cairo', 'address' => null], 'quoted' => ['id' => 'T', 'participant' => null]]));
    $this->ing->handle('message.in', inMsg(['id' => 'C', 'type' => 'contact', 'payload' => ['contacts' => [['name' => 'Bob', 'vcard' => 'BEGIN:VCARD']]]]));
    $this->ing->handle('message.in', inMsg(['id' => 'P', 'type' => 'poll', 'body' => 'Q?', 'payload' => ['options' => ['a', 'b'], 'selectable_count' => 1]]));
    $this->ing->handle('message.in', inMsg(['id' => 'K', 'payload' => ['link' => ['url' => 'https://x.test', 'title' => 't']]]));
    $l = WhatsappMessage::query()->where('wa_message_id', 'L')->first();
    expect($l->payload['lat'])->toBe(30.1)->and($l->reply_to_id)->toBe(WhatsappMessage::query()->where('wa_message_id', 'T')->value('id'))
        ->and(WhatsappMessage::query()->where('wa_message_id', 'C')->first()->payload['contacts'][0]['name'])->toBe('Bob')
        ->and(WhatsappMessage::query()->where('wa_message_id', 'P')->first()->payload['options'])->toBe(['a', 'b'])
        ->and(WhatsappMessage::query()->where('wa_message_id', 'K')->first()->payload['link']['url'])->toBe('https://x.test');
});

it('creates a media row on message.in, then pulls bytes on media.ready and deletes the node entry', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Http::fake([
        'wa.test/sessions/central/media/abcdef0123456789abcdef0123456789' => fn ($r) => $r->method() === 'GET'
            ? Http::response($png, 200, ['Content-Type' => 'image/png', 'x-content-sha256' => hash('sha256', $png)]) : Http::response('', 204),
    ]);
    Event::fake([ChatMessageUpdated::class, ChatMessageReceived::class]);
    $this->ing->handle('message.in', inMsg(['id' => 'IMG', 'type' => 'image', 'body' => null, 'caption' => 'pic',
        'media' => ['mime' => 'image/png', 'size' => 70, 'file_name' => '../../evil.png', 'width' => 1, 'height' => 1, 'duration' => null, 'animated' => false, 'thumbnail_b64' => base64_encode($png)]]));
    $media = WhatsappMedia::query()->first();
    expect($media->status)->toBe('pending')->and($media->original_name)->toBe('evil.png');
    Storage::disk('chat-test')->assertExists($media->thumbnail_path);

    $this->ing->handle('media.ready', ['message_id' => 'IMG', 'jid' => '201001112222@s.whatsapp.net', 'from_me' => false, 'status' => 'ready', 'token' => 'abcdef0123456789abcdef0123456789', 'mime' => 'image/png', 'size' => strlen($png), 'sha256' => hash('sha256', $png)]);
    $media->refresh();
    expect($media->status)->toBe('ready')->and($media->mime)->toBe('image/png')->and($media->path)->toStartWith('whatsapp-media/'.now()->format('Y/m').'/');
    Storage::disk('chat-test')->assertExists($media->path);
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/media/abcdef0123456789abcdef0123456789'));
    Event::assertDispatched(ChatMessageUpdated::class, fn ($e) => $e->message['media']['status'] === 'ready');
});

it('marks media failed or expired', function () {
    Http::fake(['wa.test/*' => Http::response('', 404)]);
    Event::fake([ChatMessageUpdated::class]);
    foreach (['F1', 'F2'] as $id) {
        $this->ing->handle('message.in', inMsg(['id' => $id, 'type' => 'image', 'body' => null, 'media' => ['mime' => 'image/png', 'size' => 5]]));
    }
    $this->ing->handle('media.ready', ['message_id' => 'F1', 'status' => 'failed', 'reason' => 'too_large']);
    $this->ing->handle('media.ready', ['message_id' => 'F2', 'status' => 'ready', 'token' => 'abcdef0123456789abcdef0123456789']);
    $st = WhatsappMessage::query()->with('media')->orderBy('id')->get()->map(fn ($m) => $m->media->status)->all();
    expect($st)->toBe(['failed', 'expired']);
});

it('applies reactions, replacing and removing', function () {
    Event::fake([ChatMessageUpdated::class]);
    $this->ing->handle('message.in', inMsg());
    $this->ing->handle('message.reaction', ['id' => 'R1', 'jid' => '201001112222@s.whatsapp.net', 'from_me' => false, 'target_id' => 'MSG1', 'emoji' => '👍']);
    $this->ing->handle('message.reaction', ['id' => 'R2', 'jid' => '201001112222@s.whatsapp.net', 'from_me' => false, 'target_id' => 'MSG1', 'emoji' => '❤️']);
    $this->ing->handle('message.reaction', ['id' => 'R3', 'jid' => '201001112222@s.whatsapp.net', 'from_me' => true, 'target_id' => 'MSG1', 'emoji' => '🔥']);
    expect(WhatsappReaction::pluck('emoji', 'reactor_jid')->all())->toBe(['201001112222@s.whatsapp.net' => '❤️', 'me' => '🔥']);
    $this->ing->handle('message.reaction', ['id' => 'R4', 'jid' => '201001112222@s.whatsapp.net', 'from_me' => false, 'target_id' => 'MSG1', 'emoji' => '']);
    expect(WhatsappReaction::count())->toBe(1);
    Event::assertDispatched(ChatMessageUpdated::class, fn ($e) => count($e->message['reactions']) === 1);
});

it('applies edits and revokes (tombstone erases content)', function () {
    Event::fake([ChatMessageUpdated::class, ChatConversationUpdated::class]);
    $this->ing->handle('message.in', inMsg());
    $this->ing->handle('message.edit', ['target_id' => 'MSG1', 'body' => 'fixed', 'edited_at' => 1790000500]);
    $m = WhatsappMessage::query()->first();
    expect($m->body)->toBe('fixed')->and($m->edited_at)->not->toBeNull()->and(WhatsappConversation::query()->first()->last_message_preview)->toBe('fixed');
    $this->ing->handle('message.delete', ['target_id' => 'MSG1']);
    $m->refresh();
    expect($m->deleted_at)->not->toBeNull()->and($m->body)->toBeNull()->and(WhatsappConversation::query()->first()->last_message_preview)->toBeNull();
    $this->ing->handle('message.edit', ['target_id' => 'MSG1', 'body' => 'zombie']);
    expect($m->fresh()->body)->toBeNull();
    Event::assertDispatched(ChatMessageUpdated::class, fn ($e) => $e->message['deleted_at'] && $e->message['body'] === null);
});

it('moves delivery status forward only and only for outgoing messages', function () {
    $this->ing->handle('message.in', inMsg(['id' => 'OUT', 'from_me' => true]));
    $this->ing->handle('message.in', inMsg(['id' => 'IN']));
    $s = fn (string $id, string $st) => $this->ing->handle('message.status', ['id' => $id, 'jid' => 'x', 'from_me' => true, 'status' => $st]);
    $s('OUT', 'read');
    $s('OUT', 'delivered');
    $s('OUT', 'failed');
    $s('IN', 'read');
    expect(WhatsappMessage::query()->where('wa_message_id', 'OUT')->value('status'))->toBe('read')
        ->and(WhatsappMessage::query()->where('wa_message_id', 'IN')->value('status'))->toBe('delivered');
});

it('broadcasts presence without persisting and updates names from contact.update', function () {
    Event::fake([ChatPresence::class, ChatConversationUpdated::class]);
    $this->ing->handle('message.in', inMsg());
    $this->ing->handle('presence', ['jid' => '201001112222@s.whatsapp.net', 'participant' => null, 'state' => 'composing', 'last_seen' => null]);
    $this->ing->handle('presence', ['jid' => '201001112222@s.whatsapp.net', 'state' => 'bogus']);
    Event::assertDispatchedTimes(ChatPresence::class, 1);
    $this->ing->handle('contact.update', ['contacts' => [['jid' => '201001112222@s.whatsapp.net', 'name' => 'Ali Saved', 'notify' => 'Ali N'], ['jid' => '5555@s.whatsapp.net', 'name' => 'Nobody']]]);
    expect(WhatsappConversation::query()->first()->name)->toBe('Ali Saved')->and(WhatsappConversation::query()->count())->toBe(1);
    Event::assertDispatched(ChatConversationUpdated::class);
});

it('ignores tenant / owner fields in the payload (one session)', function () {
    signedPost('central', 'message.in', inMsg(['tenant_id' => 999, 'owner_key' => 0]))->assertOk();
    expect(WhatsappMessage::query()->count())->toBe(1)->and(\Illuminate\Support\Facades\Schema::hasColumn('whatsapp_messages', 'tenant_id'))->toBeFalse();
});

it('authorizes the whatsapp-chat channel on guard admin by permission', function () {
    $reg = app(BroadcastManager::class)->driver();
    $check = function ($user, string $channel) use ($reg) {
        $m = new ReflectionMethod($reg, 'verifyUserCanAccessChannel');
        $m->setAccessible(true);
        $req = Request::create('/broadcasting/auth', 'POST', ['channel_name' => 'private-'.$channel]);
        $req->setUserResolver(fn () => $user);
        try {
            $m->invoke($reg, $req, $channel);

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    };
    $super = Admin::where('is_super_admin', true)->first();
    $plain = Admin::create(['name' => 'S', 'phone' => '201000000002', 'password' => 'password', 'is_active' => true]);
    $staff = Admin::create(['name' => 'C', 'phone' => '201000000004', 'password' => 'password', 'is_active' => true]);
    $staff->givePermissionTo(Permission::findOrCreate('whatsapp.chat.view', 'admin'));
    $owner = Admin::query()->where('email', 'owner@egy.test')->first();
    expect($check($super, 'whatsapp-chat'))->toBeTrue()
        ->and($check($owner, 'whatsapp-chat'))->toBeTrue()
        ->and($check($staff, 'whatsapp-chat'))->toBeTrue()
        ->and($check($plain, 'whatsapp-chat'))->toBeFalse()
        ->and($check($super, 'whatsapp'))->toBeTrue()
        ->and($check($plain, 'whatsapp'))->toBeFalse();
    $staff->update(['is_active' => false]);
    expect($check($staff->fresh(), 'whatsapp-chat'))->toBeFalse();
});

it('prunes old messages, their media files and empty conversations', function () {
    $this->ing->handle('message.in', inMsg(['id' => 'OLD', 'timestamp' => now()->subDays(400)->timestamp, 'type' => 'image', 'body' => null, 'media' => ['mime' => 'image/png', 'size' => 5, 'thumbnail_b64' => base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))]]));
    $this->ing->handle('message.in', inMsg(['id' => 'NEW', 'jid' => '201009998888@s.whatsapp.net', 'phone' => '201009998888', 'timestamp' => now()->timestamp]));
    $thumb = WhatsappMedia::query()->value('thumbnail_path');
    Storage::disk('chat-test')->assertExists($thumb);
    $this->artisan('whatsapp:chat-prune', ['--days' => 365, '--dry-run' => true])->assertSuccessful();
    expect(WhatsappMessage::query()->count())->toBe(2);
    $this->artisan('whatsapp:chat-prune', ['--days' => 365])->assertSuccessful();
    expect(WhatsappMessage::query()->pluck('wa_message_id')->all())->toBe(['NEW'])->and(WhatsappConversation::query()->count())->toBe(1)->and(WhatsappMedia::query()->count())->toBe(0);
    Storage::disk('chat-test')->assertMissing($thumb);
});

test('ingest links the conversation to the one customer with that phone (single store: always on)', function () {
    $mine = DB::table('customers')->insertGetId(['name' => 'C', 'email' => uniqid().'@x.test', 'phone' => '01001112222', 'phone_country_code' => '+20', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    $this->ing->handle('message.in', inMsg(['id' => 'CU1', 'jid' => '201001112222@s.whatsapp.net', 'phone' => '201001112222']));
    $this->ing->handle('message.in', inMsg(['id' => 'CU2', 'jid' => '201001112222@s.whatsapp.net', 'phone' => '201001112222']));
    expect(WhatsappConversation::query()->first()->customer_id)->toBe($mine);
});
