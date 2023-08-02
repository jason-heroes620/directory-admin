<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\SubcategoriesController;
use App\Http\Controllers\SocialLinkTypesController;
use App\Http\Controllers\FiltersController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\EmailController;
use Illuminate\Support\Facades\Artisan;

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

    //storage link
    Route::get('/storageLink', function () {
        Artisan::call('storage:link');
    });

    // Partner interest
    Route::post('partnerInterest', [EmailController::class, 'partnerInterest']);
    Route::post('schoolInterest', [EmailController::class, 'schoolInterest']);

    Route::get('categories', [CategoriesController::class, 'categories']);
    Route::get('subcategories', [SubcategoriesController::class, 'subcategories']);
    Route::get('filters', [FiltersController::class, 'filters']);
    Route::get('socialLinkTypes', [SocialLinkTypesController::class, 'socialLinkTypes']);

    Route::get('schools/page/{page?}/search/{search?}', [SchoolsController::class, 'schools']);
    Route::get('schools/page/{page?}', [SchoolsController::class, 'schools']);
    Route::get('schoolById/{id?}', [SchoolsController::class, 'schoolById']);
    Route::post('schools/filters/page/{page?}', [SchoolsController::class, 'filters']);
    Route::post('schools/filters/page/{page?}/search/{search?}', [SchoolsController::class, 'filters']);

    Route::post('schools/addSchoolBasic', [SchoolsController::class, 'addSchoolBasic']);
    Route::post('schools/addSchoolDescription', [SchoolsController::class, 'addSchoolDescription']);
    Route::post('schools/addSchoolSocialLinks', [SchoolsController::class, 'addSchoolSocialLinks']);
    Route::post('schools/addSchoolLocation', [SchoolsController::class, 'addSchoolLocation']);
    Route::post('schools/addSchoolImages', [SchoolsController::class, 'addSchoolImages']);

    Route::get('locations', [LocationsController::class, 'locations']);
});
