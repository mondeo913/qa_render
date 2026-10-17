<?php

use App\Http\Controllers\Api\{EvidenceController, ImportController, QueryController, TokenController, WorkflowController};
use App\Http\Controllers\{AdminController, EvidenceWorkflowController, InstitutionalClosureController, ReportController};
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [TokenController::class, 'store'])->middleware('throttle:api-login')->name('auth.token');
    Route::middleware(['auth:sanctum', 'api.identity', 'throttle:siget-api'])->group(function () {
        Route::get('me', [TokenController::class, 'me'])->name('me');
        Route::delete('auth/token', [TokenController::class, 'destroy'])->name('auth.logout');
        Route::delete('auth/tokens', [TokenController::class, 'destroyAll'])->name('auth.logout-all');
        Route::get('agencies', [QueryController::class, 'agencies'])->name('agencies');
        Route::get('dashboard', [QueryController::class, 'dashboard'])->middleware('permission:scheduled_load.board')->name('dashboard');
        Route::get('calendar/events', [QueryController::class, 'calendar'])->middleware('permission:calendar.view')->name('calendar');
        Route::get('loads', [QueryController::class, 'loads'])->middleware('permission:scheduled_load.board')->name('loads.index');
        Route::get('loads/{load}', [QueryController::class, 'show'])->middleware('permission:scheduled_load.board')->name('loads.show');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::post('evidences', [EvidenceController::class, 'store'])->name('evidences.store');
        Route::get('evidences/{evidence}', [EvidenceController::class, 'show'])->middleware('permission:repository.view')->name('evidences.show');
        Route::post('evidences/{evidence}/submit', [EvidenceController::class, 'submit'])->name('evidences.submit');
        Route::post('evidences/{evidence}/reviews', [EvidenceController::class, 'review'])->name('evidences.review');
        Route::get('files/{file}/download', [EvidenceWorkflowController::class, 'download'])->middleware('permission:repository.download')->name('files.download');
        Route::post('loads/{load}/checklist', [WorkflowController::class, 'checklist'])->name('loads.checklist');
        Route::post('loads/{load}/signature-package', [InstitutionalClosureController::class, 'signaturePackage'])->name('loads.signature-package');
        Route::post('loads/{load}/signed-document', [WorkflowController::class, 'signedDocument'])->name('loads.signed-document');
        Route::post('loads/{load}/close', [WorkflowController::class, 'close'])->name('loads.close');
        Route::post('loads/{load}/corrections', [WorkflowController::class, 'corrections'])->name('loads.corrections');
        Route::post('loads/{load}/reopen', [WorkflowController::class, 'reopen'])->name('loads.reopen');
        Route::post('loads/{load}/reschedule', [WorkflowController::class, 'reschedule'])->name('loads.reschedule');
        Route::put('loads/{load}/fiscal-assignment', [WorkflowController::class, 'assign'])->name('loads.fiscal-assignment');
        Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
        Route::get('reports/xlsx', [ReportController::class, 'xlsx'])->name('reports.xlsx');
        Route::get('admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::patch('admin/users/{user}/status', [AdminController::class, 'toggleUser'])->name('admin.users.status');
        Route::get('admin/agencies', [AdminController::class, 'agencies'])->name('admin.agencies');
        Route::post('admin/agencies', [AdminController::class, 'storeAgency'])->name('admin.agencies.store');
        Route::put('admin/agencies/{agency}', [AdminController::class, 'updateAgency'])->name('admin.agencies.update');
        Route::delete('admin/agencies/{agency}', [AdminController::class, 'destroyAgency'])->name('admin.agencies.archive');
        Route::get('admin/roles', function () {
            return response()->json(['data' => \App\Models\Role::where('active', true)->whereNotIn('code', ['DIRECTOR', 'OPERADOR'])->orderBy('code')->get(['id', 'code', 'name'])]);
        })->middleware('permission:users.manage')->name('admin.roles');
    });
});
