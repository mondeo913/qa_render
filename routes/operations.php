<?php
use App\Http\Controllers\OperationsCenterController;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','permission:operations.view'])->prefix('operacion')->name('operations.')->group(function(){
 Route::get('/',[OperationsCenterController::class,'index'])->name('index');
 Route::get('/health',[OperationsCenterController::class,'health'])->name('health');
 Route::get('/incidentes',[OperationsCenterController::class,'incidents'])->name('incidents');
 Route::get('/respaldos',[OperationsCenterController::class,'backups'])->name('backups');
});
