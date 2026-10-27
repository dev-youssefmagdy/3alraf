<?php

use App\Http\Controllers\Api\Integration\ApprovalProposalController;
use App\Http\Controllers\Api\Integration\EventController;
use App\Http\Controllers\Api\Integration\ExperimentResultController;
use App\Http\Controllers\Api\Integration\PingController;
use Illuminate\Support\Facades\Route;

// n8n integration API: /api/integration/v1 (bootstrap/app.php). Auth = bearer + HMAC (integration.auth:{scope}). NO price/refund/stock/budget endpoints exist.
Route::get('ping', PingController::class)->middleware('integration.auth')->name('ping');
Route::get('events', [EventController::class, 'index'])->middleware('integration.auth:events:read')->name('events.index');
Route::get('approvals/{id}', [ApprovalProposalController::class, 'show'])->middleware('integration.auth:read')->whereNumber('id')->name('approvals.show');
Route::post('proposals', [ApprovalProposalController::class, 'store'])->middleware('integration.auth:proposals:write')->name('proposals.store');
Route::post('experiments/{id}/results', [ExperimentResultController::class, 'store'])->middleware('integration.auth:ingest:write')->whereNumber('id')->name('experiments.results');
