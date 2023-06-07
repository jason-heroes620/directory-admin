<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\FiltersController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\EmailController;
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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => 'XSS'], function () {
    // Partner interest
    Route::post('partnerInterest', [EmailController::class, 'partnerInterest']);

    Route::get('categories', [CategoriesController::class, 'categories']);
    Route::get('filters', [FiltersController::class, 'filters']);

    Route::get('schools', [SchoolsController::class, 'schools']);
    Route::get('schools/:id', [SchoolsController::class, 'getSchoolById']);
});
