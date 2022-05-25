<?php

namespace App\Http\Controllers;

use App\Models\Innovation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;


class InnovationController extends Controller
{
    /*
    ////GET
    */
    //Get all innovations from the collection        {admin}
    public function  getAllInnovations($user_id)
    {
        //Validating the input
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Fetch all requested by administrator: ', [$user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }

        $innovations = Innovation::where('deleted', false)->get();
        Log::info('Retrieving all innovations ');
        if($innovations == null)
        {
            return response()->json(["result" => "ok", "innovations" => []], 201);
        }
        else{
            return response()->json(["result" => "ok", "innovations" => $innovations], 201);
        }
    }

    //Get all user innovations(latest version) from the collection     {user}
    public function  getAllUserInnovations($user_id)
    {
        //Find the distinct innovations of user_id
        $innovationsDistinct = Innovation::where('userIds' , $user_id)
            ->where('deleted', false)
            ->distinct('innovId')
            ->get();

        $innovations = array();
        //For every innovationId of the userId
        foreach ($innovationsDistinct as $singleInnovation)
        {
            //Ready, latest version
            $readyInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "READY")
                ->orderBy('version', 'desc')
                ->first();

            if($readyInnovations != null)
            {
                $innovations[] = $readyInnovations;
            }

            //Draft, latest version
            $draftInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "DRAFT")
                ->orderBy('version', 'desc')
                ->first();

            if($draftInnovations != null)
            {
                $innovations[] = $draftInnovations;
            }

            //Reviewer assignment, latest version
            $reviewerAssignmentInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "REVIEWER_ASSIGNMENT")
                ->orderBy('version', 'desc')
                ->first();

            if($reviewerAssignmentInnovations != null)
            {
                $innovations[] = $reviewerAssignmentInnovations;
            }

            //Rejected, latest version
            $rejectedInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "REJECTED")
                ->orderBy('version', 'desc')
                ->first();

            if($rejectedInnovations != null)
            {
                $innovations[] = $rejectedInnovations;
            }

            //Take final decision, latest version
            $finalDecisionInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "TAKE_FINAL_DECISION")
                ->orderBy('version', 'desc')
                ->first();

            if($finalDecisionInnovations != null)
            {
                $innovations[] = $finalDecisionInnovations;
            }

            //Published, latest version
            $publishedInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "PUBLISHED")
                ->orderBy('version', 'desc')
                ->first();

            if($publishedInnovations != null)
            {
                $innovations[] = $publishedInnovations;
            }

            //Under Review, latest version
            $underReviewInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "UNDER_REVIEW")
                ->orderBy('version', 'desc')
                ->first();

            if($underReviewInnovations != null)
            {
                $innovations[] = $underReviewInnovations;
            }

            //Revisions requested, latest version
            $revisionsRequestedInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "REVISIONS_REQUESTED")
                ->orderBy('version', 'desc')
                ->first();

            if($revisionsRequestedInnovations != null)
            {
                $innovations[] = $revisionsRequestedInnovations;
            }

            //Under sr assessment , latest version
            $underAssessmentInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "UNDER_SR_ASSESSMENT")
                ->orderBy('version', 'desc')
                ->first();

            if($underAssessmentInnovations != null)
            {
                $innovations[] = $underAssessmentInnovations;
            }

        }

        Log::info('Retrieving all user innovations ', [$user_id]);
        return response()->json(["result" => "ok", "innovations" => $innovations], 201);
    }

    //Get all the assigned for review innovations based on userId            {user, reviewer}
    public function getAssignedInnovations($user_id)
    {
        //Validating the input
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user has reviewer permissions
        $reviewer = User::find($user_id);
        if(in_array("Reviewer", $reviewer->permissions))
        {
            Log::info('Get assigned reviews requested by reviewer: ', [$user_id]);
        }
        else{
            Log::warning('User is not a reviewer: ', $reviewer->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User is not a reviewer'], 202);
        }
        $reviewerEnhanced = new stdClass();
        $reviewerEnhanced->reviewerId = $reviewer->userId;
        $reviewerEnhanced->fullName = $reviewer->fullName;

        $assignedReviews = Innovation::where('reviewers', $reviewerEnhanced)
                                    ->where('deleted', false)
                                    ->where('status', "UNDER_REVIEW")
                                    ->orderBy('version', 'desc')
                                    ->get();

        Log::info('Retrieving all innovations assigned for review', [$user_id]);
        if($assignedReviews == null)
        {
            return response()->json(["result" => "ok", "innovations" => []], 201);
        }
        else{
            return response()->json(["result" => "ok", "innovations" => $assignedReviews], 201);
        }
    }

    //Get all the assigned for review innovations based on userId            {user, reviewer}
    public function getSREAssignedInnovations($user_id)
    {
        //Validating the input
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user has reviewer permissions
        $sreUser = User::find($user_id);
        if(in_array("Scaling Readiness Expert", $sreUser->permissions))
        {
            Log::info('Get assigned innovations requested by scaling readiness expert: ', [$user_id]);
        }
        else{
            Log::warning('User is not a scaling readiness expert: ', $sreUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User is not a scaling readiness expert'], 202);
        }

        $sreEnhanced = new stdClass();
        $sreEnhanced->sreId = $sreUser->userId;
        $sreEnhanced->fullName = $sreUser->fullName;

        $assignedSRE = Innovation::where('scalingReadinessExpert', $sreEnhanced)
            ->where('deleted', false)
            ->where('status', "UNDER_SR_ASSESSMENT")
            ->orderBy('version', 'desc')
            ->get();

        Log::info('Retrieving all innovations assigned for scaling', [$user_id]);
        if($assignedSRE == null)
        {
            return response()->json(["result" => "ok", "innovations" => []], 201);
        }
        else{
            return response()->json(["result" => "ok", "innovations" => $assignedSRE], 201);
        }
    }

    function getAllPublishedInnovations()
    {

        //Find the distinct innovations of user_id
        $innovationsDistinct = Innovation::where('deleted', false)
            ->distinct('innovId')
            ->get();

        $innovationsWithNames= array();
        foreach($innovationsDistinct as $singleInnovation) {
            //Published, latest version
            $publishedInnovation = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "PUBLISHED")
                ->orderBy('version', 'desc')
                ->first();

            if ($publishedInnovation != null) {
                //$innovations[] = $publishedInnovations;
                $innovationEnhanced = new stdClass();
                $innovationEnhanced->innovation_id = $publishedInnovation->innovId;
                //$innovationEnhanced->name = $publishedInnovation->formData;
                foreach($publishedInnovation->formData as $singleField)
                {
                    if($singleField["id"] == "1.1")
                    {
                        $innovationEnhanced->name = $singleField["value"];
                    }
                }
                array_push($innovationsWithNames, $innovationEnhanced);
            }
        }

        return response()->json(["result" => "ok", "innovations" => $innovationsWithNames], 201);
    }


    /*
    ////POST
    */

    //Add a new innovation to the collection, given the userIds,status and formData    {user}
    public function insertInnovation(Request $request)
    {
        $innovation = new Innovation;
        $requestRules = array(
            'user_id' => 'required|string|numeric',
            'status' => [
                'required', Rule::in(['DRAFT','READY']),
            ],
        );

        //Validation and safety catches
        //for later(maybe)
        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $currentTime = round(microtime(true) * 1000);

        //generate uuid with INNOV- prefix included
        $uuid = Str::uuid()->toString();
        $innovation->innovId = 'INNOV-'.$uuid;

        //Data assignment from resource and local generation
        $innovation->userIds = [$request->user_id];
        $innovation->status = $request->status;                       //DRAFT || READY
        $innovation->version = 1;                                     //First time creating so version 1
        $innovation->persistId = [];                                  //Later will add hdl, empty for now
        $innovation->deleted = false;                                 //True for soft deleted innovations
        $innovation->reviewers = [];                                  //Array of objects, empty on initialise
        $innovation->scalingReadinessExpert = [];                     //Object, empty on initialise
        $innovation->comments = "";                                   //Reviewer's comments, empty on initialise
        $innovation->createdAt = $currentTime;                        //Date of creation
        $innovation->updatedAt = $currentTime;                        //Date of last update, same as creation at first
        $innovation->assignedAt = "";                                 //Date of assignment to reviewer
        $innovation->formData = $request->form_data;                  //The actual innovation data

        //Validation on the final user entities
        //for later

        //Save to database and log
        $innovation->save();
        Log::info('Adding new innovation with id: ', [$innovation->innovId, $request->toArray() ]);

        return response()->json(["result" => "ok"], 201);
    }

    //Update an PUBLISHED innovation to a higher version             {user}
    public function updateVersionInnovation(Request $request)
    {
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'form_data' => 'required|array',
            'version' => 'required|integer',
            'status' => [
                'required', Rule::in(['DRAFT','READY']),
            ],
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "PUBLISHED")
            ->where('version', $request->version)
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }


        //Check if user is an author
        if(in_array($request->user_id, $innovation->userIds))
        {
            Log::info('User has author privileges',[$request->user_id]);
        }
        else{
            Log::warning('User does not have author privileges', [$request->user_id]);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have author privileges'], 202);
        }

        $newVersion = $request->version +1;

        //Fetch possible existing DRAFT || READY of new version innovation
        $newVersionInnovations = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('version', $newVersion)
            ->where(function ($query) {
                $query->where('status', "DRAFT")->
                orWhere('status', "READY");
            })
            ->first();

        //If new versions already exist then return fail
        if($newVersionInnovations != null)
        {
            Log::warning('New version of innovation already existing in Draft or Ready status', [$newVersionInnovations]);
            return response()->json(["result" => "failed","errorMessage" => 'New version of innovation already existing in Draft or Ready status'], 202);
        }
        $currentTime = round(microtime(true) * 1000);

        //Data assignment from resource and local generation
        $innovation = new Innovation;
        $innovation->innovId = $request->innovation_id;
        $innovation->userIds = [$request->user_id];            //Array for migration and legacy reasons
        $innovation->status = $request->status;                //DRAFT || READY
        $innovation->version = $newVersion;                    //Updating so $request->version + 1
        $innovation->persistId = [];                           //Later will add hdl, empty for now
        $innovation->deleted = false;                          //True for soft deleted innovations
        $innovation->reviewers = [];                           //Array of objects, empty on initialise
        $innovation->scalingReadinessExpert = [];              //Object, empty on initialise
        $innovation->comments = " ";                           //Reviewer's comments, empty on initialise
        $innovation->createdAt = $currentTime;                 //Date of creation
        $innovation->updatedAt = $currentTime;                 //Date of last update, same as creation at first
        $innovation->assignedAt = "";                          //Date of assignment to reviewer
        $innovation->formData = $request->form_data;           //The actual innovation data

        //Save to database and log
        $innovation->save();
        Log::info('Adding updated innovation with version: ', [$innovation->version, $request->toArray() ]);
        return response()->json(["result" => "ok"], 201);
    }

    /*
    ////PUT || PATCH
    */

    //Edit an existing innovation, change on formData or status       {author, reviewer, scaling readiness expert}
    public function editInnovation(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'status' => [
                'required', Rule::in(['DRAFT','READY','REVISIONS_REQUESTED','UNDER_REVIEW','UNDER_SR_ASSESSMENT']),
            ],
        );
        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the innovation from the database (latest version for safety)
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "DRAFT")->
                orWhere('status', "READY")->
                orWhere('status', "UNDER_REVIEW")->
                orWhere('status', "REVISIONS_REQUESTED")->
                orWhere('status', "UNDER_SR_ASSESSMENT");
            })
            ->orderBy('version', 'desc')
            ->first();

        //Check if null was returned from the database
        if($innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check if user is an author or is assigned this innovation as a reviewer or sre
        $checkUser = User::find($request->user_id);
        if(in_array($request->user_id, $innovation->userIds))
        {
            Log::info('User has author privileges',[$request->user_id]);
        }
        elseif (in_array("Reviewer", $checkUser->permissions) && $innovation->status == "UNDER_REVIEW")
        {
            Log::info('User has reviewer privileges: ', [$request->user_id]);
            //Check innovation has been assigned to reviewer with user_id
            if(in_array(["reviewerId" => $checkUser->userId, "fullName" => $checkUser->fullName], $innovation->reviewers))
            {
                Log::info('Requested user has been assigned this innovation: ', [$request->user_id]);
            }
            else{
                Log::warning('Requested user has not been assigned this innovation: ', [$request->user_id]);
                return response()->json(["result" => "failed", "reviewer" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
            }
        }
        elseif (in_array("Scaling Readiness Expert", $checkUser->permissions) && $innovation->status == "UNDER_SR_ASSESSMENT")
        {
            Log::info('User has scaling readiness expert privileges: ', [$checkUser->userId]);
            //Check innovation has been assigned to scaling readiness expert with user_id
            if($checkUser->userId == $innovation->scalingReadinessExpert["sreId"])
            {
                Log::info('Requested user has been assigned this innovation: ', [$checkUser->userId]);
            }
            else{
                Log::warning('Requested user has not been assigned this innovation: ', [$checkUser->userId]);
                return response()->json(["result" => "failed", "sre" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
            }
        }
        else{
            Log::warning('User does not have required privileges', [$checkUser->user_id]);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
        }

        //Patch the innovation data
        $innovation->formData = $request->form_data;
        $innovation->status = $request->status;
        $innovation->updatedAt = round(microtime(true) * 1000);

        //Save, log and response
        $innovation->save();
        Log::info('Updating innovation', [$innovation]);
        return response()->json(["result" => "ok"], 201);
    }

    //Submit an innovation that has status READY                           {user}
    public function submitInnovation(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the innovation from the database (latest version for safety)
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "DRAFT")->
                orWhere('status', "READY");
            })
            ->orderBy('version', 'desc')
            ->first();

        //Check for null and innovation status
        if($innovation ==null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }
        if($innovation->status != "READY")
        {
            Log::warning('Requested innovation can not be submitted', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation can not be submitted'], 202);
        }
        //Check if user is an author
        if(in_array($request->user_id, $innovation->userIds))
        {
            Log::info('User has author privileges',[$request->user_id]);
        }
        else{
            Log::warning('User does not have author privileges', [$request->user_id]);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have author privileges'], 202);
        }

        $innovation->status = "REVIEWER_ASSIGNMENT";
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Submitting innovation', [$innovation]);

        return redirect()->route('notifyUser', [ 'innovation_id' => $request->innovation_id, 'workflow_state' => 1, 'user_id' => $request->user_id, 'title' => ""]);
        //return redirect()->action([WorkflowNotificationsController::class, 'sendNotificationEmail']);
        //return response()->json(["result" => "ok"], 201);
    }


    //Assign a reviewer to an innovation with status SUBMITTED based on reviewer_id given              {admin}
    public function assignReviewers(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'reviewer_ids' => 'required|array',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }


        //Check user is admin
        $adminUser = User::find($request->user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Assign reviewer requested by administrator: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }

        //Check users are reviewers and construct the 'reviewers' property
        $reviewersWithNames = array();
        foreach ($request->reviewer_ids as $singleReviewer)
        {
            $reviewUser = User::find($singleReviewer);
            if($reviewUser == null)
            {
                Log::warning("User does not exist", [$singleReviewer]);
                return response()->json(["result" => "failed","errorMessage" => "User does not exist"], 400);
            }
            if(in_array("Reviewer", $reviewUser->permissions))
            {
                Log::info('Requested user has Reviewer permissions: ', [$request->reviewer_ids]);
            }
            else{
                Log::warning('User does not have reviewer privileges: ', $reviewUser->permissions);
                return response()->json(["result" => "failed","errorMessage" => 'User does not have reviewer privileges'], 202);
            }
            $reviewerEnhanced = new stdClass();
            $reviewerEnhanced->reviewerId = $reviewUser->userId;
            $reviewerEnhanced->fullName = $reviewUser->fullName;
            array_push($reviewersWithNames, $reviewerEnhanced);

        }

        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "REVIEWER_ASSIGNMENT")
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        $currentTime = round(microtime(true) * 1000);
        $innovation->reviewers = $reviewersWithNames;
        $innovation->status = "UNDER_REVIEW";
        $innovation->updatedAt = $currentTime;
        $innovation->assignedAt = $currentTime;
        $innovation->save();
        Log::info('Assigning innovation to reviewer ', [$innovation]);
        return redirect()->route('notifyUser', [ 'innovation_id' => $request->innovation_id, 'workflow_state' => 3, 'user_id' => $reviewUser->userId, 'title' => ""]);

        //return response()->json(["result" => "ok"], 201);
    }

    //Assign a scaling readiness expert to an innovation with status TAKE_FINAL_DECISION based on reviewer_id given              {admin}
    public function assignScalingReadinessExpert(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'sre_id' => 'required|exists:App\Models\User,userId|string|numeric'
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($request->user_id);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Assign scaling readiness expert requested by administrator: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have administrator privileges: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator privileges'], 202);
        }

        //Check target user is sre and construct the scalingReadinessExpert property
        $sreUser = User::find($request->sre_id);
        if(in_array("Scaling Readiness Expert", $sreUser->permissions))
        {
            Log::info('Requested user has Reviewer permissions: ', [$request->sre_id]);
        }
        else{
            Log::warning('User does not have reviewer privileges: ', $sreUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have reviewer privileges'], 202);
        }
        $sreEnhanced = new stdClass();
        $sreEnhanced->sreId = $sreUser->userId;
        $sreEnhanced->fullName = $sreUser->fullName;
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "TAKE_FINAL_DECISION")
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        $currentTime = round(microtime(true) * 1000);
        $innovation->scalingReadinessExpert = $sreEnhanced;
        $innovation->status = "UNDER_SR_ASSESSMENT";
        $innovation->updatedAt = $currentTime;
        $innovation->assignedAt = $currentTime;
        $innovation->save();
        Log::info('Assigning innovation to scaling readiness expert ', [$innovation]);
        return redirect()->route('notifyUser', [ 'innovation_id' => $request->innovation_id, 'workflow_state' => 5, 'user_id' => $sreUser->userId, 'title' => ""]);

        //return response()->json(["result" => "ok"], 201);
    }

    //Add comments to an innovation that is under review              {reviewer}
    public function addComment(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'comments' => 'present|nullable|string',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is reviewer
        $reviewUser = User::find($request->user_id);
        if(in_array("Reviewer", $reviewUser->permissions))
        {
            Log::info('Requested user has Reviewer permissions: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have reviewer privileges: ', $reviewUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have reviewer privileges'], 202);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "UNDER_REVIEW")
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check innovation has been assigned to reviewer with user_id
        if(in_array(["reviewerId" => $reviewUser->userId, "fullName" => $reviewUser->fullName], $innovation->reviewers))
        {
            Log::info('Requested user has been assigned this innovation: ', [$request->user_id]);
        }
        else{
            Log::warning('Requested user has not been assigned this innovation: ', [$request->user_id]);
            return response()->json(["result" => "failed", "reviewer" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
        }

        //Add comments, log and return response
        $innovation->comments = $request->comments;
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Adding comments to under review innovation ', [$innovation]);
        return response()->json(["result" => "ok"], 201);
    }

    //Request revision for an innovation                            {reviewer}
    public function requestRevisionInnovation(Request $request)
    {
        //Request validation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'comments' => 'present|nullable|string',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is reviewer
        $reviewUser = User::find($request->user_id);
        if(in_array("Reviewer", $reviewUser->permissions))
        {
            Log::info('Requested user has reviewer rights: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have reviewer privileges: ', $reviewUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have reviewer privileges'], 202);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "UNDER_REVIEW")
            ->orderBy('version', 'desc')
            ->first();
        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check innovation has been assigned to reviewer with user_id
        if(in_array(["reviewerId" => $reviewUser->userId, "fullName" => $reviewUser->fullName], $innovation->reviewers))
        {
            Log::info('Requested user has been assigned this innovation: ', [$request->user_id]);
        }
        else{
            Log::warning('Requested user has not been assigned this innovation: ', [$request->user_id]);
            return response()->json(["result" => "failed", "reviewer" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
        }

        //Update innovation, log and return response
        $innovation->comments = $request->comments;
        $innovation->status = "REVISIONS_REQUESTED";
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Requesting revisions and updating comments ', [$innovation]);

        //Find the innovation name, used in email
        foreach ($innovation->formData as $singleField)
        {
            if($singleField["id"] == "1.1")
            {
                $innovationName = $singleField["value"];
            }
        }

        return redirect()->route('notifyUser', [ 'innovation_id' => $request->innovation_id, 'workflow_state' => 6, 'user_id' => $request->user_id, 'title' => $innovationName]);

        //return response()->json(["result" => "ok"], 201);
    }

    //Reject a submitted innovation                                   {admin}
    public function rejectInnovation(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric'
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is reviewer
        $checkUser = User::find($request->user_id);
        if(in_array("Administrator", $checkUser->permissions))
        {
            Log::info('Requested user has admin rights: ', [$request->user_id]);
        }
        else{
            Log::warning('User does not have admin privileges: ', $checkUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have admin privileges'], 202);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "TAKE_FINAL_DECISION")
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Update innovation, log and return response
        $innovation->status = "REJECTED";
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Rejecting innovation  ', [$innovation]);
        return response()->json(["result" => "ok"], 201);
    }

    //Approve a submitted innovation for the final decision         {author, reviewer}
    public function approveInnovation(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "UNDER_REVIEW")->
                orWhere('status', "REVISIONS_REQUESTED");
            })
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check user is reviewer
        $reviewUser = User::find($request->user_id);
        if(in_array($request->user_id, $innovation->userIds))
        {
            Log::info('User has author privileges',[$request->user_id]);
        }
        elseif (in_array("Reviewer", $reviewUser->permissions))
        {
            Log::info('Requested user has reviewer privileges: ', [$request->user_id]);
            //Check innovation has been assigned to reviewer with user_id
            if(in_array(["reviewerId" => $reviewUser->userId, "fullName" => $reviewUser->fullName], $innovation->reviewers))
            {
                Log::info('Requested user has been assigned this innovation: ', [$request->user_id]);
            }
            else{
                Log::warning('Requested user has not been assigned this innovation: ', [$request->user_id]);
                return response()->json(["result" => "failed", "reviewer" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
            }
        }
        else{
            Log::warning('User does not have reviewer rights: ', $reviewUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
        }

        //Update innovation, log and return response
        $innovation->status = "TAKE_FINAL_DECISION";
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Approving innovation for final decision', [$innovation]);

        return redirect()->route('notifyUser', [ 'innovation_id' => $request->innovation_id, 'workflow_state' => 4, 'user_id' => env('ADMIN_USER', ''), 'title' => ""]);

        //return response()->json(["result" => "ok"], 201);
    }

    //Publish a submitted innovation                                {admin, scaling readiness expert}
    public function publishInnovation(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "TAKE_FINAL_DECISION")->
                orWhere('status', "UNDER_SR_ASSESSMENT");
            })
            ->orderBy('version', 'desc')
            ->first();

        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check user is admin or scaling readiness expert
        $checkUser = User::find($request->user_id);
        if(in_array("Administrator", $checkUser->permissions))
        {
            Log::info('Requested user has admin privileges: ', [$request->user_id]);
        }
        elseif (in_array("Scaling Readiness Expert", $checkUser->permissions))
        {
            Log::info('User has scaling readiness expert privileges: ', [$checkUser->userId]);
            //Check innovation has been assigned to scaling readiness expert with user_id
            if($checkUser->userId == $innovation->scalingReadinessExpert["sreId"])
            {
                Log::info('Requested user has been assigned this innovation: ', [$checkUser->userId]);
            }
            else{
                Log::warning('Requested user has not been assigned this innovation: ', [$checkUser->userId]);
                return response()->json(["result" => "failed", "sre" => $request->user_id, "errorMessage" => 'Requested user has not been assigned this innovation'], 202);
            }
        }
        else{
            Log::warning('User does not have required privileges: ', $checkUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
        }

        //Update innovation, log and reroute to elasticsearch publication
        $innovation->status = "PUBLISHED";
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();
        Log::info('Publishing innovation, will also add it to elastic', [$innovation]);



        return redirect()->route('elasticSearchPublish', [ 'innovation_id' => $request->innovation_id]);
        //return response()->json(["result" => "ok"], 201);
    }


    /*
    ////DELETE
    */

    //Delete an innovation with status DRAFT || READY                 {user, admin}
    public function deleteInnovation($innovation_id, $user_id)
    {
        //TODO: check its the latest version
        //Validation
        $validator = Validator::make(["innovation_id" => $innovation_id], [
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        $innovation = Innovation::where('innovId', $innovation_id)
                                ->where('deleted', false)
                                ->where(function ($query) {
                                    $query->where('status', "DRAFT")->
                                    orWhere('status', "READY")->
                                    orWhere('status', "REVIEWER_ASSIGNMENT")->
                                    orWhere('status', "TAKE_FINAL_DECISION")->
                                    orWhere('status', "REVISIONS_REQUESTED")->
                                    orWhere('status', "UNDER_REVIEW")->
                                    orWhere('status', "UNDER_SR_ASSESSMENT");
                                    })
                                ->first();

        //Check if null was returned from the database
        if($innovation == null)
        {
            Log::warning('Requested innovation not found', [$innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check if user has delete privileges (owns the innovation || admin)
        $user = User::find($user_id);
        Log::info('Attempting to delete innovation', [$innovation]);
        if(in_array($user->userId, $innovation->userIds))
        {
            Log::info('User has delete privileges',[$user->userId]);
        }
        else{
            if(in_array("Administrator", $user->permissions))
            {
                Log::info('User has delete privileges',[$user->userId]);
            }
            else{
                Log::warning('User does not have required privileges', [$user->userId]);
                return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
            }

        }

        Log::info('Deleting innovation', [$innovation]);
        $innovation->deleted = true;
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();

        return response()->json(["result" => "ok"], 201);
    }

    //Delete an innovation with status REJECTED based on createdAt attribute      {user, admin}
    public function deleteRejectedInnovation($innovation_id, $user_id, $created_at)
    {
        //Validation on input parameters
        //Validation on innovation_id
        $validator = Validator::make(["innovation_id" => $innovation_id], [
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $innovation_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }
        //Validation on user_id
        $validator = Validator::make(["user_id" => $user_id], [
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $user_id]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the requested innovation
        $innovation = Innovation::where('innovId', $innovation_id)
            ->where('deleted', false)
            ->where('status', "REJECTED")
            ->where('createdAt', (int)$created_at)
            ->first();



        //Check if user has delete privileges (owns the innovation || admin)
        $user = User::find($user_id);
        Log::info('Attempting to delete innovation', [$innovation]);
        if(in_array($user->userId, $innovation->userIds))
        {
            Log::info('User has delete privileges',[$user->userId]);
        }
        else{
            if(in_array("Administrator", $user->permissions))
            {
                Log::info('User has delete privileges',[$user->userId]);
            }
            else{
                Log::warning('User does not have required privileges', [$user->userId]);
                return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
            }

        }

        Log::info('Deleting rejected innovation', [$innovation]);
        $innovation->deleted = true;
        $innovation->updatedAt = round(microtime(true) * 1000);
        $innovation->save();

        return response()->json(["result" => "ok"], 201);
    }

}
