<?php

use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\MasscollectDomainController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\VirtualAccountController;
use Illuminate\Support\Facades\Route;

/*
| API dla usług dpay: manager, eid, esim. Uwierzytelnienie kluczem API
| konsumenta - ten sam wzorzec, co w dpay-web-eid.
*/
Route::prefix('v1')
    ->middleware(['force.json', 'resolve.tenant.api', 'throttle:api'])
    ->group(function () {
        Route::get('/bank-accounts', [BankAccountController::class, 'index']);
        Route::get('/bank-accounts/{iban}', [BankAccountController::class, 'show']);

        Route::get('/domains', [MasscollectDomainController::class, 'index']);

        Route::post('/virtual-accounts', [VirtualAccountController::class, 'store']);
        Route::get('/virtual-accounts', [VirtualAccountController::class, 'index']);
        Route::get('/virtual-accounts/{id}', [VirtualAccountController::class, 'show']);
        Route::delete('/virtual-accounts/{id}', [VirtualAccountController::class, 'destroy']);
        Route::get('/virtual-accounts/{id}/payments', [VirtualAccountController::class, 'payments']);

        Route::post('/reconciliations', [ReconciliationController::class, 'store']);
    });
