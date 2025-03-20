<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
 
use App\Http\Controllers\SubdomainController;

Route::post('delpoy/create', [SubdomainController::class, 'create']);
Route::post('delpoy/update', [SubdomainController::class, 'update']);
Route::get('delpoy/all', [SubdomainController::class, 'get']);
