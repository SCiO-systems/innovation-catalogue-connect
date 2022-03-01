<?php

namespace App\Http\Controllers;

use App\Models\Innovation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class InnovationController extends Controller
{
    /*
    ////GET
    */
    //Get all innovations from the collection        {admin}
    public function  getAllInnovations($userId)
    {
        //Validating the input
        $validator = Validator::make(["userId" => $userId], [
            'userId' => 'required|exists:App\Models\User,userId|string|numeric',
        ]);
        if ($validator->fails()) {
            Log::error('Resource Validation Failed: ', [$validator->errors(), $userId]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Check user is admin
        $adminUser = User::find($userId);
        if(in_array("Administrator", $adminUser->permissions))
        {
            Log::info('Fetch all requested by administrator: ', [$userId]);
        }
        else{
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }

        $innovations = Innovation::where('deleted', false)->get();
        Log::info('Retrieving all innovations ');
        return response()->json(["result" => "ok", "innovations" => $innovations], 201);
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

            //Accepted, latest version
            $acceptedInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "ACCEPTED")
                ->orderBy('version', 'desc')
                ->first();

            if($acceptedInnovations != null)
            {
                $innovations[] = $acceptedInnovations;
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

            //Submitted, latest version
            $submittedInnovations = Innovation::whereIn('innovId', $singleInnovation)
                ->where('deleted', false)
                ->where('status', "SUBMITTED")
                ->orderBy('version', 'desc')
                ->first();

            if($submittedInnovations != null)
            {
                $innovations[] = $submittedInnovations;
            }


        }

        Log::info('Retrieving all user innovations ', [$user_id]);
        return response()->json(["result" => "ok", "innovations" => $innovations], 201);
    }

    //Get all the assigned for review innovations based on userId            {user, reviewer}
    public function getAssignedReviews($user_id)
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

        $assignedReviews = Innovation::where('reviewerIds', $user_id)
                                    ->where('deleted', false)
                                    ->where('status', "SUBMITTED")
                                    ->orderBy('version', 'desc')
                                    ->first();

        Log::info('Retrieving all innovations assigned for review', [$user_id]);
        if($assignedReviews == null)
        {
            return response()->json(["result" => "ok", "innovations" => []], 201);
        }
        else{
            return response()->json(["result" => "ok", "innovations" => $assignedReviews], 201);
        }
    }

    //Clarisa vocabulary results
    public function getClarisaResults()
    {
        $result = Http::redisFetch();
        $usefulHeaders = array("clarisa_technical_field",
            "clarisa_business_category",
            "clarisa_beneficiaries",
            "clarisa_users",
            "clarisa_investment_type",
            "clarisa_action_areas",
            "clarisa_innovation_readiness_levels",
            "clarisa_governance_type",
            "clarisa_environmental_benefits",
            "clarisa_administrative_scale",
            "clarisa_innovation_type",
            "clarisa_countries",
            "clarisa_technology_development_stage"
        );


        $vocabToArray = (array)$result;
        $clarisa_vocabulary = array();

        //Cases with { id , value}
        foreach ($usefulHeaders as $header)
        {
            //Log::info("HERE'S THE VOCAB", $vocabToArray[$header]);
            $value = array();
            foreach ($vocabToArray[$header] as $fields)
            {
                if(strcmp($header, "clarisa_administrative_scale") == 0 || strcmp($header, "clarisa_innovation_type") == 0)
                {
                    $valueProperty = array("id" => $fields->code, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_countries") == 0)
                {
                    $valueProperty = array("id" => $fields->isoAlpha2, "value" => $fields->name);
                }
                elseif (strcmp($header, "clarisa_technology_development_stage") == 0)
                {
                    $valueProperty = array("id" => $fields->id, "value" => $fields->officialCode." - ".$fields->name);
                }
                else{
                    $valueProperty = array("id" => $fields->id, "value" => $fields->name);
                }
                array_push($value, $valueProperty);

            }
            $singleHeader = array("header" => $header, "value" => $value);
            array_push($clarisa_vocabulary, $singleHeader);
        }

        //Cases with objects in value
        //TODO: titles could be done with less code (use the key as the value)
        $sdgTargetPropertiesNames = array();
        foreach ($vocabToArray["clarisa_sdg_targets"] as $sdgProperties) //sdgProperties is object
        {
            $sdgTargetPropertiesValues[$sdgProperties->sdg->usndCode][] = array("id" => $sdgProperties->id , "value" => $sdgProperties->sdgTargetCode." - ".$sdgProperties->sdgTarget);
            if(!isset($sdgTargetPropertiesNames[$sdgProperties->sdg->usndCode]))
            {
                $sdgTargetPropertiesNames[$sdgProperties->sdg->usndCode][] = $sdgProperties->sdg->fullName;
            }
        }

        $sdgTargetValue = array();
        foreach ($sdgTargetPropertiesValues as $targetId => $targets) //$targets is array of sdg_target objects
        {
            $singleTarget = array("id" => $targetId, "title" => $sdgTargetPropertiesNames[$targetId][0], "value" => $targets);
            array_push($sdgTargetValue, $singleTarget);
        }
        $singleHeader = array("header" => "clarisa_sdg_targets", "value" => $sdgTargetValue);
        array_push($clarisa_vocabulary, $singleHeader);


        $indicatorTitles = array();
        foreach ($vocabToArray["clarisa_impact_areas_indicators"] as $indicatorProperties)
        {
            $singleIndicatorPropertiesValues[$indicatorProperties->impactAreaId][] = array("id" => $indicatorProperties->indicatorId, "value" => $indicatorProperties->indicatorStatement);
            if(!isset($indicatorTitles[$indicatorProperties->impactAreaId]))
            {
                $indicatorTitles[$indicatorProperties->impactAreaId][] = $indicatorProperties->impactAreaName;
            }
        }

        $impactAreaIndicatorValue = array();
        foreach ($singleIndicatorPropertiesValues as $impactAreaId => $impactAreaIndicators)
        {
            $singleIndicator = array("id" => $impactAreaId, "title" => $indicatorTitles[$impactAreaId][0], "value" => $impactAreaIndicators);
            array_push($impactAreaIndicatorValue, $singleIndicator);
        }
        $singleHeader = array("header" => "clarisa_impact_areas_indicators", "value" => $impactAreaIndicatorValue);
        array_push($clarisa_vocabulary, $singleHeader);


        return response()->json($clarisa_vocabulary, 201);
    }

    //testing function
    public function  getInnovationsTest($user_id)
    {
        //Find the distinct innovations of user_id
        $innovationsDistinct = Innovation::where('userIds' , $user_id)
            ->where('deleted', false)
            ->distinct('innovId')
            ->get();


        Log::info('Retrieving all user distinct innovations ', [$user_id]);
        return response()->json(["result" => "ok", "innovations" => $innovationsDistinct], 201);
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

        //generate uuid with INNOV- prefix included
        $uuid = Str::uuid()->toString();
        $innovation->innovId = 'INNOV-'.$uuid;

        //Data assignment from resource and local generation
        $innovation->userIds = [$request->user_id];
        $innovation->status = $request->status;                 //DRAFT || READY TODO:(??ask nick??)
        $innovation->version = 1;                               //First time creating so version 1
        $innovation->persistId = [];                            //Later will add hdl, empty for now
        $innovation->deleted = false;                           //True for soft deleted innovations
        $innovation->reviewerIds = [];                          //Array of strings, empty on initialise
        $innovation->comments = " ";                            //Reviewer's comments, empty on initialise
        $innovation->formData = $request->form_data;            //The actual innovation data

        //Validation on the final user entities
        //for later

        //Save to database and log
        $innovation->save();
        Log::info('Adding new innovation with id: ', [$innovation->innovId, $request->toArray() ]);

        return response()->json(["result" => "ok"], 201);

    }

    /*
    ////PUT || PATCH
    */

    //Edit an existing innovation, change on formData or status       {user}
    public function editInnovation(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innov_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'status' => [
                'required', Rule::in(['DRAFT','READY']),
            ],
        );
        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the innovation from the database (latest version for safety)
        $innovation = Innovation::where('innovId', $request->innov_id)
            ->where('deleted', false)
            ->where(function ($query) {
                $query->where('status', "DRAFT")->
                orWhere('status', "READY");
            })
            ->orderBy('version', 'desc')
            ->first();

        //Log::info('EXTRA LOG', [$innovation]);
        //Check if null was returned from the database
        if($innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innov_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        //Check if user is an author
        if(in_array($request->user_id, $innovation->userIds))
        {
            Log::info('User has author privileges',[$request->user_id]);
        }
        else{
            Log::warning('User does not have required privileges', [$request->user_id]);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have required privileges'], 202);
        }

        //Patch the innovation data
        $innovation->formData = $request->form_data;
        $innovation->status = $request->status;

        //Save, log and response
        $innovation->save();
        Log::info('Updating innovation', [$innovation]);
        return response()->json(["result" => "ok"], 201);
    }

    //Submit an innovation that has status READY
    public function submitInnovation(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innov_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
        );

        $validator = Validator::make($request->toArray(),$requestRules);
        if ($validator->fails()) {
            Log::error('Request Validation Failed: ', [$validator->errors(), $request->toArray()]);
            return response()->json(["result" => "failed","errorMessage" => $validator->errors()], 400);
        }

        //Fetch the innovation from the database (latest version for safety)
        $innovation = Innovation::where('innovId', $request->innov_id)
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
            Log::warning('Requested innovation not found', [$request->innov_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }
        if($innovation->status != "READY")
        {
            Log::warning('Requested innovation can not be submitted', [$request->innov_id]);
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

        $innovation->status = "SUBMITTED";
        $innovation->save();
        Log::info('Submitting innovation', [$innovation]);
        return response()->json(["result" => "ok"], 201);

    }


    //Assign a reviewer to an innovation with status SUBMITTED based on reviewer_id given
    public function assignReviewer(Request $request)
    {
        //Request vallidation
        $requestRules = array(
            'innovation_id' => 'required|exists:App\Models\Innovation,innovId|string',
            'user_id' => 'required|exists:App\Models\User,userId|string|numeric',
            'reviewer_id' => 'required|exists:App\Models\User,userId|string|numeric',
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
            Log::warning('User does not have administrator rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have administrator rights: '], 202);
        }

        //Check user is reviewer
        $adminUser = User::find($request->reviewer_id);
        if(in_array("Reviewer", $adminUser->permissions))
        {
            Log::info('Requested user has Reviewer permissions: ', [$request->reviewer_id]);
        }
        else{
            Log::warning('User does not have reviewer rights: ', $adminUser->permissions);
            return response()->json(["result" => "failed","errorMessage" => 'User does not have reviewer rights: '], 202);
        }

        $innovation = Innovation::where('innovId', $request->innovation_id)
            ->where('deleted', false)
            ->where('status', "SUBMITTED")
            ->orderBy('version', 'desc')
            ->first();


        if( $innovation == null)
        {
            Log::warning('Requested innovation not found', [$request->innov_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested innovation not found'], 202);
        }

        $tempReviewersArray = $innovation->reviewerIds;
        if(in_array($request->reviewer_id, $tempReviewersArray))
        {
            Log::info('Requested reviewer has already been assigned this innovation: ', [$request->reviewer_id]);
            return response()->json(["result" => "failed","errorMessage" => 'Requested reviewer has already been assigned this innovation: '], 202);
        }

        array_push($tempReviewersArray, $request->reviewer_id);
        $innovation->reviewerIds = $tempReviewersArray;
        $innovation->save();
        Log::info('Assigning innovation to reviewer ', [$innovation]);
        return response()->json(["result" => "ok"], 201);
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
                                            orWhere('status', "READY");
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
        $innovation->save();

        return response()->json(["result" => "ok"], 201);
    }



}
