<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InnovationController;

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

//Fluff
Route::get('/kalhmera', function () {
    //return view('welcome');
    return "az023...";
});

Route::get('morning/head', [UserController::class, 'morningHead']); //also for trying things
Route::get('playaround', [UserController::class, 'playaround']); //for trying things
Route::get('playaround/user/{userId}/getInnovations', [InnovationController::class, 'getInnovationsTest']);


/*
//User calls and routes
*/
//Single user, CRUD
Route::post('user/{userId}/new', [UserController::class, 'insertUser']);
Route::get('user/{userId}/exists', [UserController::class, 'existsUser']);
Route::get('user/{userId}/data', [UserController::class, 'getUser']);
Route::patch('user/{userId}/update/role', [UserController::class, 'updateRoleUser']);

//Multiple users
Route::get('users/data', [UserController::class, 'getUsers']);

//Admin calls and routes for user data
Route::patch('admin/{userId}/update/permissions', [UserController::class, 'updatePermissionsUser']);
Route::get('admin/{userId}/getReviewers', [UserController::class, 'getAllReviewers']);

/*
//Innovation calls and routes
*/
Route::post('innovation/insert', [InnovationController::class, 'insertInnovation']);
Route::get('user/{userId}/getInnovations', [InnovationController::class, 'getAllUserInnovations']);
Route::delete('innovation/{innovId}/delete', [InnovationController::class, 'deleteInnovation']);
Route::patch('innovation/{innovId}/edit', [InnovationController::class, 'editInnovation']);
Route::patch('innovation/{innovId}/submit', [InnovationController::class, 'submitInnovation']);

//Admin calls and routes for innovation data
Route::get('admin/{userId}/getInnovations', [InnovationController::class, 'getAllInnovations']);
