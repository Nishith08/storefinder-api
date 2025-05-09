<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\StoreController;
use App\Http\Controllers\CityDataController;


Route::get('/stores', [StoreController::class, 'index']); 
Route::post('/stores', [StoreController::class, 'store']);
Route::get('/stores/search', [StoreController::class, 'search']);
Route::post('/find-stores', [StoreController::class, 'findNearbyStores']);
    //start of the data change routes
Route::get('/update-districts', [StoreController::class, 'updateDistrictsFromPincode']);// updates the stores district in db based on pincode
Route::get('/update-cities-from-stores', [CityDataController::class, 'updateMissingDistricts']);
    //end of the data change routes
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
