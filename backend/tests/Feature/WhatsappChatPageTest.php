<?php

use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->super = CentralAdmin::where('is_super_admin', true)->first();
    $this->owner = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'o', 'email' => 'o@egy.test', 'password' => 'password', 'is_active' => true]);
    $this->owner->syncRoles('tenant-owner');
    $this->noPerm = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'n', 'email' => 'n@egy.test', 'password' => 'password', 'is_active' => true]);
});

it('renders the chat page for central admins', function () {
    $this->actingAs($this->super, 'central')->get('http://3alraf.test/admin/whatsapp/chat')
        ->assertOk()->assertSee('id="wa-chat"', false)->assertSee('whatsapp-chat.central', false);
});

it('renders the chat page for tenant admins with their own channel', function () {
    $this->actingAs($this->owner, 'tenant')->get('http://egy.3alraf.test/admin/whatsapp/chat')
        ->assertOk()->assertSee('whatsapp-chat.tenant.'.$this->tenant->id, false);
});

it('forbids admins without whatsapp.chat.view and redirects guests', function () {
    $this->actingAs($this->noPerm, 'tenant')->get('http://egy.3alraf.test/admin/whatsapp/chat')->assertForbidden();
    auth('tenant')->logout();
    $this->get('http://egy.3alraf.test/admin/whatsapp/chat')->assertRedirect();
});
