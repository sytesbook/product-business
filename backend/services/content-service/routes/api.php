<?php

use App\Http\Controllers\RoutingTablesController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => ['code' => 200]]);
});

Route::get('/routing-tables', [RoutingTablesController::class, 'index']);
