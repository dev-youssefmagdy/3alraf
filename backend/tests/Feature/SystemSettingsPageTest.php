<?php

use App\Models\Admin;
use Database\Seeders\FoundationSeeder;

it('renders the system settings page, lists it in the sidebar and saves', function () {
    $this->seed(FoundationSeeder::class);
    $admin = Admin::query()->create(['name' => 'S', 'email' => 's@x.test', 'password' => 'password', 'is_active' => true]);
    $admin->assignRole('super-admin');
    $this->actingAs($admin, 'admin');

    $this->get('/admin/settings/system')->assertOk()->assertSee(route('admin.system-settings.edit'), false);
    $this->postJson('/admin/settings/system/validate', ['app_url' => 'bad'])->assertStatus(422);
});
