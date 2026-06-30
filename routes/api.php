<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/biometric/punch', 'App\Http\Controllers\Api\BiometricPunchController@webhook')
    ->withoutMiddleware('auth:api')
    ->name('biometric.punch');

Route::get('/biometric/status', 'App\Http\Controllers\Api\BiometricPunchController@status')
    ->withoutMiddleware('auth:api')
    ->name('biometric.status');
