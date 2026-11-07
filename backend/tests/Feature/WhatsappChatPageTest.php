<?php

use App\Models\Admin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->super = Admin::where('is_super_admin', true)->first();
    $this->owner = Admin::query()->create(['name' => 'o', 'email' => 'o@egy.test', 'password' => 'password', 'is_active' => true]);
    $this->owner->syncRoles('owner');
    $this->noPerm = Admin::query()->create(['name' => 'n', 'email' => 'n@egy.test', 'password' => 'password', 'is_active' => true]);
});

it('renders the chat page on the single whatsapp-chat channel for the super admin and the owner', function () {
    foreach ([$this->super, $this->owner] as $admin) {
        $html = $this->actingAs($admin, 'admin')->get('/admin/whatsapp/chat')->assertOk()->assertSee('id="wa-chat"', false)->getContent();
        expect($html)->toContain('whatsapp-chat')->not->toContain('whatsapp-chat.tenant')->not->toContain('whatsapp-chat.central');
    }
});

it('forbids admins without whatsapp.chat.view and redirects guests', function () {
    $this->actingAs($this->noPerm, 'admin')->get('/admin/whatsapp/chat')->assertForbidden();
    auth('admin')->logout();
    $this->get('/admin/whatsapp/chat')->assertRedirect();
});
