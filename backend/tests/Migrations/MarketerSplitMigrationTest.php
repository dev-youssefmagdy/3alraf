<?php

// Data migration 2027_01_12_000100_move_marketing_to_marketers (owner decision Q4 = auto-migrate): admins who used the marketer panel
// become marketers, every marketing "who" column is re-pointed to `marketers`, admin-guard marketing-only permissions disappear.

use App\Models\Admin;
use App\Models\Marketer;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Clean schema without re-importing runtime config: `import_env_into_system_settings` copies the CURRENT config into `settings`,
    // and config already holds the previous settings (SystemSettings::apply at boot). So: wipe, reboot the app on an empty DB, migrate.
    $this->freshSchema = function () {
        $this->artisan('db:wipe', ['--force' => true]);
        $this->refreshApplication();
        $this->artisan('migrate', ['--force' => true]);
    };
    ($this->freshSchema)();
    $this->seed(FoundationSeeder::class);
    $this->migration = require database_path('migrations/2027_01_12_000100_move_marketing_to_marketers.php');
});

it('moves marketing admins and their history to marketers and back', function () {
    $this->migration->down();   // state before the split: FKs point at admins, column created_by_admin_id
    expect(Schema::hasColumn('marketing_suggestions', 'created_by_admin_id'))->toBeTrue();

    // an admin who used the marketer panel (direct permission, guard admin) and authored rows; an admin who did not
    $perm = Permission::findOrCreate('ai_suggestions.view', 'admin');
    $staff = Admin::create(['name' => 'Mona', 'email' => 'mona@egy.test', 'phone' => '201099999999', 'password' => 'password', 'is_active' => true]);
    $staff->givePermissionTo($perm);
    $other = Admin::create(['name' => 'Ops', 'email' => 'ops@egy.test', 'phone' => '201088888888', 'password' => 'password', 'is_active' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $hash = $staff->getAuthPassword();

    $tokenId = DB::table('marketing_api_tokens')->insertGetId(['name' => 'T', 'token_prefix' => 'mkt_x', 'token_hash' => str_repeat('a', 64), 'scopes' => '[]', 'created_by' => $staff->id, 'created_at' => now(), 'updated_at' => now()]);
    $sid = DB::table('marketing_suggestions')->insertGetId([
        'type' => 'product', 'title' => 'S', 'payload' => '{}', 'content_hash' => str_repeat('b', 64), 'version' => 1, 'status' => 'approved', 'source' => 'manual',
        'created_by_admin_id' => $staff->id, 'approved_by' => $staff->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('marketing_suggestion_activity_logs')->insert(['suggestion_id' => $sid, 'action' => 'approved', 'actor_type' => 'admin', 'actor_id' => $staff->id, 'created_at' => now()]);

    $this->migration->up();

    $m = Marketer::where('migrated_from_admin_id', $staff->id)->firstOrFail();
    expect($m->phone)->toBe('201099999999')->and($m->getAuthPassword())->toBe($hash)->and($m->hasRole('marketer'))->toBeTrue()
        ->and(Marketer::where('migrated_from_admin_id', $other->id)->exists())->toBeFalse();
    // the super admin of the seed becomes a marketer-owner
    expect(Marketer::whereNotNull('migrated_from_admin_id')->get()->contains(fn ($x) => $x->isOwner()))->toBeTrue();

    expect(Schema::hasColumn('marketing_suggestions', 'created_by_marketer_id'))->toBeTrue()
        ->and(Schema::hasColumn('marketing_suggestions', 'created_by_admin_id'))->toBeFalse();
    $s = DB::table('marketing_suggestions')->find($sid);
    expect($s->created_by_marketer_id)->toBe($m->id)->and($s->approved_by)->toBe($m->id)
        ->and(DB::table('marketing_api_tokens')->where('id', $tokenId)->value('created_by'))->toBe($m->id);
    $log = DB::table('marketing_suggestion_activity_logs')->where('suggestion_id', $sid)->first();
    expect($log->actor_type)->toBe('marketer')->and($log->actor_id)->toBe($m->id);

    // admins lose the marketing-only permissions, keep the shared tools
    expect(Permission::where('guard_name', 'admin')->where('name', 'ai_suggestions.view')->exists())->toBeFalse()
        ->and(Permission::where('guard_name', 'admin')->where('name', 'banners.view')->exists())->toBeTrue()
        ->and(Permission::where('guard_name', 'marketer')->where('name', 'ai_suggestions.view')->exists())->toBeTrue();

    // FK now targets marketers: deleting the marketer nulls the column
    $m->delete();
    expect(DB::table('marketing_suggestions')->where('id', $sid)->value('approved_by'))->toBeNull();
});

it('rolls back to admin ids', function () {
    $this->migration->down();
    $staff = Admin::create(['name' => 'Mona', 'email' => 'mona@egy.test', 'phone' => '201099999999', 'password' => 'password', 'is_active' => true]);
    $staff->givePermissionTo(Permission::findOrCreate('ai_media.view', 'admin'));
    $sid = DB::table('marketing_suggestions')->insertGetId([
        'type' => 'product', 'title' => 'S', 'payload' => '{}', 'content_hash' => str_repeat('b', 64), 'version' => 1, 'status' => 'pending', 'source' => 'manual',
        'created_by_admin_id' => $staff->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->migration->up();
    $this->migration->down();
    expect(DB::table('marketing_suggestions')->where('id', $sid)->value('created_by_admin_id'))->toBe($staff->id)
        ->and(Schema::hasColumn('marketers', 'migrated_from_admin_id'))->toBeFalse()
        ->and(Marketer::where('phone', '201099999999')->exists())->toBeFalse();
    $this->migration->up();
});

// this file commits DDL + seed data: leave the database freshly migrated and EMPTY, so RefreshDatabase tests that run next in this
// process (parallel workers mix files) start from the same state as always
afterEach(function () {
    ($this->freshSchema)();
});
