<?php

use App\Events\Whatsapp\ChatConversationUpdated;
use App\Events\Whatsapp\ChatMessageReceived;
use App\Events\Whatsapp\ChatMessageUpdated;
use App\Jobs\SendWhatsappMessage;
use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMedia;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use App\Services\Whatsapp\Chat\ChatIngestService;
use App\Services\Whatsapp\Chat\ChatSendService;
use App\Services\Whatsapp\Chat\MediaScanner;
use App\Services\Whatsapp\WhatsappService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'tok', 'callback_secret' => 's'], 'whatsapp_chat.disk' => 'chat-test']);
    Storage::fake('chat-test');
    $this->c = 'http://3alraf.test/admin/whatsapp/chat';
    $this->t = 'http://egy.3alraf.test/admin/whatsapp/chat';
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->super = CentralAdmin::where('is_super_admin', true)->first();
    $this->owner = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'o', 'email' => 'o@egy.test', 'password' => 'password', 'is_active' => true]);
    $this->owner->syncRoles('tenant-owner');
    foreach ([null, $this->tenant->id] as $o) {
        app(WhatsappService::class)->session($o)->update(['status' => 'connected', 'is_enabled' => true, 'phone' => '201111111111']);
    }
});

function conv(?int $tenant, array $o = []): WhatsappConversation
{
    $c = WhatsappConversation::factory()->make($o + ['jid' => '2010'.random_int(1000000, 9999999).'@s.whatsapp.net', 'last_message_at' => now()]);
    $c->tenant_id = $tenant;
    $c->phone ??= null;
    $c->save();

    return $c;
}

function msg(WhatsappConversation $c, array $o = []): WhatsappMessage
{
    $m = WhatsappMessage::factory()->make($o + ['conversation_id' => $c->id]);
    $m->tenant_id = $c->tenant_id;
    $m->save();

    return $m;
}

function nodeOk(string $id = 'WAID1'): void
{
    Http::swap(new Factory);
    Http::fake(['wa.test/sessions/*/uploads*' => Http::response(['upload_id' => 'up1', 'size' => 3, 'sha256' => 'x', 'mime' => 'image/png'], 201),
        'wa.test/sessions/*/messages' => Http::response(['message_id' => $id, 'to_jid' => '201001112222@s.whatsapp.net', 'timestamp' => 1790000000]),
        'wa.test/sessions/*/read' => Http::response(['ok' => true]), 'wa.test/sessions/*/presence' => Http::response(['ok' => true])]);
}

function png(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('pic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
}

it('requires authentication and the right permission per guard', function () {
    $this->getJson("$this->c/conversations")->assertStatus(401);
    $plain = CentralAdmin::create(['name' => 'P', 'phone' => '201000000077', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($plain, 'central')->getJson("$this->c/conversations")->assertForbidden();
    $this->actingAs($plain, 'central')->postJson("$this->c/conversations", ['country' => 'EG', 'phone' => '01001112222'])->assertForbidden();
    $this->actingAs($this->super, 'central')->getJson("$this->c/conversations")->assertOk();
    $this->actingAs($this->owner, 'tenant')->getJson("$this->t/conversations")->assertOk();
    // view-only role cannot send / manage / read media without the permission
    $viewer = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'v', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $viewer->givePermissionTo(Permission::findOrCreate('whatsapp.chat.view', 'tenant'));
    $c = conv($this->tenant->id);
    $this->actingAs($viewer, 'tenant')->getJson("$this->t/conversations/$c->id/messages")->assertOk();
    $this->postJson("$this->t/conversations/$c->id/messages", ['type' => 'text', 'text' => 'x'])->assertForbidden();
    $this->patchJson("$this->t/conversations/$c->id", ['is_pinned' => true])->assertForbidden();
    $this->deleteJson("$this->t/conversations/$c->id")->assertForbidden();
});

it('isolates owners: tenant and central never see each other, and ids from another owner are 404', function () {
    $mine = conv($this->tenant->id, ['name' => 'Mine']);
    $central = conv(null, ['name' => 'Central']);
    $other = Tenant::create(['slug' => 'uae', 'name' => 'U', 'country' => 'AE', 'currency_code' => 'AED', 'currency_symbol' => 'AED', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'Asia/Dubai', 'is_active' => true]);
    $theirs = conv($other->id, ['name' => 'Theirs']);
    $tm = msg($theirs, ['direction' => 'out', 'status' => 'failed', 'wa_message_id' => null]);
    $media = new WhatsappMedia(['disk' => 'chat-test', 'path' => 'x.png', 'mime' => 'image/png', 'status' => 'ready']);
    $media->tenant_id = $other->id;
    $media->save();
    Storage::disk('chat-test')->put('x.png', 'abc');

    $this->actingAs($this->owner, 'tenant');
    $names = collect($this->getJson("$this->t/conversations")->assertOk()->json('data'))->pluck('name')->all();
    expect($names)->toBe(['Mine']);
    foreach (["conversations/$theirs->id", "conversations/$central->id", "conversations/$theirs->id/messages", "media/$media->id"] as $u) {
        $this->getJson("$this->t/$u")->assertNotFound();
    }
    $this->postJson("$this->t/conversations/$theirs->id/messages", ['type' => 'text', 'text' => 'hi'])->assertNotFound();
    $this->postJson("$this->t/conversations/$theirs->id/read")->assertNotFound();
    $this->patchJson("$this->t/conversations/$theirs->id", ['is_pinned' => true])->assertNotFound();
    $this->deleteJson("$this->t/conversations/$theirs->id")->assertNotFound();
    $this->postJson("$this->t/messages/$tm->id/resend")->assertNotFound();
    expect(WhatsappConversation::ownedBy($other->id)->count())->toBe(1);

    $this->actingAs($this->super, 'central');
    expect(collect($this->getJson("$this->c/conversations")->json('data'))->pluck('name')->all())->toBe(['Central']);
    $this->getJson("$this->c/conversations/$mine->id")->assertNotFound();
    $this->getJson("$this->c/media/$media->id")->assertNotFound();
});

it('lists conversations with search, filters and cursor pagination', function () {
    $this->actingAs($this->super, 'central');
    foreach (range(1, 5) as $i) {
        conv(null, ['name' => "Chat $i", 'last_message_at' => now()->subMinutes($i), 'unread_count' => $i === 2 ? 3 : 0, 'is_pinned' => $i === 5]);
    }
    conv(null, ['name' => 'Old', 'is_archived' => true]);
    $p1 = $this->getJson("$this->c/conversations?limit=2")->assertOk();
    expect($p1->json('data.0.name'))->toBe('Chat 5')->and($p1->json('has_more'))->toBeTrue()->and($p1->json('unread_total'))->toBe(3);
    $p2 = $this->getJson("$this->c/conversations?limit=2&cursor=".urlencode($p1->json('next_cursor')))->assertOk();
    expect(collect($p2->json('data'))->pluck('name')->all())->toBe(['Chat 2', 'Chat 3']);
    expect($this->getJson("$this->c/conversations?unread=1")->json('data'))->toHaveCount(1);
    expect($this->getJson("$this->c/conversations?archived=1")->json('data.0.name'))->toBe('Old');
    expect($this->getJson("$this->c/conversations?q=Chat+3")->json('data'))->toHaveCount(1);
    expect($this->getJson("$this->c/conversations?q=%25")->json('data'))->toHaveCount(0); // LIKE wildcards are escaped
    $this->getJson("$this->c/conversations?limit=500")->assertStatus(422);
});

it('pages the thread in both directions', function () {
    $this->actingAs($this->super, 'central');
    $c = conv(null);
    $ids = collect(range(1, 7))->map(fn ($i) => msg($c, ['body' => "m$i"])->id)->all();
    $r = $this->getJson("$this->c/conversations/$c->id/messages?limit=3")->assertOk();
    expect(collect($r->json('data'))->pluck('id')->all())->toBe(array_slice($ids, 4, 3))->and($r->json('has_more_before'))->toBeTrue();
    $r = $this->getJson("$this->c/conversations/$c->id/messages?limit=3&before=".$ids[4])->assertOk();
    expect(collect($r->json('data'))->pluck('id')->all())->toBe(array_slice($ids, 1, 3));
    $r = $this->getJson("$this->c/conversations/$c->id/messages?limit=2&after=".$ids[4])->assertOk();
    expect(collect($r->json('data'))->pluck('id')->all())->toBe(array_slice($ids, 5, 2))->and($r->json('has_more_after'))->toBeFalse();
});

it('creates a conversation by phone via AdminPhone and rejects bad numbers', function () {
    $this->actingAs($this->super, 'central');
    $this->postJson("$this->c/conversations", ['country' => 'EG', 'phone' => '123'])->assertStatus(422)->assertJsonPath('code', 'invalid_phone');
    $this->postJson("$this->c/conversations", ['country' => 'EG'])->assertStatus(422);
    $r = $this->postJson("$this->c/conversations", ['country' => 'EG', 'phone' => '01001112222'])->assertCreated();
    expect($r->json('data.phone'))->toBe('201001112222')->and($r->json('data.jid'))->toBe('201001112222@s.whatsapp.net');
    $this->postJson("$this->c/conversations", ['country' => 'EG', 'phone' => '01001112222'])->assertOk();
    expect(WhatsappConversation::ownedBy(null)->count())->toBe(1);
});

it('validates the send payload per type', function () {
    $this->actingAs($this->super, 'central');
    $c = conv(null);
    $u = "$this->c/conversations/$c->id/messages";
    $this->postJson($u, [])->assertStatus(422)->assertJsonValidationErrors('type');
    $this->postJson($u, ['type' => 'nope'])->assertStatus(422);
    $this->postJson($u, ['type' => 'text'])->assertJsonValidationErrors('text');
    $this->postJson($u, ['type' => 'text', 'text' => str_repeat('a', 5000)])->assertJsonValidationErrors('text');
    $this->postJson($u, ['type' => 'image'])->assertJsonValidationErrors('file');
    $this->postJson($u, ['type' => 'text', 'text' => 'x', 'file' => png()])->assertJsonValidationErrors('file');
    $this->postJson($u, ['type' => 'location', 'location' => ['lat' => 100, 'lng' => 1]])->assertJsonValidationErrors('location.lat');
    $this->postJson($u, ['type' => 'contact', 'contacts' => [['name' => 'A', 'phone' => 'abc']]])->assertJsonValidationErrors('contacts.0.phone');
    $this->postJson($u, ['type' => 'poll', 'poll' => ['name' => 'Q', 'options' => ['a']]])->assertJsonValidationErrors('poll.options');
    $this->postJson($u, ['type' => 'poll', 'poll' => ['name' => 'Q', 'options' => ['a', 'a']]])->assertJsonValidationErrors('poll.options.0');
    $this->postJson($u, ['type' => 'poll', 'poll' => ['name' => 'Q', 'options' => ['a', 'b'], 'selectable_count' => 3]])->assertJsonValidationErrors('poll.selectable_count');
    // real MIME is sniffed: a text file named .png is not an image
    $fake = UploadedFile::fake()->createWithContent('evil.png', '<?php echo 1;');
    $this->postJson($u, ['type' => 'image', 'file' => $fake])->assertJsonValidationErrors('file');
    // size cap per type
    config(['whatsapp_chat.limits.image' => 10]);
    $this->postJson($u, ['type' => 'image', 'file' => png()])->assertJsonValidationErrors('file');
    // scanner hook
    config(['whatsapp_chat.limits.image' => 1000000]);
    app()->bind(MediaScanner::class, fn () => new class implements MediaScanner
    {
        public function isClean(string $p): bool
        {
            return false;
        }
    });
    $this->postJson($u, ['type' => 'image', 'file' => png()])->assertJsonValidationErrors('file');
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(0);
});

it('refuses to send when the session is not connected', function () {
    WhatsappSession::where('session_id', 'central')->update(['status' => 'disconnected']);
    $this->actingAs($this->super, 'central');
    $c = conv(null);
    $this->postJson("$this->c/conversations/$c->id/messages", ['type' => 'text', 'text' => 'x'])->assertStatus(409)->assertJsonPath('code', 'not_connected');
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(0);
});

it('queues a text send, creates the pending row first and sends via the owner session only', function () {
    Queue::fake();
    Event::fake([ChatMessageReceived::class, ChatMessageUpdated::class]);
    $this->actingAs($this->owner, 'tenant');
    $c = conv($this->tenant->id, ['phone' => '201001112222', 'jid' => '201001112222@s.whatsapp.net']);
    $r = $this->postJson("$this->t/conversations/$c->id/messages", ['type' => 'text', 'text' => 'hello', 'session_id' => 'central', 'tenant_id' => 999])->assertStatus(202);
    expect($r->json('data.status'))->toBe('pending')->and($r->json('data.direction'))->toBe('out')->and($r->json('data.body'))->toBe('hello');
    $m = WhatsappMessage::ownedBy($this->tenant->id)->first();
    expect($m->wa_message_id)->toBeNull()->and($m->sent_by_guard)->toBe('tenant')->and($m->sent_by_admin_id)->toBe($this->owner->id);
    Queue::assertPushed(SendWhatsappMessage::class, fn ($j) => $j->tenantId === $this->tenant->id && $j->messageId === $m->id);
    Event::assertDispatched(ChatMessageReceived::class, fn ($e) => $e->tenantId === $this->tenant->id && $e->message['status'] === 'pending');
    expect($c->fresh()->last_message_id)->toBe($m->id);
});

it('sends every type through Node with the right typed payload (job end to end)', function () {
    nodeOk();
    $this->actingAs($this->super, 'central');
    $c = conv(null, ['phone' => '201001112222', 'jid' => '201001112222@s.whatsapp.net']);
    $c2 = conv(null, ['jid' => '555@lid', 'phone' => null]);
    $u = "$this->c/conversations/$c->id/messages";
    $sent = [];
    $i = 0;
    foreach ([
        ['type' => 'text', 'text' => 'hello'],
        ['type' => 'image', 'file' => png(), 'caption' => 'cap'],
        ['type' => 'document', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")],
        ['type' => 'ptt', 'file' => UploadedFile::fake()->createWithContent('v.ogg', "OggS\x00\x02".str_repeat("\0", 20)."\x01\x13OpusHead\x01\x01\x38\x01\x80\xbb\x00\x00\x00\x00\x00")],
        ['type' => 'location', 'location' => ['lat' => 30.04, 'lng' => 31.23, 'name' => 'Cairo']],
        ['type' => 'contact', 'contacts' => [['name' => 'Ali', 'phone' => '+201001234567']]],
        ['type' => 'poll', 'poll' => ['name' => 'Q?', 'options' => ['a', 'b'], 'selectable_count' => 1]],
    ] as $body) {
        $i++;
        $id = $this->postJson($u, $body)->assertStatus(202)->json('data.id');
        expect(WhatsappMessage::ownedBy(null)->find($id)->status)->toBe('sent'); // sync queue in tests
    }
    $payloads = Http::recorded(fn ($req) => str_ends_with($req->url(), '/sessions/central/messages'))->map(fn ($p) => $p[0]->data())->values();
    expect($payloads)->toHaveCount(7);
    expect($payloads[0])->toMatchArray(['to' => '201001112222', 'type' => 'text', 'text' => 'hello']); // first message: Node does the number lookup
    expect($payloads[1])->toMatchArray(['to' => '201001112222@s.whatsapp.net', 'type' => 'image', 'caption' => 'cap'])->and($payloads[1]['media']['upload_id'])->toBe('up1');
    expect($payloads[2]['type'])->toBe('document')->and($payloads[3]['type'])->toBe('ptt');
    expect($payloads[4]['location'])->toMatchArray(['lat' => 30.04, 'lng' => 31.23, 'name' => 'Cairo']);
    expect($payloads[5]['contacts'])->toBe([['name' => 'Ali', 'phone' => '+201001234567']]);
    expect($payloads[6]['poll'])->toMatchArray(['name' => 'Q?', 'options' => ['a', 'b']]);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/sessions/central/uploads') && $r->header('Content-Type')[0] === 'image/png' && $r->hasHeader('Authorization'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'tenant-'));
    $this->postJson("$this->c/conversations/$c2->id/messages", ['type' => 'text', 'text' => 'lid'])->assertStatus(202);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && ($r['to'] ?? null) === '555@lid');
});

it('replies with the quoted wa id and keeps the session derived from the tenant', function () {
    nodeOk();
    $this->actingAs($this->owner, 'tenant');
    $c = conv($this->tenant->id);
    $orig = msg($c, ['wa_message_id' => 'ORIG', 'direction' => 'in']);
    $this->postJson("$this->t/conversations/$c->id/messages", ['type' => 'text', 'text' => 'r', 'reply_to_id' => $orig->id])->assertStatus(202)->assertJsonPath('data.reply_to_id', $orig->id)->assertJsonPath('data.reply_to.id', $orig->id);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions/tenant-'.$this->tenant->id.'/messages') && $r['reply_to'] === ['id' => 'ORIG', 'from_me' => false]);
    // reply target from another conversation is ignored, not leaked
    $other = conv($this->tenant->id);
    $foreign = msg($other, ['wa_message_id' => 'F']);
    $r = $this->postJson("$this->t/conversations/$c->id/messages", ['type' => 'text', 'text' => 'r2', 'reply_to_id' => $foreign->id])->assertStatus(202);
    expect($r->json('data.reply_to_id'))->toBeNull();
});

it('marks the row failed (with an error code, no body) when Node rejects, and resends the same row', function () {
    Http::swap(new Factory);
    Http::fake(['wa.test/sessions/*/messages' => Http::response(['error' => 'number is not on WhatsApp'], 422)]);
    $this->actingAs($this->super, 'central');
    $c = conv(null);
    $id = $this->postJson("$this->c/conversations/$c->id/messages", ['type' => 'text', 'text' => 'secret body'])->assertStatus(202)->json('data.id');
    $m = WhatsappMessage::ownedBy(null)->find($id);
    expect($m->status)->toBe('failed')->and($m->error)->toBe('number is not on WhatsApp')->and($m->error)->not->toContain('secret');
    nodeOk('NEWID');
    $this->postJson("$this->c/messages/$id/resend")->assertStatus(202);
    expect($m->fresh()->status)->toBe('sent')->and($m->fresh()->wa_message_id)->toBe('NEWID')->and(WhatsappMessage::ownedBy(null)->count())->toBe(1);
    $this->postJson("$this->c/messages/$id/resend")->assertStatus(422)->assertJsonPath('code', 'not_resendable');
});

it('reconciles the from_me echo that arrives BEFORE the Node response (no duplicate)', function () {
    $c = conv(null, ['phone' => '201001112222', 'jid' => '201001112222@s.whatsapp.net', 'last_message_at' => now()->subHour()]);
    $m = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null, 'body' => 'hi there']);
    $ing = app(ChatIngestService::class);
    $ing->handle(null, 'message.in', ['id' => 'ECHO1', 'jid' => $c->jid, 'phone' => '201001112222', 'from_me' => true, 'timestamp' => time(), 'type' => 'text', 'body' => 'hi there']);
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(1)->and($m->fresh()->wa_message_id)->toBe('ECHO1')->and($m->fresh()->status)->toBe('sent');
    // delivery receipt lands before the response too
    $ing->handle(null, 'message.status', ['id' => 'ECHO1', 'jid' => $c->jid, 'from_me' => true, 'status' => 'delivered']);
    // then the response arrives: id is already set, status must not regress
    $m->refresh()->forceFill(['status' => 'pending'])->save(); // job started when the row was still pending
    $m->forceFill(['status' => 'delivered'])->save();
    nodeOk('ECHO1');
    Queue::fake();
    app(ChatSendService::class)->deliver(null, $m->id); // no-op: not pending anymore
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(1)->and($m->fresh()->status)->toBe('delivered');
    // a replay of the echo creates nothing
    $ing->handle(null, 'message.in', ['id' => 'ECHO1', 'jid' => $c->jid, 'from_me' => true, 'timestamp' => time(), 'type' => 'text', 'body' => 'hi there']);
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(1);
});

it('merges an echo row that was inserted before the response when no pending row matched', function () {
    $c = conv(null, ['phone' => '201001112222', 'jid' => '201001112222@s.whatsapp.net']);
    $m = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null, 'body' => 'orig']);
    // echo with a (slightly) different body (e.g. link-preview normalisation) is inserted as its own row
    app(ChatIngestService::class)->handle(null, 'message.in', ['id' => 'ECHO2', 'jid' => $c->jid, 'from_me' => true, 'timestamp' => time(), 'type' => 'text', 'body' => 'orig!']);
    expect(WhatsappMessage::ownedBy(null)->count())->toBe(2);
    nodeOk('ECHO2');
    app(ChatSendService::class)->deliver(null, $m->id);
    $rows = WhatsappMessage::ownedBy(null)->get();
    expect($rows)->toHaveCount(1)->and($rows[0]->id)->toBe($m->id)->and($rows[0]->wa_message_id)->toBe('ECHO2')->and($c->fresh()->last_message_id)->toBe($m->id);
});

it('does not adopt a pending row of another conversation, type or owner', function () {
    $c = conv(null, ['jid' => '201001112222@s.whatsapp.net']);
    $c2 = conv(null, ['jid' => '201003334444@s.whatsapp.net']);
    $m = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null, 'body' => 'same']);
    $ing = app(ChatIngestService::class);
    $ing->handle(null, 'message.in', ['id' => 'E1', 'jid' => $c2->jid, 'from_me' => true, 'timestamp' => time(), 'type' => 'text', 'body' => 'same']);
    $ing->handle($this->tenant->id, 'message.in', ['id' => 'E2', 'jid' => $c->jid, 'from_me' => true, 'timestamp' => time(), 'type' => 'text', 'body' => 'same']);
    expect($m->fresh()->wa_message_id)->toBeNull()->and($m->fresh()->status)->toBe('pending');
});

it('adopts the real jid Node reports for a new chat started by phone', function () {
    Http::fake(['wa.test/sessions/*/messages' => Http::response(['message_id' => 'M9', 'to_jid' => '99887766@lid', 'timestamp' => 1790000000])]);
    $this->actingAs($this->super, 'central');
    $id = $this->postJson("$this->c/conversations", ['country' => 'EG', 'phone' => '01001112222'])->json('data.id');
    $this->postJson("$this->c/conversations/$id/messages", ['type' => 'text', 'text' => 'first'])->assertStatus(202);
    expect(WhatsappConversation::ownedBy(null)->find($id)->jid)->toBe('99887766@lid');
    Http::assertSent(fn ($r) => ($r['to'] ?? null) === '201001112222');
});

it('marks a conversation read: resets unread, tells Node, tolerates Node being down', function () {
    nodeOk();
    Event::fake([ChatConversationUpdated::class]);
    $this->actingAs($this->super, 'central');
    $c = conv(null, ['unread_count' => 2]);
    msg($c, ['wa_message_id' => 'IN1']);
    msg($c, ['wa_message_id' => 'IN2']);
    $r = $this->postJson("$this->c/conversations/$c->id/read")->assertOk();
    expect($r->json('data.unread_count'))->toBe(0)->and($r->json('node_synced'))->toBeTrue()->and($c->fresh()->unread_count)->toBe(0);
    Http::assertSent(fn ($q) => str_ends_with($q->url(), '/sessions/central/read') && $q['jid'] === $c->jid && collect($q['keys'])->pluck('id')->sort()->values()->all() === ['IN1', 'IN2']);
    Event::assertDispatched(ChatConversationUpdated::class);
    $c->update(['unread_count' => 1]);
    Http::swap(new Factory);
    Http::fake(['wa.test/*' => Http::response(['error' => 'x'], 500)]);
    $this->postJson("$this->c/conversations/$c->id/read")->assertOk()->assertJsonPath('node_synced', false);
    expect($c->fresh()->unread_count)->toBe(0);
});

it('sends presence through the owner session', function () {
    nodeOk();
    $this->actingAs($this->owner, 'tenant');
    $c = conv($this->tenant->id);
    $this->postJson("$this->t/conversations/$c->id/presence", ['state' => 'composing'])->assertOk();
    $this->postJson("$this->t/conversations/$c->id/presence", ['state' => 'bogus'])->assertStatus(422);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions/tenant-'.$this->tenant->id.'/presence'));
});

it('archives, pins, assigns (same owner only) and deletes locally with its media files', function () {
    $this->actingAs($this->owner, 'tenant');
    $c = conv($this->tenant->id);
    $this->patchJson("$this->t/conversations/$c->id", ['is_pinned' => true, 'is_archived' => true])->assertOk()->assertJsonPath('data.is_pinned', true);
    $this->putJson("$this->t/conversations/$c->id/assign", ['admin_id' => $this->owner->id])->assertOk()->assertJsonPath('data.assigned_admin_id', $this->owner->id)->assertJsonPath('data.assigned_guard', 'tenant');
    $other = Tenant::create(['slug' => 'uae', 'name' => 'U', 'country' => 'AE', 'currency_code' => 'AED', 'currency_symbol' => 'AED', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'Asia/Dubai', 'is_active' => true]);
    $foreign = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'f', 'email' => 'f@uae.test', 'password' => 'password', 'is_active' => true]);
    $foreign->syncRoles('tenant-owner');
    $this->putJson("$this->t/conversations/$c->id/assign", ['admin_id' => $foreign->id])->assertStatus(422)->assertJsonPath('code', 'invalid_assignee');
    $this->putJson("$this->t/conversations/$c->id/assign", ['admin_id' => null])->assertOk()->assertJsonPath('data.assigned_admin_id', null);

    Storage::disk('chat-test')->put('f.png', 'abc');
    Storage::disk('chat-test')->put('t.jpg', 'abc');
    $media = new WhatsappMedia(['disk' => 'chat-test', 'path' => 'f.png', 'thumbnail_path' => 't.jpg', 'mime' => 'image/png', 'status' => 'ready']);
    $media->tenant_id = $this->tenant->id;
    $media->save();
    $m1 = msg($c, ['media_id' => $media->id, 'type' => 'image']);
    msg($c, ['reply_to_id' => $m1->id]);
    $this->deleteJson("$this->t/conversations/$c->id")->assertOk();
    expect(WhatsappConversation::ownedBy($this->tenant->id)->count())->toBe(0)->and(WhatsappMessage::ownedBy($this->tenant->id)->count())->toBe(0)->and(WhatsappMedia::ownedBy($this->tenant->id)->count())->toBe(0);
    Storage::disk('chat-test')->assertMissing('f.png');
    Storage::disk('chat-test')->assertMissing('t.jpg');
    $log = Activity::where('log_name', 'whatsapp-chat')->pluck('event')->all();
    expect($log)->toContain('conversation.assigned')->and($log)->toContain('conversation.deleted');
    expect(json_encode(Activity::where('log_name', 'whatsapp-chat')->get()->toArray()))->not->toContain('hello');
});

it('reacts, edits and deletes-for-everyone only on eligible messages', function () {
    nodeOk();
    $this->actingAs($this->super, 'central');
    $c = conv(null);
    $out = msg($c, ['direction' => 'out', 'status' => 'sent', 'wa_message_id' => 'OUT1', 'body' => 'old']);
    $in = msg($c, ['direction' => 'in', 'wa_message_id' => 'IN1']);
    $pending = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null]);

    $this->postJson("$this->c/messages/$in->id/react", ['emoji' => '👍'])->assertOk()->assertJsonPath('data.reactions.0.emoji', '👍');
    Http::assertSent(fn ($r) => ($r['reaction'] ?? null) === ['message_id' => 'IN1', 'from_me' => false, 'emoji' => '👍']);
    $this->postJson("$this->c/messages/$in->id/react", ['emoji' => ''])->assertOk()->assertJsonPath('data.reactions', []);
    $this->postJson("$this->c/messages/$pending->id/react", ['emoji' => '👍'])->assertStatus(422);

    $this->putJson("$this->c/messages/$out->id", ['text' => 'new'])->assertOk()->assertJsonPath('data.body', 'new');
    Http::assertSent(fn ($r) => ($r['edit'] ?? null) === ['message_id' => 'OUT1', 'text' => 'new']);
    $this->putJson("$this->c/messages/$in->id", ['text' => 'x'])->assertStatus(422)->assertJsonPath('code', 'not_editable');
    $out->forceFill(['created_at' => now()->subMinutes(30)])->save();
    $this->putJson("$this->c/messages/$out->id", ['text' => 'late'])->assertStatus(422);

    $this->deleteJson("$this->c/messages/$in->id")->assertStatus(422)->assertJsonPath('code', 'not_deletable');
    $this->deleteJson("$this->c/messages/$out->id")->assertOk()->assertJsonPath('data.body', null);
    expect($out->fresh()->deleted_at)->not->toBeNull();
    $this->deleteJson("$this->c/messages/$out->id")->assertStatus(422);

    Http::swap(new Factory);
    Http::fake(['wa.test/*' => Http::response(['error' => 'boom'], 500)]);
    $out2 = msg($c, ['direction' => 'out', 'status' => 'sent', 'wa_message_id' => 'OUT2']);
    $this->deleteJson("$this->c/messages/$out2->id")->assertStatus(502)->assertJsonPath('code', 'wa_error');
    expect($out2->fresh()->deleted_at)->toBeNull();
});

it('streams media with safe headers, Range support and a plain 404 for missing or unready files', function () {
    $this->actingAs($this->super, 'central');
    Storage::disk('chat-test')->put('a/b.png', '0123456789');
    $mk = fn (array $o) => tap(new WhatsappMedia($o + ['disk' => 'chat-test', 'original_name' => 'My "photo".png', 'status' => 'ready']), function ($m) {
        $m->tenant_id = null;
        $m->save();
    });
    $png = $mk(['path' => 'a/b.png', 'mime' => 'image/png']);
    $r = $this->get("$this->c/media/$png->id")->assertOk();
    expect($r->headers->get('Content-Type'))->toBe('image/png')->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($r->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($r->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->and($r->headers->get('Accept-Ranges'))->toBe('bytes');
    expect($r->streamedContent())->toBe('0123456789');
    $this->get("$this->c/media/$png->id?download=1")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="My _photo_.png"; filename*=utf-8\'\'My%20%22photo%22.png');
    $p = $this->get("$this->c/media/$png->id", ['Range' => 'bytes=2-4'])->assertStatus(206);
    expect($p->streamedContent())->toBe('234')->and($p->headers->get('Content-Range'))->toBe('bytes 2-4/10');
    expect($this->get("$this->c/media/$png->id", ['Range' => 'bytes=-3'])->streamedContent())->toBe('789');
    $this->get("$this->c/media/$png->id", ['Range' => 'bytes=50-60'])->assertStatus(416);
    // html / svg / unknown types are never rendered inline
    Storage::disk('chat-test')->put('a/h.html', '<script>alert(1)</script>');
    $html = $mk(['path' => 'a/h.html', 'mime' => 'text/html', 'original_name' => 'x.html']);
    $r = $this->get("$this->c/media/$html->id")->assertOk();
    expect($r->headers->get('Content-Disposition'))->toStartWith('attachment')->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    $this->get("$this->c/media/".$mk(['path' => 'a/missing.png', 'mime' => 'image/png'])->id)->assertNotFound();
    $this->get("$this->c/media/".$mk(['path' => null, 'status' => 'pending', 'mime' => 'image/png'])->id)->assertNotFound();
    // thumbnail variant
    Storage::disk('chat-test')->put('a/t.jpg', 'jpegbytes');
    $th = $mk(['path' => null, 'status' => 'pending', 'thumbnail_path' => 'a/t.jpg']);
    $this->get("$this->c/media/$th->id?thumb=1")->assertOk();
    // permission: view without media
    $v = CentralAdmin::create(['name' => 'V', 'phone' => '201000000055', 'password' => 'password', 'is_active' => true]);
    $v->givePermissionTo(Permission::findOrCreate('whatsapp.chat.view', 'central'));
    $this->actingAs($v, 'central')->get("$this->c/media/$png->id")->assertForbidden();
});

it('exposes upload limits and connection state', function () {
    $this->actingAs($this->owner, 'tenant');
    $r = $this->getJson("$this->t/limits")->assertOk();
    expect($r->json('data.limits.image'))->toBe(16 * 1024 * 1024)->and($r->json('data.limits.document'))->toBe(100 * 1024 * 1024)->and($r->json('data.connected'))->toBeTrue();
});

it('does not touch the OTP path: sendText still uses the legacy payload', function () {
    Http::fake(['wa.test/*' => Http::response(['ok' => true])]);
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'tok', 'callback_secret' => 's']]);
    AuthSettings::for(null)->set('whatsapp.enabled', true);
    app(WhatsappService::class)->sendText(null, '201001112222', 'code 123456');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions/central/messages') && $r->data() === ['to' => '201001112222', 'text' => 'code 123456']);
});

it('job: skips rows that are not pending, never double sends, and fails the row from failed()', function () {
    nodeOk();
    $c = conv(null);
    $sent = msg($c, ['direction' => 'out', 'status' => 'sent', 'wa_message_id' => 'DONE']);
    (new SendWhatsappMessage(null, $sent->id))->handle(app(ChatSendService::class));
    Http::assertNothingSent();
    $p = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null]);
    (new SendWhatsappMessage(null, $p->id))->failed(new RuntimeException('x'));
    expect($p->fresh()->status)->toBe('failed')->and($p->fresh()->error)->toBe('send_failed');
    // wrong owner: the job cannot reach another owner's row
    $q = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null]);
    (new SendWhatsappMessage($this->tenant->id, $q->id))->handle(app(ChatSendService::class));
    expect($q->fresh()->status)->toBe('pending');
    Http::assertNothingSent();
    $mw = (new SendWhatsappMessage(null, 1))->middleware();
    expect($mw[0])->toBeInstanceOf(WithoutOverlapping::class)->and($mw[0]->key)->toBe('wa-chat-send:central');
});

it('job: fails the row when the session dropped before delivery', function () {
    nodeOk();
    $c = conv(null);
    $p = msg($c, ['direction' => 'out', 'status' => 'pending', 'wa_message_id' => null]);
    WhatsappSession::where('session_id', 'central')->update(['status' => 'disconnected']);
    (new SendWhatsappMessage(null, $p->id))->handle(app(ChatSendService::class));
    expect($p->fresh()->status)->toBe('failed');
    Http::assertNothingSent();
});
