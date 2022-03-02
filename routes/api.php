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

Route::get('morning/head/{timestamp}', [UserController::class, 'morningHead']); //also for trying things
Route::get('playaround', [UserController::class, 'playaround']); //for trying things
Route::get('playaround/user/{userId}/getInnovations', [InnovationController::class, 'getInnovationsTest']);

Route::get('clarisaResults', [InnovationController::class, 'getClarisaResults']);


/*
//User calls and routes
*/
//Single user, CRUD
Route::post('user/{user_id}/new', [UserController::class, 'insertUser']);
Route::get('user/{user_id}/exists', [UserController::class, 'existsUser']);
Route::get('user/{user_id}/data', [UserController::class, 'getUser']);
Route::patch('user/{user_id}/update/role', [UserController::class, 'updateRoleUser']);

//Multiple users
Route::get('admin/{user_id}/users/data', [UserController::class, 'getUsers']);

//Admin calls and routes for user data
Route::patch('admin/{user_id}/update/permissions', [UserController::class, 'updatePermissionsUser']);
Route::get('admin/{user_id}/getReviewers', [UserController::class, 'getAllReviewers']);

/*
//Innovation calls and routes
*/
Route::post('innovation/insert', [InnovationController::class, 'insertInnovation']);
Route::post('innovation/{innovation_id}/updateVersion', [InnovationController::class, 'updateVersionInnovation']);
Route::get('user/{user_id}/getInnovations', [InnovationController::class, 'getAllUserInnovations']);
Route::patch('innovation/{innovation_id}/edit', [InnovationController::class, 'editInnovation']);
Route::patch('innovation/{innovation_id}/submit', [InnovationController::class, 'submitInnovation']);
Route::delete('innovation/{innovation_id}/delete/{user_id}', [InnovationController::class, 'deleteInnovation']);
Route::delete('innovation/{innovation_id}/deleteRejected/user/{user_id}/createdAt/{timestamp}', [InnovationController::class, 'deleteRejectedInnovation']);

//Admin calls and routes for innovation data
Route::get('admin/{user_id}/getInnovations', [InnovationController::class, 'getAllInnovations']);
Route::patch('admin/{user_id}/assignReviewer', [InnovationController::class, 'assignReviewer']);

//Reviewer calls and routes for innovation data
Route::get('user/{user_id}/getAssignedReviews', [InnovationController::class, 'getAssignedReviews']);
Route::patch('innovation/{innovation_id}/reject', [InnovationController::class, 'rejectInnovation']);
Route::patch('innovation/{innovation_id}/publish', [InnovationController::class, 'publishInnovation']);
