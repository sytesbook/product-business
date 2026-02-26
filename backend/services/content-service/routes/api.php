<?php

use App\Http\Controllers\DomainsController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\RoutingTablesController;
use App\Http\Controllers\SitesController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => ['code' => 200]]);
});

Route::get('/routing-tables', [RoutingTablesController::class, 'index']);

Route::get('/sites', [SitesController::class, 'index']);
Route::post('/sites', [SitesController::class, 'store']);
Route::get('/sites/{siteUid}', [SitesController::class, 'show']);
Route::put('/sites/{siteUid}', [SitesController::class, 'update']);
Route::post('/sites/{siteUid}/activate', [SitesController::class, 'activate']);
Route::post('/sites/{siteUid}/deactivate', [SitesController::class, 'deactivate']);
Route::delete('/sites/{siteUid}', [SitesController::class, 'destroy']);

Route::get('/sites/{siteUid}/domains', [DomainsController::class, 'index']);
Route::post('/sites/{siteUid}/domains', [DomainsController::class, 'store']);
Route::get('/sites/{siteUid}/domains/{domainUid}', [DomainsController::class, 'show']);
Route::put('/sites/{siteUid}/domains/{domainUid}', [DomainsController::class, 'update']);
Route::post('/sites/{siteUid}/domains/{domainUid}/set-as-primary', [DomainsController::class, 'setAsPrimary']);
Route::delete('/sites/{siteUid}/domains/{domainUid}', [DomainsController::class, 'destroy']);

Route::get('/sites/{siteUid}/pages', [PagesController::class, 'index']);
Route::post('/sites/{siteUid}/pages', [PagesController::class, 'store']);
Route::get('/sites/{siteUid}/pages/{pageUid}', [PagesController::class, 'show']);
Route::put('/sites/{siteUid}/pages/{pageUid}', [PagesController::class, 'update']);
Route::delete('/sites/{siteUid}/pages/{pageUid}', [PagesController::class, 'destroy']);
