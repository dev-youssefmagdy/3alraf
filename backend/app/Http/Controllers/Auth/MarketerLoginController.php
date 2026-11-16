<?php

namespace App\Http\Controllers\Auth;

use App\Models\Marketer;
use Illuminate\Database\Eloquent\Builder;

/** Marketer panel login (/marketer, guard `marketer`, table `marketers`). Flow: PanelLoginController. */
class MarketerLoginController extends PanelLoginController
{
    protected function guard(): string
    {
        return Marketer::GUARD;
    }

    protected function query(): Builder
    {
        return Marketer::query();
    }

    protected function routePrefix(): string
    {
        return 'marketer.';
    }

    protected function homeRoute(): string
    {
        return 'marketer.dashboard';
    }

    protected function panelLabel(): string
    {
        return __('marketing.panel');
    }
}
