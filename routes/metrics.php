<?php
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;
Route::get('/operacion/metrics',MetricsController::class)->name('operations.metrics');
