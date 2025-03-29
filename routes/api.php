<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
 
use App\Http\Controllers\DomainController;

Route::post('delpoy/create-sub-domain', [DomainController::class, 'createSubDomain']);
Route::post('delpoy/update-sub-domain', [DomainController::class, 'updateSubDomain']);
Route::get('delpoy/all', [DomainController::class, 'get']);

Route::post('delpoy/create-domain', [DomainController::class, 'create']);
Route::post('delpoy/update-domain', [DomainController::class, 'update']);
Route::get('delpoy/test-ssl/{domain}', [DomainController::class, 'testSSL']);
Route::post('delpoy/regenerate-ssl', [DomainController::class, 'regenerateSSL']);
Route::get('delpoy/check-domain/{domain}', [DomainController::class, 'checkDomain']);