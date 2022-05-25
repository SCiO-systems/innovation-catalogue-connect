<?php


use App\Http\Controllers\WorkflowNotificationsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InnovationController;
use App\Http\Controllers\RtbController;
use App\Http\Controllers\OfflinePopulationController;
use App\Http\Controllers\ClarisaVocabulariesController;
use App\Http\Controllers\ElasticPopulationController;

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

/*
//Special calls and routes
*/
Route::get('morning/head/{timestamp}', [UserController::class, 'morningHead']);         //also for trying things

//Clarisa vocabularies
Route::get('clarisaResults', [ClarisaVocabulariesController::class, 'getClarisaResults']);
Route::post('autocompleteOrganization', [ClarisaVocabulariesController::class, 'autocompleteOrganization']);
//Populate Users calls and routes
Route::post('populateUsers', [OfflinePopulationController::class, 'populateUsers']);
//Get all published innovations
Route::get('allPublishedInnovations', [InnovationController::class, 'getAllPublishedInnovations']);
//Elastic calls
//Get is used here by exception, rerouted through publishInnovation
Route::get('innovation/{innovation_id}/publishedToElastic', [ElasticPopulationController::class, 'publishToElastic'])->name('elasticSearchPublish');
Route::get('elasticMigration', [ElasticPopulationController::class, 'migrateToMongo']);
//Approval Notification calls and routes
Route::get('workflowNotification/{innovation_id}/{workflow_state}/{user_id}/{title}', [WorkflowNotificationsController::class, 'sendNotificationEmail'])->name('notifyUser');


/*
//User calls and routes
*/
//Single user, CRUD
Route::post('user/{user_id}/new', [UserController::class, 'insertUser']);
Route::get('user/{user_id}/exists', [UserController::class, 'existsUser']);
Route::get('user/{user_id}/data', [UserController::class, 'getUser']);
Route::patch('user/{user_id}/edit', [UserController::class, 'editUser']);

//Special calls
Route::post('user/name/autocomplete', [UserController::class, 'autocompleteUsers']);

//Admin calls and routes for user data
Route::patch('admin/{user_id}/update/permissions', [UserController::class, 'updatePermissionsUser']);
Route::get('admin/{user_id}/getReviewers', [UserController::class, 'getAllReviewers']);
Route::get('admin/{user_id}/getSRE', [UserController::class, 'getAllScalingReadinessExperts']);
Route::post('admin/{user_id}/users/dataPaginated', [UserController::class, 'getUsersPaginated']);

/*
//Innovation calls and routes
*/
Route::post('innovation/insert', [InnovationController::class, 'insertInnovation']);
Route::post('innovation/{innovation_id}/updateVersion', [InnovationController::class, 'updateVersionInnovation']);
Route::get('user/{user_id}/getInnovations', [InnovationController::class, 'getAllUserInnovations']);
Route::patch('innovation/{innovation_id}/edit', [InnovationController::class, 'editInnovation']);
Route::patch('innovation/{innovation_id}/submit', [InnovationController::class, 'submitInnovation']);
Route::delete('innovation/{innovation_id}/delete/{user_id}', [InnovationController::class, 'deleteInnovation']);
Route::delete('innovation/{innovation_id}/deleteRejected/user/{user_id}/createdAt/{created_at}', [InnovationController::class, 'deleteRejectedInnovation']);

//Admin calls and routes for innovation data
Route::get('admin/{user_id}/getInnovations', [InnovationController::class, 'getAllInnovations']);
Route::patch('admin/{user_id}/assignReviewers', [InnovationController::class, 'assignReviewers']);
Route::patch('admin/{user_id}/assignSRE', [InnovationController::class, 'assignScalingReadinessExpert']);

//Reviewer calls and routes for innovation data
Route::get('reviewer/{user_id}/getAssignedInnovations', [InnovationController::class, 'getAssignedInnovations']);
Route::patch('innovation/{user_id}/addComment', [InnovationController::class, 'addComment']); //maybe change this
Route::patch('innovation/{innovation_id}/revision', [InnovationController::class, 'requestRevisionInnovation']);
Route::patch('innovation/{innovation_id}/reject', [InnovationController::class, 'rejectInnovation']);
Route::patch('innovation/{innovation_id}/approve', [InnovationController::class, 'approveInnovation']);
Route::patch('innovation/{innovation_id}/publish', [InnovationController::class, 'publishInnovation']);

//SRE calls and routes for innovation data
Route::get('sre/{user_id}/getAssignedInnovations', [InnovationController::class, 'getSREAssignedInnovations']);

/*
//RTB Search routes and calls
*/
route::post('rtb-search', [RtbController::class, 'rtb_search']);
route::post('rtb-retrieveByTitle', [RtbController::class, 'rtb_retrievedocument_by_title']);


route::post('retrievedocument', [RtbController::class, 'rtb_retrieve_document']);
/*Route::group(
    ['middleware' => 'jwt'], function () {
    route::post('retrievedocument', [RtbController::class, 'rtb_retrieve_document']);
    });*/
