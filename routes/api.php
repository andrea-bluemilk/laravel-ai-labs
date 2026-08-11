<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\UcpCheckoutController;
use App\Http\Middleware\VerifyUcpSignature;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/ucp')->middleware(VerifyUcpSignature::class)->group(function () {
    // Endpoint FASE A (Creazione Sessione)
    Route::post('/checkout', [UcpCheckoutController::class, 'createSession']);

    // Endpoint FASE C (Completamento e Pagamento)
    Route::post('/checkout/{sessionId}/complete', [UcpCheckoutController::class, 'completeSession']);
});

Route::prefix('v1/vapi')->group(function () {
    Route::post('/call/response', [\App\Http\Controllers\Api\V1\VapiController::class, 'handleWebhook']);
});