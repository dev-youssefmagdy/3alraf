<?php

namespace App\Http\Controllers\Auth;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

/** Admin panel login (/admin, guard `admin`, table `admins`). Flow: PanelLoginController. */
class AdminLoginController extends PanelLoginController
{
    protected function guard(): string
    {
        return 'admin';
    }

    protected function query(): Builder
    {
        return Admin::query();
    }

    protected function routePrefix(): string
    {
        return 'admin.';
    }

    protected function homeRoute(): string
    {
        return 'admin.welcome';
    }

    protected function panelLabel(): string
    {
        return __('admin.admin_panel');
    }
}
