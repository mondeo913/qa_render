<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;
Route::middleware('guest')->group(function(){
    Route::get('/iniciar-sesion',[AuthController::class,'create'])->name('login');
    Route::post('/iniciar-sesion',[AuthController::class,'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/recuperar-contrasena',[PasswordResetController::class,'requestForm'])->name('password.request');
    Route::post('/recuperar-contrasena',[PasswordResetController::class,'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}',[PasswordResetController::class,'resetForm'])->name('password.reset');
    Route::post('/restablecer-contrasena',[PasswordResetController::class,'reset'])->name('password.update');
});
Route::post('/cerrar-sesion',[AuthController::class,'destroy'])->middleware('auth')->name('logout');
